<?php

namespace App\Livewire;

use App\Jobs\CreateBackup;
use App\Models\Backup;
use App\Models\Event;
use App\Models\LifeArea;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    public $life_area_id;

    public $description;

    public $type = 'neutral';

    public function mount(): void
    {
        $backupId = $this->queueTodayBackup();

        if ($backupId) {
            CreateBackup::dispatch($backupId)->afterCommit();
        }
    }

    private function queueTodayBackup(): ?int
    {
        $backupDate = today()->toDateString();

        try {
            return DB::transaction(function () use ($backupDate) {
                $backup = Backup::whereDate('backup_date', $backupDate)
                    ->lockForUpdate()
                    ->first();

                if (! $backup) {
                    return Backup::create([
                        'backup_date' => $backupDate,
                        'status' => Backup::STATUS_PENDING,
                    ])->id;
                }

                if ($backup->status !== Backup::STATUS_FAILED) {
                    return null;
                }

                $backup->update([
                    'status' => Backup::STATUS_PENDING,
                    'path' => null,
                    'error_message' => null,
                    'started_at' => null,
                    'completed_at' => null,
                ]);

                return $backup->id;
            });
        } catch (QueryException $exception) {
            // Another Dashboard request may have created today's backup first.
            if (Backup::whereDate('backup_date', $backupDate)->exists()) {
                return null;
            }

            throw $exception;
        }
    }

    public function saveRecord()
    {
        $validated = $this->validate([
            'life_area_id' => 'required|exists:life_areas,id',
            'description' => 'required|string|max:10000',
            'type' => 'required|in:neutral,positive,negative',
        ]);

        Event::create($validated);

        $this->reset(['life_area_id', 'description']);
        $this->type = 'neutral';
    }

    public function render()
    {
        $lifeAreas = LifeArea::get();
        $thisWeek = Event::whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);

        $weeklyStats = [
            'positive' => (clone $thisWeek)->where('type', 'positive')->count(),
            'neutral' => (clone $thisWeek)->where('type', 'neutral')->count(),
            'negative' => (clone $thisWeek)->where('type', 'negative')->count(),
            'total' => $thisWeek->count(),
        ];

        return view('livewire.dashboard', [
            'lifeAreas' => $lifeAreas,
            'weeklyStats' => $weeklyStats,
        ])
            ->layout('components.layouts.app');
    }
}
