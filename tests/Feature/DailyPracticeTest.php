<?php

namespace Tests\Feature;

use App\Livewire\DailyPractice;
use App\Models\Backup;
use App\Models\DailyImprovement;
use App\Models\DailyPractice as Practice;
use App\Models\Principle;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class DailyPracticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-26 12:00:00', 'Europe/Lisbon'));
    }

    public function test_page_has_one_add_form_and_reflective_guidance_without_persisted_inputs(): void
    {
        Backup::create(['backup_date' => '2026-09-26', 'status' => 'completed']);
        $this->get('/daily-practice')->assertOk()->assertSee("Bruce's Practice", false)
            ->assertSee('How am I going to express myself honestly today?')
            ->assertSee('Water and adaptability')->assertSee('lg:grid-cols-2')
            ->assertDontSee('honestExpression')->assertDontSee('Mirror');
        $component = Livewire::test(DailyPractice::class);
        $this->assertSame(1, substr_count($component->html(), 'wire:submit="addImprovement"'));
        $this->assertDatabaseCount('daily_improvements', 0);
        $component->call('$refresh');
        $this->assertDatabaseCount('daily_practices', 0);
        $this->assertFalse(Schema::hasColumn('daily_practices', 'honest_expression'));
        $this->assertFalse(Schema::hasColumn('daily_practices', 'improvements_created'));
        $this->assertFalse(Schema::hasColumn('daily_improvements', 'position'));
    }

    public function test_midnight_add_uses_current_lisbon_date(): void
    {
        $this->travelTo(Carbon::parse('2026-09-26 22:59:00', 'UTC'));
        $component = Livewire::test(DailyPractice::class);
        $component->set('newIssue', 'Before midnight')->call('addImprovement')->assertHasNoErrors();
        $this->assertDatabaseCount('daily_practices', 1);
        $component->set('newIssue', 'Across midnight');
        $this->travelTo(Carbon::parse('2026-09-26 23:01:00', 'UTC'));
        $component->call('addImprovement')->assertHasNoErrors();
        $this->assertSame('2026-09-27', DailyImprovement::latest('id')->first()->practice->practice_date->toDateString());
        $this->assertDatabaseCount('daily_practices', 2);
    }

    public function test_dst_changes_allow_multiple_practices_on_the_correct_lisbon_date(): void
    {
        foreach (['2026-03-29', '2026-10-25'] as $date) {
            $this->travelTo(Carbon::parse($date.' 00:30:00', 'UTC'));
            $component = Livewire::test(DailyPractice::class);
            $component->set('newIssue', 'First practice')->call('addImprovement')->assertHasNoErrors();
            $this->travelTo(Carbon::parse($date.' 02:30:00', 'UTC'));
            $component->set('newIssue', 'Second practice')->call('addImprovement')->assertHasNoErrors();
            $this->assertSame(2, Practice::where('practice_date', $date)->count());
        }
        $this->assertDatabaseCount('daily_practices', 4);
    }

    public function test_migration_allows_duplicate_dates_and_preserves_existing_improvements(): void
    {
        $item = $this->item('2026-09-26', ['solution' => 'Keep this solution']);
        $migration = require database_path('migrations/2026_10_02_000001_allow_multiple_daily_practices_per_date.php');
        $migration->down();
        $migration->up();
        Practice::create(['practice_date' => '2026-09-26']);
        $this->assertDatabaseCount('daily_practices', 2);
        $this->assertSame('Keep this solution', $item->fresh()->solution);
        $this->assertSame($item->daily_practice_id, $item->fresh()->daily_practice_id);
    }

    public function test_only_verified_active_principles_are_selected_and_stable(): void
    {
        $eligible = $this->principle();
        $this->principle(['active' => false]);
        $this->principle(['verified_at' => null]);
        $this->principle(['verified_at' => now()->addDay()]);
        $this->principle(['source_reference' => '']);
        $this->principle(['text' => ' ']);
        $component = Livewire::test(DailyPractice::class)->assertDontSee('Daily Bruce Lee principle')
            ->assertDontSee('Synthetic principle')->assertDontSee('Synthetic reference');
        $component->set('newIssue', 'First practice')->call('addImprovement')->assertHasNoErrors();
        $this->assertSame($eligible->id, Practice::first()->principle_id);
        $this->principle(['text' => 'Another principle']);
        $component->call('$refresh')->assertDontSee('Synthetic principle');
        $this->assertSame($eligible->id, Practice::first()->principle_id);
    }

    public function test_no_verified_principle_has_a_safe_stable_empty_state(): void
    {
        $this->principle(['verified_at' => null]);
        Livewire::test(DailyPractice::class)->assertDontSee('Daily Bruce Lee principle')
            ->assertDontSee('No daily principle available.')
            ->set('newIssue', 'Still usable')->call('addImprovement')->assertHasNoErrors();
        $this->principle();
        Livewire::test(DailyPractice::class)->assertDontSee('No daily principle available.');
    }

    public function test_multiple_improvements_are_created_independently_without_solutions_or_a_limit(): void
    {
        $component = Livewire::test(DailyPractice::class);
        for ($number = 1; $number <= 6; $number++) {
            $component->set('newIssue', "Issue $number")
                ->call('addImprovement')->assertHasNoErrors()->assertSet('newIssue', '')
                ->assertSee("Issue $number")->assertSee('No solution yet');
            $this->assertDatabaseCount('daily_improvements', $number);
            $this->assertDatabaseCount('daily_practices', $number);
            $this->assertDatabaseHas('daily_improvements', [
                'issue' => "Issue $number", 'solution' => null, 'status' => 'identified', 'resolved_at' => null,
            ]);
        }
        Livewire::test(DailyPractice::class)->assertViewHas('items', fn ($items) => $items->count() === 6);
        $this->assertSame(6, DailyImprovement::distinct()->count('daily_practice_id'));
        $this->assertSame(6, Practice::where('practice_date', '2026-09-26')->count());
    }

    public function test_active_list_includes_today_and_older_items_newest_first_and_excludes_resolved_and_future(): void
    {
        $old = $this->item('2026-09-25', ['issue' => 'Older active']);
        $today = $this->item('2026-09-26', ['issue' => 'Today active']);
        $this->item('2026-09-25', ['issue' => 'Older resolved', 'status' => 'resolved']);
        $this->item('2026-09-26', ['issue' => 'Today resolved', 'status' => 'resolved']);
        $this->item('2026-09-27', ['issue' => 'Future issue']);
        Livewire::test(DailyPractice::class)->assertSee('Older active')->assertSee('Today active')
            ->assertDontSee('Older resolved')->assertDontSee('Today resolved')->assertDontSee('Future issue')
            ->assertViewHas('items', fn ($items) => $items->modelKeys() === [$today->id, $old->id]);
    }

    public function test_card_edits_are_independent_and_preserve_unsaved_add_text(): void
    {
        $first = $this->item('2026-09-25');
        $second = $this->item('2026-09-26');
        $component = Livewire::test(DailyPractice::class)->set('newIssue', 'Unsaved add')
            ->call('editImprovement', $first->id)->set("drafts.$first->id.issue", 'Changed issue')
            ->set("drafts.$first->id.solution", 'New solution')->set("drafts.$first->id.status", 'working_on_it')
            ->call('editImprovement', $second->id)->set("drafts.$second->id.issue", 'Second draft')
            ->call('saveImprovement', $first->id)->assertHasNoErrors()
            ->assertSet('newIssue', 'Unsaved add')->assertSet("drafts.$second->id.issue", 'Second draft');
        $this->assertSame('Changed issue', $first->fresh()->issue);
        $this->assertSame('New solution', $first->fresh()->solution);
        $this->assertSame('working_on_it', $first->fresh()->status);
        $component->call('editImprovement', $first->id)->set("drafts.$first->id.solution", 'Edited solution')
            ->set("drafts.$first->id.status", 'improving')->call('saveImprovement', $first->id)->assertHasNoErrors();
        $this->assertSame('Edited solution', $first->fresh()->solution);
        $this->assertSame('improving', $first->fresh()->status);
        $component->call('cancelImprovementEdit', $second->id)->assertSet('newIssue', 'Unsaved add');
        $this->assertSame('Synthetic issue', $second->fresh()->issue);
        $component->call('editImprovement', $second->id)->set("drafts.$second->id.issue", 'Keep this draft')
            ->call('addImprovement')->assertSet("drafts.$second->id.issue", 'Keep this draft');
    }

    public function test_resolving_today_and_previous_cards_removes_them_but_keeps_records(): void
    {
        foreach (['2026-09-25', '2026-09-26'] as $date) {
            $item = $this->item($date);
            Livewire::test(DailyPractice::class)->call('editImprovement', $item->id)
                ->set("drafts.$item->id.status", 'resolved')->call('saveImprovement', $item->id)
                ->assertHasNoErrors()->assertViewHas('items', fn ($items) => $items->isEmpty());
            $this->assertTrue($item->fresh()->resolved_at->equalTo(now()));
        }
        $this->assertDatabaseCount('daily_improvements', 2);
    }

    public function test_resolved_timestamp_is_preserved_until_reopened(): void
    {
        $item = $this->item('2026-09-25');
        $item->applyStatus('resolved');
        $item->save();
        $resolvedAt = $item->resolved_at;
        $this->travel(10)->minutes();
        $item->applyStatus('resolved');
        $item->save();
        $this->assertTrue($resolvedAt->equalTo($item->fresh()->resolved_at));
        $item->applyStatus('improving');
        $item->save();
        $this->assertNull($item->fresh()->resolved_at);
        Livewire::test(DailyPractice::class)->assertSee($item->issue);
    }

    public function test_delete_has_confirmation_and_preserves_other_drafts(): void
    {
        $item = $this->item('2026-09-25');
        $other = $this->item('2026-09-26');
        Livewire::test(DailyPractice::class)->assertSee('wire:confirm=', false)
            ->set('newIssue', 'Unsaved add')->call('editImprovement', $other->id)
            ->set("drafts.$other->id.issue", 'Other draft')->call('deleteImprovement', $item->id)
            ->assertSet('newIssue', 'Unsaved add')->assertSet("drafts.$other->id.issue", 'Other draft');
        $this->assertDatabaseMissing('daily_improvements', ['id' => $item->id]);
        $this->assertModelExists($other);
    }

    public function test_ineligible_ids_are_rejected_and_save_rechecks_membership(): void
    {
        $future = $this->item('2026-09-27');
        $resolved = $this->item('2026-09-25', ['status' => 'resolved']);
        foreach ([$future->id, $resolved->id, 999999] as $id) {
            foreach (['editImprovement', 'deleteImprovement', 'saveImprovement'] as $action) {
                Livewire::test(DailyPractice::class)->call($action, $id)->assertStatus(404);
            }
        }
        $item = $this->item('2026-09-26');
        $component = Livewire::test(DailyPractice::class)->call('editImprovement', $item->id);
        $item->update(['status' => 'resolved', 'resolved_at' => now()]);
        $component->set("drafts.$item->id.issue", 'Must not save')->call('saveImprovement', $item->id)->assertStatus(404);
        $this->assertSame('Synthetic issue', $item->fresh()->issue);
    }

    public function test_editing_ids_cannot_be_manipulated(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(DailyPractice::class)->set('editingIds', [123]);
    }

    public function test_validation_rejects_blank_issues_and_invalid_status(): void
    {
        Livewire::test(DailyPractice::class)->set('newIssue', '   ')->call('addImprovement')->assertHasErrors('newIssue');
        $this->assertDatabaseCount('daily_practices', 0);
        $item = $this->item('2026-09-26');
        Livewire::test(DailyPractice::class)->call('editImprovement', $item->id)
            ->set("drafts.$item->id.issue", ' ')->set("drafts.$item->id.status", 'invalid')
            ->call('saveImprovement', $item->id)->assertHasErrors(["drafts.$item->id.issue", "drafts.$item->id.status"]);
        $this->assertSame('Synthetic issue', $item->fresh()->issue);
    }

    public function test_legacy_schema_upgrade_preserves_real_improvements_and_removes_empty_placeholders(): void
    {
        Schema::table('daily_practices', function (Blueprint $table) {
            $table->text('honest_expression')->nullable();
            $table->unsignedTinyInteger('improvements_created')->default(0);
        });
        Schema::table('daily_improvements', function (Blueprint $table) {
            $table->text('issue')->nullable()->change();
            $table->string('position')->nullable();
            $table->unique(['daily_practice_id', 'position']);
        });
        $item = $this->item('2026-09-25', ['solution' => 'Keep this solution']);
        DB::table('daily_improvements')->where('id', $item->id)->update(['position' => '1']);
        DB::table('daily_improvements')->insert([
            'daily_practice_id' => $item->daily_practice_id, 'position' => '2', 'issue' => null,
        ]);
        $migration = require database_path('migrations/2026_09_26_000004_simplify_daily_practice.php');
        $migration->up();
        $this->assertDatabaseCount('daily_improvements', 1);
        $this->assertSame('Keep this solution', $item->fresh()->solution);
        $this->assertFalse(Schema::hasColumn('daily_improvements', 'position'));
        $this->assertFalse(Schema::hasColumn('daily_practices', 'honest_expression'));
        $this->assertFalse(Schema::hasColumn('daily_practices', 'improvements_created'));
    }

    private function principle(array $attributes = []): Principle
    {
        return Principle::create(array_merge([
            'text' => 'Synthetic principle', 'source_reference' => 'Synthetic reference',
            'active' => true, 'verified_at' => now(),
        ], $attributes));
    }

    private function item(string $date, array $attributes = []): DailyImprovement
    {
        $practice = Practice::firstOrCreate(['practice_date' => $date]);

        return $practice->improvements()->create(array_merge(['issue' => 'Synthetic issue'], $attributes));
    }
}
