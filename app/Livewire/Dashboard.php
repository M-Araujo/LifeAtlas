<?php

namespace App\Livewire;

use App\Models\Event;
use Livewire\Component;
use App\Models\LifeArea;

class Dashboard extends Component {
    public $life_area_id;
    public $description;
    public $type = 'neutral';
    public $editingEvent = null;
    public $editingEventId = null;
    public $deletingEventId = null;
    public $deletingEventDescription = null;

    public function saveRecord() {
        $validated = $this->validate([
            'life_area_id' => 'required|exists:life_areas,id',
            'description' => 'required|string|max:10000',
            'type' => 'required|in:neutral,positive,negative',
        ]);

        Event::create($validated);

        $this->reset(['life_area_id', 'description']);
        $this->type = 'neutral';
    }

    public function render() {
        $lifeAreas = LifeArea::get();

        return view('livewire.dashboard', [
            'lifeAreas' => $lifeAreas,
        ])
            ->layout('components.layouts.app');
    }

    public function editEvent(Event $event) {
        $this->editingEventId = $event->id;

        $this->life_area_id = $event->life_area_id;
        $this->description = $event->description;
        $this->type = $event->type;

        $this->dispatch('open-modal', 'edit-entry');
    }

    public function updateEvent() {
        $validated = $this->validate([
            'life_area_id' => 'required|exists:life_areas,id',
            'description' => 'required|string|max:10000',
            'type' => 'required|in:neutral,positive,negative',
        ]);

        $event = Event::findOrFail($this->editingEventId);

        $event->update($validated);

        $this->reset(['life_area_id', 'description', 'editingEventId']);
        $this->type = 'neutral';

        $this->dispatch('close-modal', 'edit-entry');
    }

    public function confirmDelete(Event $event) {
        $this->deletingEventId = $event->id;
        $this->deletingEventDescription = $event->description;
        $this->dispatch('open-modal', 'delete-entry');
    }

    public function deleteEvent() {
        $event = Event::findOrFail($this->deletingEventId);

        $event->delete();

        $this->reset(['deletingEventId', 'deletingEventDescription']);

        $this->dispatch('close-modal', 'delete-entry');
    }
}
