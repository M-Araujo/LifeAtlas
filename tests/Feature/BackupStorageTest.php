<?php

namespace Tests\Feature;

use App\Jobs\CreateBackup;
use App\Livewire\Dashboard;
use App\Models\Backup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
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
        if (! function_exists('posix_geteuid') || posix_geteuid() === 0) {
            $this->markTestSkipped('Permission denial requires a non-root Linux test user.');
        }
        for ($days = 0; $days <= 5; $days++) {
            $this->completedCopy($days);
        }
        chmod($this->backupRoot.'/backups', 0500);
        try {
            $this->prune();
        } finally {
            chmod($this->backupRoot.'/backups', 0700);
        }
        $this->assertSame(6, Backup::count());
        $this->assertFileExists($this->backupRoot.'/backups/old-5.sql');
    }

    public function test_retention_preserves_records_when_selected_mount_has_no_files(): void
    {
        for ($days = 0; $days <= 5; $days++) {
            Backup::create(['backup_date' => today()->subDays($days), 'status' => Backup::STATUS_COMPLETED, 'path' => 'backups/old-'.$days.'.sql']);
        }
        $this->prune();

        $this->assertSame(6, Backup::count());
    }

    public function test_retention_preserves_record_without_a_path(): void
    {
        for ($days = 0; $days <= 5; $days++) {
            Backup::create(['backup_date' => today()->subDays($days), 'status' => Backup::STATUS_COMPLETED]);
        }
        $this->prune();

        $this->assertSame(6, Backup::count());
    }

    private function completedCopy(int $days, string $contents = 'synthetic SQL'): Backup
    {
        $path = 'backups/old-'.$days.'.sql';
        file_put_contents($this->backupRoot.'/'.$path, $contents);

        return Backup::create(['backup_date' => today()->subDays($days), 'status' => Backup::STATUS_COMPLETED, 'path' => $path]);
    }

    private function prune(): void
    {
        (new ReflectionMethod(CreateBackup::class, 'pruneCompletedBackups'))
            ->invoke(new CreateBackup(1), Storage::disk('backups'));
    }

    public function test_retention_preserves_last_accessible_backup_after_mount_change(): void
    {
        for ($days = 0; $days < 5; $days++) {
            Backup::create(['backup_date' => today()->subDays($days), 'status' => Backup::STATUS_COMPLETED, 'path' => 'backups/missing-'.$days.'.sql']);
        }
        $available = $this->completedCopy(5);

        $this->prune();

        $this->assertFileExists($this->backupRoot.'/'.$available->path);
        $this->assertSame(6, Backup::count());
    }

    public function test_empty_files_and_duplicate_records_do_not_count_as_available_copies(): void
    {
        $this->completedCopy(0, '');
        $shared = $this->completedCopy(1);
        Backup::create(['backup_date' => today()->subDays(2), 'status' => Backup::STATUS_COMPLETED, 'path' => $shared->path]);
        for ($days = 3; $days <= 6; $days++) {
            $this->completedCopy($days);
        }

        $this->prune();

        $this->assertSame(7, Backup::count());
        $this->assertFileExists($this->backupRoot.'/backups/old-6.sql');
    }

    public static function unsafePaths(): array
    {
        return array_map(fn ($path) => [$path], [
            'unrelated.txt', '../unrelated.sql', 'backups/../unrelated.sql',
            '/tmp/unrelated.sql', 'C:/unrelated.sql', 'C:\\unrelated.sql',
            '//server/share/unrelated.sql', 'backups\\unrelated.sql',
            'backups/nested/unrelated.sql', 'backups//unrelated.sql',
            'backups/./unrelated.sql', 'backups/%2e%2e/unrelated.sql',
            'php://filter/resource=unrelated.sql', "backups/unrelated.sql\0", '', null,
        ]);
    }

    #[DataProvider('unsafePaths')]
    public function test_retention_rejects_unsafe_paths_without_deleting_files(?string $path): void
    {
        for ($days = 0; $days < 5; $days++) {
            $this->completedCopy($days);
        }
        $record = Backup::create(['backup_date' => today()->subDays(5), 'status' => Backup::STATUS_COMPLETED, 'path' => $path]);
        file_put_contents($this->backupRoot.'/unrelated.txt', 'synthetic unrelated file');
        file_put_contents($this->backupRoot.'/unrelated.sql', 'synthetic unrelated SQL');

        Log::spy();
        $this->prune();

        $this->assertSame(6, Backup::count());
        $this->assertNotNull($record->fresh());
        $this->assertSame('synthetic unrelated file', file_get_contents($this->backupRoot.'/unrelated.txt'));
        $this->assertSame('synthetic unrelated SQL', file_get_contents($this->backupRoot.'/unrelated.sql'));
        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    public function test_retention_rejects_symlink_and_hardlink_files(): void
    {
        file_put_contents($this->backupRoot.'/outside.sql', 'synthetic outside');
        if (! @symlink($this->backupRoot.'/outside.sql', $this->backupRoot.'/backups/link.sql')) {
            $this->markTestSkipped('Creating temporary symlinks is unavailable.');
        }
        $this->assertTrue(link($this->backupRoot.'/outside.sql', $this->backupRoot.'/backups/hardlink.sql'));
        for ($days = 0; $days < 5; $days++) {
            $this->completedCopy($days);
        }
        foreach (['link.sql', 'hardlink.sql'] as $index => $name) {
            Backup::create(['backup_date' => today()->subDays(5 + $index), 'status' => Backup::STATUS_COMPLETED, 'path' => 'backups/'.$name]);
        }

        $this->prune();

        $this->assertSame(7, Backup::count());
        $this->assertTrue(is_link($this->backupRoot.'/backups/link.sql'));
        $this->assertSame('synthetic outside', file_get_contents($this->backupRoot.'/outside.sql'));
        $this->assertFileExists($this->backupRoot.'/backups/hardlink.sql');
    }

    public function test_unreadable_file_does_not_count_as_an_available_copy(): void
    {
        if (! function_exists('posix_geteuid') || posix_geteuid() === 0) {
            $this->markTestSkipped('Permission denial requires a non-root Linux test user.');
        }
        for ($days = 0; $days <= 5; $days++) {
            $this->completedCopy($days);
        }
        $unreadable = $this->backupRoot.'/backups/old-0.sql';
        chmod($unreadable, 0000);
        try {
            $this->prune();
            $this->assertSame(6, Backup::count());
            $this->assertFileExists($this->backupRoot.'/backups/old-5.sql');
        } finally {
            chmod($unreadable, 0600);
        }
    }

    public function test_retention_rejects_symlinked_backup_directory(): void
    {
        mkdir($this->backupRoot.'/outside', 0700);
        file_put_contents($this->backupRoot.'/outside/old.sql', 'synthetic outside');
        rmdir($this->backupRoot.'/backups');
        if (! @symlink($this->backupRoot.'/outside', $this->backupRoot.'/backups')) {
            $this->markTestSkipped('Creating temporary symlinks is unavailable.');
        }
        try {
            $this->prune();
            $this->fail('A symlinked directory must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('without symlinks', $exception->getMessage());
            $this->assertSame('synthetic outside', file_get_contents($this->backupRoot.'/outside/old.sql'));
        } finally {
            unlink($this->backupRoot.'/backups');
        }
    }

    public function test_existing_destination_is_never_overwritten_and_foreign_temporary_file_is_preserved(): void
    {
        $backup = $this->pendingBackup();
        $name = 'lifeatlas-'.today()->format('Y-m-d').'-'.$backup->id.'.sql';
        file_put_contents($this->backupRoot.'/backups/'.$name, 'synthetic existing');
        file_put_contents($this->backupRoot.'/backups/.'.$name.'.tmp', 'synthetic foreign temporary');
        $this->fakeDump();

        (new CreateBackup($backup->id))->handle();

        $this->assertSame(Backup::STATUS_FAILED, $backup->fresh()->status);
        $this->assertSame('synthetic existing', file_get_contents($this->backupRoot.'/backups/'.$name));
        $this->assertSame('synthetic foreign temporary', file_get_contents($this->backupRoot.'/backups/.'.$name.'.tmp'));
        $this->assertCount(2, array_diff(scandir($this->backupRoot.'/backups'), ['.', '..']));
    }

    public function test_concurrent_publication_has_one_winner_without_overwriting(): void
    {
        $first = $this->backupRoot.'/backups/first.tmp';
        $second = $this->backupRoot.'/backups/second.tmp';
        $final = $this->backupRoot.'/backups/concurrent.sql';
        file_put_contents($first, 'first synthetic dump');
        file_put_contents($second, 'second synthetic dump');
        $publish = new ReflectionMethod(CreateBackup::class, 'publishBackup');
        $publish->invoke(new CreateBackup(1), $first, $final);
        try {
            $publish->invoke(new CreateBackup(2), $second, $final);
            $this->fail('Only one publisher may create the destination.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('destination occupied', $exception->getMessage());
        }
        $this->assertSame('first synthetic dump', file_get_contents($final));
        $this->assertSame('second synthetic dump', file_get_contents($second));
    }

    public function test_publication_does_not_follow_an_existing_destination_symlink(): void
    {
        $source = $this->backupRoot.'/backups/source.tmp';
        $outside = $this->backupRoot.'/outside.sql';
        $final = $this->backupRoot.'/backups/final.sql';
        file_put_contents($source, 'new synthetic dump');
        file_put_contents($outside, 'existing synthetic dump');
        if (! @symlink($outside, $final)) {
            $this->markTestSkipped('Creating temporary symlinks is unavailable.');
        }
        try {
            (new ReflectionMethod(CreateBackup::class, 'publishBackup'))->invoke(new CreateBackup(1), $source, $final);
            $this->fail('An existing symlink must block publication.');
        } catch (\RuntimeException $exception) {
            $this->assertTrue(is_link($final));
            $this->assertSame('existing synthetic dump', file_get_contents($outside));
        }
    }

    public function test_two_processes_competing_to_publish_never_overwrite_each_other(): void
    {
        if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
            $this->markTestSkipped('A two-process race test requires pcntl and Unix sockets.');
        }
        $first = $this->backupRoot.'/backups/first.tmp';
        $second = $this->backupRoot.'/backups/second.tmp';
        $final = $this->backupRoot.'/backups/race.sql';
        file_put_contents($first, 'first synthetic dump');
        file_put_contents($second, 'second synthetic dump');
        [$parent, $child] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid);
        if ($pid === 0) {
            fclose($parent);
            fread($child, 1);
            try {
                (new ReflectionMethod(CreateBackup::class, 'publishBackup'))->invoke(new CreateBackup(2), $second, $final);
                exit(0);
            } catch (\RuntimeException) {
                exit(2);
            }
        }
        fclose($child);
        fwrite($parent, 'g');
        $parentWon = true;
        try {
            (new ReflectionMethod(CreateBackup::class, 'publishBackup'))->invoke(new CreateBackup(1), $first, $final);
        } catch (\RuntimeException) {
            $parentWon = false;
        } finally {
            fclose($parent);
            pcntl_waitpid($pid, $status);
        }
        $this->assertTrue(pcntl_wifexited($status));
        $this->assertSame($parentWon ? 2 : 0, pcntl_wexitstatus($status));
        $this->assertSame($parentWon ? 'first synthetic dump' : 'second synthetic dump', file_get_contents($final));
        $this->assertSame('first synthetic dump', file_get_contents($first));
        $this->assertSame('second synthetic dump', file_get_contents($second));
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
