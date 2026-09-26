<?php

namespace Tests\Feature;

use App\Livewire\Stats;
use App\Models\Event;
use App\Models\LifeArea;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-23 12:00:00', 'Europe/Lisbon'));
    }

    public function test_default_range_includes_six_calendar_months_through_today(): void
    {
        $area = $this->area('Health');
        $this->entry($area, '2026-03-23 00:00:00');
        $this->entry($area, '2026-09-23 23:59:59');
        $this->entry($area, '2026-03-22 23:59:59');
        $this->entry($area, '2026-09-24 00:00:00');

        Livewire::test(Stats::class)
            ->assertSet('from', '2026-03-23')->assertSet('to', '2026-09-23')
            ->assertSet('appliedFrom', '2026-03-23')->assertSet('appliedTo', '2026-09-23')
            ->assertViewHas('overview', ['entries' => 2, 'check_in_days' => 2, 'life_areas' => 1])
            ->assertSee('Showing 23 Mar 2026 – 23 Sep 2026, inclusive')
            ->assertDontSee('Life balance')->assertDontSee('All time')
            ->assertDontSee('Activity over time')->assertDontSee('View activity data')
            ->assertSee('Entries by Life Area')->assertSee('Life Area radar chart.');
    }

    public function test_default_month_subtraction_does_not_overflow_and_uses_lisbon_today(): void
    {
        // This UTC instant is already 31 August in Lisbon.
        $this->travelTo(Carbon::parse('2026-08-30 23:30:00', 'UTC'));
        Livewire::test(Stats::class)->assertSet('from', '2026-02-28')->assertSet('to', '2026-08-31');
    }

    public function test_apply_updates_every_section_with_inclusive_custom_boundaries(): void
    {
        $health = $this->area('Health');
        $work = $this->area('Work');
        $outside = $this->area('Outside range');
        $this->entry($health, '2026-07-10 00:00:00', 'positive');
        $this->entry($health, '2026-07-10 12:00:00', 'neutral');
        $this->entry($health, '2026-07-12 23:59:59', 'positive');
        $this->entry($work, '2026-07-11 10:00:00', 'negative');
        $this->entry($outside, '2026-07-09 23:59:59');
        $this->entry($outside, '2026-07-13 00:00:00');

        Livewire::test(Stats::class)->set('from', '2026-07-10')->set('to', '2026-07-12')->call('applyRange')
            ->assertHasNoErrors()
            ->assertSet('appliedFrom', '2026-07-10')->assertSet('appliedTo', '2026-07-12')
            ->assertSee('Showing 10 Jul 2026 – 12 Jul 2026, inclusive')
            ->assertViewHas('overview', ['entries' => 4, 'check_in_days' => 3, 'life_areas' => 2])
            ->assertViewHas('distribution', [
                ['type' => 'positive', 'entries' => 2, 'share' => 50.0],
                ['type' => 'neutral', 'entries' => 1, 'share' => 25.0],
                ['type' => 'negative', 'entries' => 1, 'share' => 25.0],
            ])
            ->assertViewHas('lifeAreas', fn ($rows) => array_column($rows, 'name') === ['Health', 'Work', 'Outside range']
                && array_column($rows, 'entries') === [3, 1, 0]
                && array_column($rows, 'share') === [75.0, 25.0, 0.0])
            ->assertSee('3 entries · 75.0%')->assertSee('1 entry · 25.0%');
    }

    public function test_draft_edits_and_failed_validation_preserve_all_applied_results(): void
    {
        $this->entry($this->area('Health'), '2026-09-23 09:00:00');
        $component = Livewire::test(Stats::class);
        $original = [];
        foreach (['overview', 'distribution', 'lifeAreas', 'radarAreas', 'periodLabel'] as $key) {
            $original[$key] = $component->viewData($key);
        }

        $component->set('from', '2026-10-10')->set('to', '2026-10-01');
        foreach ($original as $key => $value) {
            $component->assertViewHas($key, $value);
        }
        $component->call('applyRange')->assertHasErrors(['to' => 'after_or_equal'])
            ->assertSet('appliedFrom', '2026-03-23')->assertSet('appliedTo', '2026-09-23')
            ->assertSee('To must be on or after From.');
        foreach ($original as $key => $value) {
            $component->assertViewHas($key, $value);
        }
        $component->set('to', '2026-10-10')->call('applyRange')->assertHasNoErrors()
            ->assertSet('appliedFrom', '2026-10-10')->assertSet('appliedTo', '2026-10-10');
    }

    public function test_missing_and_invalid_dates_are_rejected_without_changing_the_range(): void
    {
        foreach ([['', '2026-09-23', 'from'], ['2026-02-30', '2026-09-23', 'from'],
            ['not-a-date', '2026-09-23', 'from'], ['2026-03-23', '', 'to'],
            ['2026-03-23', '2026-13-01', 'to']] as [$from, $to, $field]) {
            Livewire::test(Stats::class)->set('from', $from)->set('to', $to)->call('applyRange')
                ->assertHasErrors([$field])
                ->assertSet('appliedFrom', '2026-03-23')->assertSet('appliedTo', '2026-09-23');
        }
    }

    public function test_same_day_ranges_use_lisbon_clock_values_across_dst_changes(): void
    {
        $area = $this->area('Health');
        foreach (['2026-03-29', '2026-10-25'] as $date) {
            $day = CarbonImmutable::parse($date, 'Europe/Lisbon');
            $this->entry($area, $day->subSecond()->toDateTimeString());
            $this->entry($area, $date.' 00:00:00');
            $this->entry($area, $date.' 23:59:59');
            $this->entry($area, $day->addDay()->toDateTimeString());
            Livewire::test(Stats::class)->set('from', $date)->set('to', $date)->call('applyRange')
                ->assertHasNoErrors()
                ->assertViewHas('overview', ['entries' => 2, 'check_in_days' => 1, 'life_areas' => 1])
                ->assertViewHas('radarAreas', fn ($rows) => array_column($rows, 'entries') === [2]);
        }
    }

    public function test_empty_ranges_show_zero_counts_without_placeholder_analysis(): void
    {
        $this->entry($this->area('Health'), '2026-09-23 10:00:00');
        Livewire::test(Stats::class)->set('from', '2020-01-01')->set('to', '2020-01-03')->call('applyRange')
            ->assertViewHas('overview', ['entries' => 0, 'check_in_days' => 0, 'life_areas' => 0])
            ->assertViewHas('distribution', fn ($rows) => array_column($rows, 'entries') === [0, 0, 0]
                && array_column($rows, 'share') === [0.0, 0.0, 0.0])
            ->assertViewHas('lifeAreas', fn ($rows) => array_column($rows, 'entries') === [0]
                && array_column($rows, 'share') === [0.0])
            ->assertSee('No entries recorded in this period.')
            ->assertDontSee('Life balance');
    }

    public function test_life_area_bars_use_applied_counts_and_handle_empty_ranges(): void
    {
        foreach (['Health', 'Work', 'Personal growth'] as $name) {
            $this->entry($this->area($name), '2026-09-23 10:00:00');
        }

        Livewire::test(Stats::class)
            ->assertSee('Entries by Life Area')
            ->assertSeeInOrder(['Health', 'Personal growth', 'Work'])
            ->assertSee('1 entry · 33.3%')
            ->assertSee('style="width: 33.3%"', false)
            ->set('from', '2020-01-01')->set('to', '2020-01-02')
            ->assertSee('1 entry · 33.3%')
            ->call('applyRange')
            ->assertSee('0 entries · 0.0%')
            ->assertSee('style="width: 0%"', false)
            ->assertSee('No entries recorded in this period.');
    }

    public function test_life_areas_rank_all_entry_types_and_include_areas_without_events(): void
    {
        $neutral = $this->area('Neutral only');
        $mixed = $this->area('Mixed');
        $negative = $this->area('Negative only');
        $this->area('No events');
        $this->entry($mixed, '2026-09-23 00:00:00', 'positive');
        $this->entry($mixed, '2026-09-23 23:59:59', 'positive');
        $this->entry($mixed, '2026-09-23 12:00:00', 'negative');
        $this->entry($neutral, '2026-09-23 12:00:00', 'neutral');
        $this->entry($negative, '2026-09-23 12:00:00', 'negative');
        $this->entry($mixed, '2026-09-22 23:59:59', 'positive');
        $this->entry($mixed, '2026-09-24 00:00:00', 'positive');

        Livewire::test(Stats::class)->set('from', '2026-09-23')->set('to', '2026-09-23')->call('applyRange')
            ->assertViewHas('lifeAreas', fn ($rows) => array_column($rows, 'name') === ['Mixed', 'Negative only', 'Neutral only', 'No events']
                && array_column($rows, 'entries') === [3, 1, 1, 0]
                && array_column($rows, 'share') === [60.0, 20.0, 20.0, 0.0])
            ->assertSeeInOrder(['Mixed', 'Negative only', 'Neutral only', 'No events'])
            ->assertSee('3 entries · 60.0%')
            ->assertSee('style="width: 60%"', false)
            ->assertViewHas('overview', ['entries' => 5, 'check_in_days' => 1, 'life_areas' => 3]);
    }

    public function test_life_area_bars_handle_no_life_areas(): void
    {
        Livewire::test(Stats::class)->assertViewHas('lifeAreas', [])
            ->assertSee('No Life Areas available.');
    }

    public function test_radar_uses_applied_area_counts_and_handles_empty_ranges(): void
    {
        foreach (['Health', 'Work', 'Personal growth'] as $name) {
            $this->entry($this->area($name), '2026-09-23 10:00:00');
        }

        Livewire::test(Stats::class)
            ->assertSee('Life Area radar chart.', false)
            ->assertSee('Health: 1 positive entry')
            ->assertSee('Work: 1 positive entry')
            ->assertSee('Personal growth: 1 positive entry')
            ->assertSee('fill-opacity="0.18"', false)
            ->set('from', '2020-01-01')->set('to', '2020-01-02')
            ->assertSee('Health: 1 positive entry')
            ->call('applyRange')
            ->assertViewHas('radarAreas', fn ($rows) => array_column($rows, 'entries') === [0, 0, 0])
            ->assertSee('Life Area radar chart.', false)
            ->assertSee('Health: 0 positive entries')
            ->assertSee('No positive entries recorded in this period.');
    }

    public function test_radar_counts_only_positive_entries_within_inclusive_boundaries(): void
    {
        $neutral = $this->area('Neutral only');
        $positive = $this->area('Positive');
        $negative = $this->area('Negative only');
        $this->area('No events');
        $this->entry($positive, '2026-09-23 00:00:00', 'positive');
        $this->entry($positive, '2026-09-23 23:59:59', 'positive');
        $this->entry($positive, '2026-09-23 12:00:00', 'negative');
        $this->entry($neutral, '2026-09-23 12:00:00', 'neutral');
        $this->entry($negative, '2026-09-23 12:00:00', 'negative');
        $this->entry($positive, '2026-09-22 23:59:59', 'positive');
        $this->entry($positive, '2026-09-24 00:00:00', 'positive');

        Livewire::test(Stats::class)->set('from', '2026-09-23')->set('to', '2026-09-23')->call('applyRange')
            ->assertViewHas('radarAreas', fn ($rows) => array_column($rows, 'name') === ['Neutral only', 'Positive', 'Negative only', 'No events']
                && array_column($rows, 'entries') === [0, 2, 0, 0])
            ->assertViewHas('lifeAreas', fn ($rows) => array_column($rows, 'entries') === [3, 1, 1, 0])
            ->assertSee('Positive: 2 positive entries')
            ->assertSee('No events: 0 positive entries')
            ->assertViewHas('overview', ['entries' => 5, 'check_in_days' => 1, 'life_areas' => 3]);
    }

    public function test_radar_updates_when_events_change_and_preserves_axis_order(): void
    {
        $health = $this->area('Health');
        $work = $this->area('Work');
        $component = Livewire::test(Stats::class)
            ->assertSee('Health: 0 positive entries')
            ->assertSee('Fewer than three Life Areas are available; points are shown without a filled shape.')
            ->assertSee('<polyline', false);

        $this->entry($work, '2026-09-23 10:00:00');
        $component->call('$refresh')
            ->assertViewHas('radarAreas', fn ($rows) => array_column($rows, 'id') === [$health->id, $work->id]
                && array_column($rows, 'entries') === [0, 1])
            ->assertSee('Work: 1 positive entry');
    }

    public function test_radar_renders_interactive_labels_and_points(): void
    {
        $this->entry($this->area('Health'), '2026-09-23 10:00:00');
        $html = Livewire::test(Stats::class)->html();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $controls = $xpath->query('//*[local-name()="svg"]//*[@role="button"]');
        $this->assertCount(2, $controls);
        foreach ($controls as $control) {
            $this->assertSame('0', $control->getAttribute('tabindex'));
            $this->assertSame('Health: 1 positive entry', $control->getAttribute('aria-label'));
            foreach (['mouseenter', 'focus', 'click', 'keydown.enter.prevent', 'keydown.space.prevent', 'keydown.escape'] as $event) {
                $this->assertTrue($control->hasAttribute('x-on:'.$event));
            }
        }
    }

    public function test_radar_handles_no_life_areas(): void
    {
        Livewire::test(Stats::class)->assertViewHas('radarAreas', [])
            ->assertSee('No Life Areas available.')
            ->assertDontSee('Life Area radar chart.', false);
    }

    public function test_distribution_bars_use_type_colors_and_applied_percentages(): void
    {
        $area = $this->area('Health');
        foreach (['positive', 'positive', 'neutral', 'negative'] as $type) {
            $this->entry($area, '2026-09-23 10:00:00', $type);
        }
        $this->entry($area, '2026-09-22 10:00:00', 'negative');

        $component = Livewire::test(Stats::class)
            ->set('from', '2026-09-23')->set('to', '2026-09-23')->call('applyRange');
        $this->assertDistributionBars($component->html(), [50, 25, 25]);

        $component->set('from', '2026-09-22')->set('to', '2026-09-22');
        $this->assertDistributionBars($component->html(), [50, 25, 25]);
        $component->call('applyRange')->assertHasNoErrors();
        $this->assertDistributionBars($component->html(), [0, 0, 100]);
    }

    public function test_distribution_bars_preserve_empty_period_state(): void
    {
        $this->entry($this->area('Health'), '2026-09-23 10:00:00');
        $component = Livewire::test(Stats::class)
            ->set('from', '2020-01-01')->set('to', '2020-01-02')->call('applyRange')
            ->assertSee('No entries to calculate a distribution.')
            ->assertViewHas('distribution', fn ($rows) => array_column($rows, 'entries') === [0, 0, 0]
                && array_column($rows, 'share') === [0.0, 0.0, 0.0]);

        $this->assertDistributionBars($component->html(), [0, 0, 0]);
    }

    private function assertDistributionBars(string $html, array $widths): void
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $rows = $xpath->query('//section[@aria-labelledby="type-heading"]/ul/li');
        $this->assertCount(3, $rows);

        foreach (['Positive' => 'bg-emerald-600', 'Neutral' => 'bg-slate-500', 'Negative' => 'bg-red-600'] as $label => $color) {
            $index = array_search($label, ['Positive', 'Neutral', 'Negative']);
            $row = $rows->item($index);
            $this->assertStringContainsString($label, $row->textContent);
            $bars = $xpath->query('./div[@aria-hidden="true"]/div', $row);
            $this->assertCount(1, $bars);
            $bar = $bars->item(0);
            $this->assertSame('width: '.$widths[$index].'%', $bar->getAttribute('style'));
            $this->assertContains($color, explode(' ', $bar->getAttribute('class')));
        }
    }

    private function area(string $name): LifeArea
    {
        $area = new LifeArea;
        $area->name = $name;
        $area->save();

        return $area;
    }

    private function entry(LifeArea $area, string $createdAt, string $type = 'positive'): void
    {
        $event = new Event(['life_area_id' => $area->id, 'type' => $type, 'description' => 'Stats fixture']);
        $event->created_at = $createdAt;
        $event->save();
    }
}
