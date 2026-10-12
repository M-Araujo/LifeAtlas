<?php

namespace Tests\Feature;

use App\Jobs\CreateBackup;
use App\Livewire\Dashboard;
use App\Models\Backup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use ReflectionMethod;
use Tests\Support\IsolatedBackupTestCase;

class BackupStorageTest extends IsolatedBackupTestCase
{
    use RefreshDatabase;

    private function pendingBackup(): Backup
    {
        return Backup::create(['backup_date' => today(), 'status' => Backup::STATUS_PENDING]);
    }

    private function fakeDump(bool $successful = true, string $contents = 'synthetic SQL'): void
    {
        config(['database.connections.dump_test' => [
            'driver' => 'mariadb', 'host' => 'unused', 'port' => 3306,
            'username' => 'unused', 'password' => '', 'database' => 'unused',
        ]]);
        // Keep Eloquent on SQLite while presenting fake connection metadata to the job.
        config(['database.default' => 'dump_test']);
        app('db')->extend('mariadb', fn () => app('db')->connection('sqlite'));
        Process::fake(function ($process) use ($successful, $contents) {
            foreach ($process->command as $argument) {
                if (str_starts_with($argument, '--result-file=')) {
                    $path = substr($argument, strlen('--result-file='));
                    $this->assertStringStartsWith($this->backupRoot.'/backups/', $path);
                    file_put_contents($path, $contents);
                }
            }

            return Process::result(exitCode: $successful ? 0 : 1);
        });
    }

    protected function tearDown(): void
    {
        config(['database.default' => 'sqlite']);
        parent::tearDown();
    }

    public function test_success_uses_dedicated_disk_and_preserves_relative_path(): void
    {
        $backup = $this->pendingBackup();
        $disk = Storage::build([
            'driver' => 'local', 'root' => $this->backupRoot,
        ]);
        Storage::shouldReceive('disk')->once()->with('backups')->andReturn($disk);
        $this->fakeDump();
        (new CreateBackup($backup->id))->handle();
        $backup->refresh();
        $path = 'backups/lifeatlas-'.today()->format('Y-m-d').'-'.$backup->id.'.sql';
        $this->assertSame(Backup::STATUS_COMPLETED, $backup->status);
        $this->assertSame($path, $backup->path);
        $this->assertSame('synthetic SQL', file_get_contents($this->backupRoot.'/'.$path));
        $this->assertFileDoesNotExist($this->backupRoot.'/backups/.'.basename($path).'.tmp');
    }

    public function test_missing_directory_fails_without_creating_it_or_dumping(): void
    {
        rmdir($this->backupRoot.'/backups');
        $backup = $this->pendingBackup();
        (new CreateBackup($backup->id))->handle();
        $this->assertSame(Backup::STATUS_FAILED, $backup->fresh()->status);
        $this->assertDirectoryDoesNotExist($this->backupRoot.'/backups');
        Process::assertNothingRan();
    }

    public function test_repository_root_is_rejected_without_accessing_files(): void
    {
        config(['filesystems.disks.backups.root' => base_path()]);
        $backup = $this->pendingBackup();
        Storage::shouldReceive('disk')->never();
        (new CreateBackup($backup->id))->handle();
        $this->assertSame(Backup::STATUS_FAILED, $backup->fresh()->status);
        Process::assertNothingRan();
    }

    public function test_missing_root_is_rejected_without_creating_it(): void
    {
        $root = $this->backupRoot.'/missing-root';
        config(['filesystems.disks.backups.root' => $root]);
        $backup = $this->pendingBackup();
        Storage::shouldReceive('disk')->never();
        (new CreateBackup($backup->id))->handle();
        $this->assertSame(Backup::STATUS_FAILED, $backup->fresh()->status);
        $this->assertDirectoryDoesNotExist($root);
        Process::assertNothingRan();
    }

    public function test_repository_subdirectory_is_rejected_before_disk_access(): void
    {
        config(['filesystems.disks.backups.root' => base_path('tests')]);
        $backup = $this->pendingBackup();
        Storage::shouldReceive('disk')->never();
        (new CreateBackup($backup->id))->handle();
        $this->assertSame(Backup::STATUS_FAILED, $backup->fresh()->status);
        Process::assertNothingRan();
    }

    public function test_symlinked_root_into_repository_is_rejected(): void
    {
        $link = $this->backupRoot.'/linked-root';
        if (! @symlink(base_path('tests'), $link)) {
            $this->markTestSkipped('Creating temporary symlinks is unavailable.');
        }
        try {
            config(['filesystems.disks.backups.root' => $link]);
            $backup = $this->pendingBackup();
            Storage::shouldReceive('disk')->never();
            (new CreateBackup($backup->id))->handle();
            $this->assertSame(Backup::STATUS_FAILED, $backup->fresh()->status);
            Process::assertNothingRan();
        } finally {
            unlink($link);
        }
    }

