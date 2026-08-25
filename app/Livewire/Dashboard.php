<?php

namespace App\Livewire;

use App\Models\Event;
use Livewire\Component;
use App\Models\LifeArea;
use Carbon\Carbon;

class Dashboard extends Component {
    public $life_area_id;
    public $description;
    public $type = 'neutral';
    public $search = '';
    public $searchLifeArea = '';

    public function saveRecord() {
        $validated = $this->validate([
            'life_area_id' => 'required|exists:life_areas,id',
            'description' => 'required|string|max:1000',
            'type' => 'required|in:neutral,positive,negative',
        ]);

        Event::create($validated);

        $this->reset(['life_area_id', 'description']);
        $this->type = 'neutral';
    }

    public function render() {

        $events = Event::with('lifeArea')
            ->when($this->search, function ($query) {
                $query->where('description', 'like', '%' . $this->search . '%');
            })
            ->when($this->searchLifeArea, function ($query) {
                $query->where('life_area_id', $this->searchLifeArea);
            })
            ->orderBy('created_at', 'desc')
            ->get();

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

        return view('livewire.dashboard', [
            'events' => $events,
            'lifeAreas' => $lifeAreas,
            'stats' => $stats
        ])
            ->layout('components.layouts.app');
    }
}
