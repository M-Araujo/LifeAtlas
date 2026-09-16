<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\LifeArea;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class History extends Component
{
    use WithPagination;

    public string $dateRange = '6_months';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSearchLifeArea(): void
    {
        $this->resetPage();
    }

    public function updatedDateRange(): void
    {
        $this->resetPage();
    }

    public $search = '';

    public $searchLifeArea = '';

    #[Locked]
    public ?int $editingEventId = null;

    #[Locked]
    public ?int $deletingEventId = null;

    public $life_area_id = '';

    public string $description = '';

    public string $type = 'neutral';

    public string $deletingEventDescription = '';

    public string $deletingEventDate = '';

    public string $deletingEventLifeArea = '';

    public string $message = '';

    public function editEvent(int $id): void
    {
        $this->cancelEdit();
        $this->message = '';
        $event = Event::find($id);
        if (! $event) {
            $this->message = 'This entry no longer exists.';

            return;
        }
        $this->editingEventId = $event->id;
        $this->life_area_id = $event->life_area_id;
        $this->description = $event->description;
        $this->type = $event->type;
        $this->dispatch('open-modal', 'edit-entry');
    }

    public function updateEvent(): void
    {
        if ($this->editingEventId === null) {
            return;
        }
        $event = Event::find($this->editingEventId);
        if (! $event) {
            $this->cancelEdit();
            $this->message = 'This entry no longer exists.';

            return;
        }
        $event->update($this->validate([
            'life_area_id' => 'required|exists:life_areas,id',
            'description' => 'required|string|max:10000',
            'type' => 'required|in:neutral,positive,negative',
        ]));
        $this->cancelEdit();
        $this->message = 'Entry updated.';
    }

    public function cancelEdit(): void
    {
        $this->reset('editingEventId', 'life_area_id', 'description', 'type');
        $this->resetValidation();
        $this->dispatch('close-modal', 'edit-entry');
    }

    public function confirmDelete(int $id): void
    {
        $this->cancelDelete();
        $this->message = '';
        $event = Event::with('lifeArea')->find($id);
        if (! $event) {
            $this->message = 'This entry no longer exists.';

            return;
        }
        $this->deletingEventId = $event->id;
        $this->deletingEventDescription = $event->description;
        $this->deletingEventDate = $event->created_at->format('d M Y');
        $this->deletingEventLifeArea = $event->lifeArea->name;
        $this->dispatch('open-modal', 'delete-entry');
    }

    public function deleteEvent(): void
    {
        if ($this->deletingEventId === null) {
            return;
        }
        $event = Event::find($this->deletingEventId);
        $event?->delete();
        $this->cancelDelete();
        $this->message = $event ? 'Entry deleted.' : 'This entry no longer exists.';
    }

    public function cancelDelete(): void
    {
        $this->reset('deletingEventId', 'deletingEventDescription', 'deletingEventDate', 'deletingEventLifeArea');
        $this->dispatch('close-modal', 'delete-entry');
    }

    public function render()
    {
        if (! in_array($this->dateRange, ['30_days', '6_months', '1_year', 'all'], true)) {
            $this->dateRange = '6_months';
        }

        $start = match ($this->dateRange) {
            '30_days' => today()->subDays(29),
            '1_year' => today()->subYearNoOverflow(),
            'all' => null,
            default => today()->subMonthsNoOverflow(6),
        };

        $query = Event::with('lifeArea')
            ->when($start, fn ($query) => $query->where('created_at', '>=', $start)
                ->where('created_at', '<', today()->addDay()))
            ->when($this->search, function ($query) {
                $query->where('description', 'like', '%'.$this->search.'%');
            })
            ->when($this->searchLifeArea, function ($query) {
                $query->where('life_area_id', $this->searchLifeArea);
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        $events = $query->paginate(15);

        // A deletion or edit may remove the final result on the current page.
        if ($events->currentPage() > $events->lastPage()) {
            $this->setPage($events->lastPage());
            $events = $query->paginate(15);
        }

        $lifeAreas = LifeArea::get();

        return view('livewire.history', [
            'events' => $events,
            'lifeAreas' => $lifeAreas,
        ])->layout('components.layouts.app');
    }
}