    public function test_symlinked_backup_directory_into_repository_is_rejected(): void
    {
        $link = $this->backupRoot.'/backups';
        rmdir($link);
        if (! @symlink(base_path('tests'), $link)) {
            $this->markTestSkipped('Creating temporary symlinks is unavailable.');
        }
        try {
            $backup = $this->pendingBackup();
            (new CreateBackup($backup->id))->handle();
            $this->assertSame(Backup::STATUS_FAILED, $backup->fresh()->status);
            Process::assertNothingRan();
        } finally {
            unlink($link);
        }
    }

    public function test_unwritable_host_destination_fails_without_dumping(): void
    {
        if (! function_exists('posix_geteuid') || posix_geteuid() === 0) {
            $this->markTestSkipped('Permission denial requires a non-root Linux test user.');
        }
        chmod($this->backupRoot.'/backups', 0500);
        try {
            $backup = $this->pendingBackup();
            (new CreateBackup($backup->id))->handle();
            $this->assertSame(Backup::STATUS_FAILED, $backup->fresh()->status);
            Process::assertNothingRan();
        } finally {
            chmod($this->backupRoot.'/backups', 0700);
        }
    }

    public function test_host_mode_does_not_change_directory_permissions(): void
    {
        $directory = $this->backupRoot.'/backups';
        chmod($directory, 0750);
        (new ReflectionMethod(CreateBackup::class, 'prepareBackupDirectory'))->invoke(new CreateBackup(1), $directory);
        clearstatcache(true, $directory);
        $this->assertSame(0750, fileperms($directory) & 0777);
    }

    public function test_invalid_permission_mode_fails_without_dumping(): void
    {
        config(['filesystems.disks.backups.permission_mode' => 'automatic']);
        $backup = $this->pendingBackup();
        (new CreateBackup($backup->id))->handle();
        $this->assertSame(Backup::STATUS_FAILED, $backup->fresh()->status);
        Process::assertNothingRan();
    }

    public function test_failed_dump_cleans_temporary_file_and_preserves_existing_file(): void
    {
        file_put_contents($this->backupRoot.'/backups/existing.sql', 'synthetic existing');
        $backup = $this->pendingBackup();
        $this->fakeDump(false);
        (new CreateBackup($backup->id))->handle();
        $this->assertSame(Backup::STATUS_FAILED, $backup->fresh()->status);
        $this->assertSame(['existing.sql'], array_values(array_diff(scandir($this->backupRoot.'/backups'), ['.', '..'])));
    }

    public function test_empty_dump_is_not_finalized(): void
    {
        $backup = $this->pendingBackup();
        $this->fakeDump(contents: '');
        (new CreateBackup($backup->id))->handle();
        $this->assertSame(Backup::STATUS_FAILED, $backup->fresh()->status);
        $this->assertSame([], array_values(array_diff(scandir($this->backupRoot.'/backups'), ['.', '..'])));
    }

    public function test_retention_keeps_newest_five_completed_backups(): void
    {
        for ($days = 1; $days <= 6; $days++) {
            $path = 'backups/old-'.$days.'.sql';
            Storage::disk('backups')->put($path, 'synthetic old');
            Backup::create(['backup_date' => today()->subDays($days), 'status' => Backup::STATUS_COMPLETED, 'path' => $path]);
        }
        $backup = $this->pendingBackup();
        $this->fakeDump();
        (new CreateBackup($backup->id))->handle();
        $this->assertSame(5, Backup::count());
        $this->assertFileExists($this->backupRoot.'/backups/old-4.sql');
        $this->assertFileDoesNotExist($this->backupRoot.'/backups/old-5.sql');
        $this->assertFileDoesNotExist($this->backupRoot.'/backups/old-6.sql');
    }

    public function test_retention_preserves_record_when_file_deletion_fails(): void
    {
        for ($days = 0; $days <= 5; $days++) {
            Backup::create(['backup_date' => today()->subDays($days), 'status' => Backup::STATUS_COMPLETED, 'path' => 'backups/old-'.$days.'.sql']);
        }
        $disk = Mockery::mock();
        $disk->shouldReceive('exists')->once()->with('backups/old-5.sql')->andReturn(true);
        $disk->shouldReceive('delete')->once()->with('backups/old-5.sql')->andReturn(false);
        (new ReflectionMethod(CreateBackup::class, 'pruneCompletedBackups'))->invoke(new CreateBackup(1), $disk);
        $this->assertSame(6, Backup::count());
    }

    public function test_dashboard_preserves_dispatch_duplicate_prevention_and_retry(): void
    {
        Queue::fake();
        Livewire::test(Dashboard::class);
        $backup = Backup::firstOrFail();
        Queue::assertPushed(CreateBackup::class, 1);
        Livewire::test(Dashboard::class);
        Queue::assertPushed(CreateBackup::class, 1);
        $backup->update(['status' => Backup::STATUS_FAILED]);
        Livewire::test(Dashboard::class);
        $this->assertSame(Backup::STATUS_PENDING, $backup->fresh()->status);
        Queue::assertPushed(CreateBackup::class, 2);
    }
}
