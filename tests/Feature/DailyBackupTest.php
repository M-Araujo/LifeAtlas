<?php

namespace Tests\Feature;

use App\Jobs\CreateBackup;
use App\Livewire\Dashboard;
use App\Models\Backup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class DailyBackupTest extends TestCase {
    use RefreshDatabase;

    public function test_dashboard_queues_a_backup_when_todays_backup_does_not_exist(): void {
        Queue::fake();

        Livewire::test(Dashboard::class);

        $backup = Backup::whereDate('backup_date', today())->firstOrFail();

        $this->assertSame(Backup::STATUS_PENDING, $backup->status);
        Queue::assertPushed(CreateBackup::class, fn(CreateBackup $job) => $job->backupId === $backup->id);
    }

    public function test_dashboard_does_not_queue_a_second_backup_after_todays_backup_completes(): void {
        Queue::fake();
        Backup::create([
            'backup_date' => today()->toDateString(),
            'status' => Backup::STATUS_COMPLETED,
            'path' => 'backups/lifeatlas.sql',
            'completed_at' => now(),
        ]);

        Livewire::test(Dashboard::class);

        Queue::assertNothingPushed();
    }

    public function test_dashboard_retries_todays_failed_backup(): void {
        Queue::fake();
        $backup = Backup::create([
            'backup_date' => today()->toDateString(),
            'status' => Backup::STATUS_FAILED,
            'error_message' => 'Previous attempt failed.',
            'completed_at' => now(),
        ]);

        Livewire::test(Dashboard::class);

        $backup->refresh();

        $this->assertSame(Backup::STATUS_PENDING, $backup->status);
        $this->assertNull($backup->error_message);
        Queue::assertPushed(CreateBackup::class, fn(CreateBackup $job) => $job->backupId === $backup->id);
    }
}
