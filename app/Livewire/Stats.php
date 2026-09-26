<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\LifeArea;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Stats extends Component
{
    private const TIMEZONE = 'Europe/Lisbon';

    public string $from = '';

    public string $to = '';

    #[Locked]
    public string $appliedFrom = '';

    #[Locked]
    public string $appliedTo = '';

    public function mount(): void
    {
        $today = CarbonImmutable::today(self::TIMEZONE);
        $this->from = $this->appliedFrom = $today->subMonthsNoOverflow(6)->toDateString();
        $this->to = $this->appliedTo = $today->toDateString();
    }

    public function applyRange(): void
    {
        $this->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [
            'from.required' => 'Choose a From date.',
            'from.date_format' => 'Enter a valid From date.',
            'to.required' => 'Choose a To date.',
            'to.date_format' => 'Enter a valid To date.',
            'to.after_or_equal' => 'To must be on or after From.',
        ]);

        $this->appliedFrom = $this->from;
        $this->appliedTo = $this->to;
    }

    public function render()
    {
        $start = CarbonImmutable::parse($this->appliedFrom, self::TIMEZONE)->startOfDay();
        $end = CarbonImmutable::parse($this->appliedTo, self::TIMEZONE)->startOfDay()->addDay();

        // Verified runtime convention: Eloquent writes Lisbon wall-clock strings;
        // MariaDB TIMESTAMP uses a UTC session and returns those same strings.
        // Compare local clock values directly, not UTC-converted instants. SQLite
        // tests use the same strings. Revisit if timestamp storage is migrated.
        $query = Event::query()->toBase()
            ->where('events.created_at', '>=', $start->toDateTimeString())
            ->where('events.created_at', '<', $end->toDateTimeString());

        $summary = (clone $query)->selectRaw(
            'COUNT(*) AS entries, COUNT(DISTINCT DATE(events.created_at)) AS check_in_days, COUNT(DISTINCT life_area_id) AS life_areas'
        )->first();
        $overview = [
            'entries' => (int) $summary->entries,
            'check_in_days' => (int) $summary->check_in_days,
            'life_areas' => (int) $summary->life_areas,
        ];

        $typeCounts = (clone $query)->selectRaw('type, COUNT(*) AS entries')->groupBy('type')->pluck('entries', 'type');
        $distribution = [];
        foreach (['positive', 'neutral', 'negative'] as $type) {
            $count = (int) ($typeCounts[$type] ?? 0);
            $distribution[] = ['type' => $type, 'entries' => $count, 'share' => $this->share($count, $overview['entries'])];
        }

        $areaCounts = (clone $query)
            ->selectRaw('life_area_id, COUNT(*) AS entries')->groupBy('life_area_id');
        $lifeAreas = LifeArea::query()->toBase()
            ->leftJoinSub($areaCounts, 'area_counts', 'life_areas.id', '=', 'area_counts.life_area_id')
            ->selectRaw('life_areas.id, life_areas.name, COALESCE(area_counts.entries, 0) AS entries')
            ->orderByDesc('entries')->orderBy('life_areas.name')->orderBy('life_areas.id')
            ->get()->map(fn ($area) => [
                'id' => $area->id,
                'name' => $area->name,
                'entries' => (int) $area->entries,
                'share' => $this->share((int) $area->entries, $overview['entries']),
            ])->all();

        $positiveCounts = (clone $query)->where('events.type', 'positive')
            ->selectRaw('life_area_id, COUNT(*) AS entries')->groupBy('life_area_id');
        $radarAreas = LifeArea::query()->toBase()
            ->leftJoinSub($positiveCounts, 'positive_counts', 'life_areas.id', '=', 'positive_counts.life_area_id')
            ->selectRaw('life_areas.id, life_areas.name, COALESCE(positive_counts.entries, 0) AS entries')
            ->orderBy('life_areas.id')
            ->get()->map(fn ($area) => [
                'id' => $area->id,
                'name' => $area->name,
                'entries' => (int) $area->entries,
            ])->all();

        return view('livewire.stats', [
            'overview' => $overview,
            'periodLabel' => $start->format('j M Y').' – '.$end->subDay()->format('j M Y'),
            'distribution' => $distribution,
            'lifeAreas' => $lifeAreas,
            'radarAreas' => $radarAreas,
        ])->layout('components.layouts.app');
    }

    private function share(int $count, int $total): float
    {
        return $total === 0 ? 0.0 : round($count / $total * 100, 1);
    }
}
