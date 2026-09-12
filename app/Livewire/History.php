<?php

namespace App\Livewire;

use App\Models\Event;
use Livewire\Component;
use App\Models\LifeArea;
use Carbon\Carbon;

class History extends Component {
    public $search = '';
    public $searchLifeArea = '';

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

        $lifeAreas = LifeArea::get();

        return view('livewire.history', [
            'events' => $events,
            'lifeAreas' => $lifeAreas,
        ])->layout('components.layouts.app');
    }
}
