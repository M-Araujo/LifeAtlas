<?php

namespace App\Livewire;

use App\Models\Event;
use Livewire\Component;
use App\Models\LifeArea;

class Stats extends Component {
    public function render() {
        $lifeAreas = LifeArea::with('events')->get();

        $stats = [
            'check_in_days' => Event::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->selectRaw('COUNT(DISTINCT DATE(created_at)) as count')
                ->value('count'),

            'total' => Event::count(),
            'life_areas' => LifeArea::count(),
            'positive' => Event::where('type', 'positive')->count(),
            'negative' => Event::where('type', 'negative')->count(),
            'neutral' => Event::where('type', 'neutral')->count(),
        ];

        return view('livewire.stats', [
            'lifeAreas' => $lifeAreas,
            'stats' => $stats,
        ])->layout('components.layouts.app');
    }
}
