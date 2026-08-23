<?php

namespace App\Livewire;

use App\Models\Event;
use Livewire\Component;
use App\Models\LifeArea;

class Dashboard extends Component {
    public $life_area_id;
    public $description;
    public $type = 'neutral';

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

        $events = Event::with('lifeArea')->orderBy('created_at', 'desc')->limit(6)->get();

        $lifeAreas = LifeArea::with('events')->get();

        return view('livewire.dashboard', [
            'events' => $events,
            'lifeAreas' => $lifeAreas
        ])
            ->layout('components.layouts.app');
    }
}
