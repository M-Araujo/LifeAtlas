<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Livewire\History;
use App\Models\Event;
use App\Models\LifeArea;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class HistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-16 12:00:00'));
    }

    public function test_date_ranges_include_boundaries_and_exclude_older_entries(): void
    {
        $area = $this->area('Health');
        foreach (['30_days' => '2026-08-18', '6_months' => '2026-03-16', '1_year' => '2025-09-16'] as $range => $date) {
            Event::query()->delete();
            $boundary = $this->entry($area);
            $boundary->created_at = $date.' 00:00:00';
            $boundary->save();
            $older = $this->entry($area);
            $older->created_at = Carbon::parse($date)->subSecond();
            $older->save();
            $future = $this->entry($area);
            $future->created_at = today()->addDay();
            $future->save();

            Livewire::test(History::class)->assertSet('dateRange', '6_months')
                ->set('dateRange', $range)
                ->assertViewHas('events', fn ($events) => $events->getCollection()->modelKeys() === [$boundary->id])
                ->set('dateRange', 'all')->assertViewHas('events', fn ($events) => $events->total() === 3);
        }
    }

    public function test_default_range_and_combined_filters_exclude_old_matches(): void
    {
        $area = $this->area('Health');
        $recent = $this->entry($area);
        $old = $this->entry($area);
        $old->created_at = today()->subMonths(7);
        $old->save();
        $this->entry($area, 'Different note');
        $this->entry($this->area('Work'));
        Livewire::test(History::class)->set('search', 'Morning')->set('searchLifeArea', $area->id)
            ->assertViewHas('events', fn ($events) => $events->getCollection()->modelKeys() === [$recent->id])
            ->set('dateRange', 'all')->assertViewHas('events', fn ($events) => $events->total() === 2)
            ->set('dateRange', 'invalid')->assertSet('dateRange', '6_months');
    }

    public function test_database_pagination_and_each_filter_reset_the_page(): void
    {
        $area = $this->area('Health');
        $ids = [];
        for ($i = 0; $i < 31; $i++) {
            $ids[] = $this->entry($area)->id;
        }
        $component = Livewire::test(History::class)
            ->assertViewHas('events', fn ($events) => $events instanceof LengthAwarePaginator
                && $events->total() === 31 && $events->count() === 15
                && $events->getCollection()->modelKeys() === array_slice(array_reverse($ids), 0, 15))
            ->call('setPage', 3)->assertViewHas('events', fn ($events) => $events->count() === 1);

        foreach (['search' => 'Morning', 'searchLifeArea' => $area->id, 'dateRange' => 'all'] as $filter => $value) {
            $component->call('setPage', 2)->set($filter, $value)
                ->assertViewHas('events', fn ($events) => $events->currentPage() === 1);
        }
    }

    public function test_edit_and_delete_keep_filters_and_recover_from_an_empty_last_page(): void
    {
        $area = $this->area('Health');
        $first = $this->entry($area);
        for ($i = 0; $i < 16; $i++) {
            $this->entry($area);
        }
        $component = Livewire::test(History::class)->set('search', 'Morning')
            ->set('searchLifeArea', $area->id)->set('dateRange', 'all')->call('setPage', 2)
            ->call('editEvent', $first->id)->set('type', 'neutral')->call('updateEvent')
            ->assertViewHas('events', fn ($events) => $events->currentPage() === 2)
            ->call('editEvent', $first->id)->set('description', 'No longer matches')->call('updateEvent')
            ->assertViewHas('events', fn ($events) => $events->currentPage() === 2 && $events->count() === 1);
        $last = Event::where('description', 'like', '%Morning%')->oldest('id')->first();
        $component->call('confirmDelete', $last->id)->call('deleteEvent')
            ->assertSet('search', 'Morning')->assertSet('searchLifeArea', $area->id)->assertSet('dateRange', 'all')
            ->assertViewHas('events', fn ($events) => $events->currentPage() === 1 && $events->total() === 15);
    }

    public function test_full_multiline_descriptions_are_rendered_without_clipping(): void
    {
        $description = str_repeat('Complete text ', 100)."\nFinal line <safe>";
        $this->entry($this->area('Health'), $description);
        Livewire::test(History::class)->assertSee($description)
            ->assertSee('whitespace-pre-wrap', false)->assertSee('overflow-wrap:anywhere', false)
            ->assertDontSee('max-h-80', false)->assertDontSee('line-clamp', false)
            ->assertDontSee('View full description');
    }

    private function area(string $name): LifeArea
    {
        $area = new LifeArea;
        $area->name = $name;
        $area->save();

        return $area;
    }

    private function entry(LifeArea $area, string $description = 'Morning walk'): Event
    {
        return Event::create(['life_area_id' => $area->id, 'description' => $description, 'type' => 'positive']);
    }

    public function test_history_displays_types_and_combines_search_and_area_filters(): void
    {
        $area = $this->area('Health');
        $match = $this->entry($area);
        $this->entry($area, 'Evening rest');
        $this->entry($this->area('Work'), 'Morning meeting');

        Livewire::test(History::class)
            ->assertSeeInOrder(['Date', 'Life Area', 'Type', 'Description', 'Actions'])
            ->assertSee('Positive')->assertSee('Edit')->assertSee('Delete')
            ->set('search', 'Morning')->set('searchLifeArea', (string) $area->id)
            ->assertViewHas('events', fn ($events) => $events->getCollection()->modelKeys() === [$match->id]);
    }

    public function test_edit_updates_fields_preserves_date_and_keeps_filters(): void
    {
        $area = $this->area('Health');
        $other = $this->area('Work');
        $event = $this->entry($area);
        $createdAt = $event->created_at->toDateTimeString();

        Livewire::test(History::class)
            ->set('search', 'Morning')->set('searchLifeArea', (string) $area->id)
            ->call('editEvent', $event->id)->assertSet('description', 'Morning walk')
            ->assertDispatched('open-modal', 'edit-entry')
            ->set('life_area_id', $other->id)->set('description', 'Changed note')->set('type', 'negative')
            ->call('updateEvent')->assertHasNoErrors()
            ->assertSet('editingEventId', null)->assertSet('message', 'Entry updated.')
            ->assertSet('search', 'Morning')->assertSet('searchLifeArea', (string) $area->id)
            ->assertViewHas('events', fn ($events) => $events->isEmpty());

        $this->assertDatabaseHas('events', ['id' => $event->id, 'life_area_id' => $other->id, 'description' => 'Changed note', 'type' => 'negative', 'created_at' => $createdAt]);
    }

    public function test_invalid_edits_do_not_save_and_cancel_clears_form_errors(): void
    {
        $event = $this->entry($this->area('Health'));
        $component = Livewire::test(History::class)->call('editEvent', $event->id)
            ->set('life_area_id', 999)->set('description', '')->set('type', 'invalid')
            ->call('updateEvent')->assertHasErrors(['life_area_id', 'description', 'type'])
            ->set('description', str_repeat('x', 10001))->call('updateEvent')
            ->assertHasErrors(['description' => 'max'])
            ->call('cancelEdit')->assertSet('editingEventId', null)->assertHasNoErrors();

        $component->call('editEvent', $event->id)->assertSet('description', 'Morning walk')->assertSet('type', 'positive');
        $this->assertSame('Morning walk', $event->fresh()->description);
    }

    public function test_delete_requires_confirmation_and_cancel_prevents_deletion(): void
    {
        $event = $this->entry($this->area('Health'));
        $component = Livewire::test(History::class)->call('deleteEvent')
            ->call('confirmDelete', $event->id)->assertSet('deletingEventId', $event->id)
            ->assertDispatched('open-modal', 'delete-entry');
        $this->assertModelExists($event);

        $component->call('cancelDelete')->assertSet('deletingEventId', null)->call('deleteEvent');
        $this->assertModelExists($event);
    }

    public function test_confirmed_delete_removes_only_selected_entry_and_keeps_filters(): void
    {
        $area = $this->area('Health');
        $event = $this->entry($area);
        $other = $this->entry($area, 'Morning run');
        Livewire::test(History::class)->set('search', 'Morning')->set('searchLifeArea', (string) $area->id)
            ->call('confirmDelete', $event->id)->call('deleteEvent')
            ->assertSet('deletingEventId', null)->assertSet('message', 'Entry deleted.')
            ->assertSet('search', 'Morning')->assertSet('searchLifeArea', (string) $area->id)
            ->assertViewHas('events', fn ($events) => $events->getCollection()->modelKeys() === [$other->id]);
        $this->assertModelMissing($event);
        $this->assertModelExists($other);
    }

    public function test_entries_removed_while_dialogs_are_open_are_handled(): void
    {
        $area = $this->area('Health');
        $event = $this->entry($area);
        $component = Livewire::test(History::class)->call('editEvent', $event->id);
        $event->delete();
        $component->call('updateEvent')->assertSet('editingEventId', null)
            ->assertSet('message', 'This entry no longer exists.');

        $event = $this->entry($area);
        $component->call('confirmDelete', $event->id);
        $event->delete();
        $component->call('deleteEvent')->assertSet('deletingEventId', null)
            ->assertSet('message', 'This entry no longer exists.')
            ->call('editEvent', $event->id)->assertSet('editingEventId', null)
            ->call('confirmDelete', $event->id)->assertSet('deletingEventId', null);
    }

    public function test_dashboard_still_creates_entries_and_updates_weekly_statistics(): void
    {
        Queue::fake();
        $area = $this->area('Health');
        Livewire::test(Dashboard::class)->set('life_area_id', $area->id)
            ->set('description', 'New check-in')->set('type', 'neutral')->call('saveRecord')
            ->assertHasNoErrors()->assertViewHas('weeklyStats', fn ($stats) => $stats['neutral'] === 1 && $stats['total'] === 1)
            ->assertDontSee('Edit entry')->assertDontSee('Delete entry');
        $this->assertDatabaseHas('events', ['description' => 'New check-in', 'life_area_id' => $area->id]);
    }
}
