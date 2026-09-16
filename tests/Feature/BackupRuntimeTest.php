<?php

namespace Tests\Feature;

use App\Jobs\CreateBackup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class BackupRuntimeTest extends TestCase
{
    public function test_backup_directory_is_created_private_and_existing_files_are_untouched(): void
    {
        Storage::fake('local');
        $directory = Storage::disk('local')->path('backups');
        $method = new ReflectionMethod(CreateBackup::class, 'prepareBackupDirectory');
        $job = new CreateBackup(3);
        $method->invoke($job, $directory);
        $this->assertSame(0700, fileperms($directory) & 0777);
        file_put_contents($directory.'/existing.sql', 'existing backup');
        chmod($directory.'/existing.sql', 0600);
        chmod($directory, 0755);

        $method->invoke($job, $directory);

        clearstatcache();
        $this->assertSame(0700, fileperms($directory) & 0777);
        $this->assertSame(0600, fileperms($directory.'/existing.sql') & 0777);
        $this->assertSame('existing backup', file_get_contents($directory.'/existing.sql'));
    }

    public function test_dump_failure_logs_exit_code_and_only_safe_stderr_details(): void
    {
        config(['database.default' => 'mariadb', 'database.connections.mariadb' => [
            'driver' => 'mariadb', 'host' => 'private-host', 'port' => 3306,
            'username' => 'private-user', 'password' => 'secret-password',
            'database' => 'private-database',
        ]]);
        Process::fake([
            '*' => Process::result(errorOutput: "Can't create/write to file '/private/path' (Errcode: 13 \"Permission denied\") MYSQL_PWD=secret-password mysql://private-user:another-secret@private-host/private-database arbitrary-token", exitCode: 1),
        ]);
        Log::shouldReceive('error')->once()->with('LifeAtlas MariaDB dump command failed.', [
            'backup_id' => 3,
            'exit_code' => 1,
            'stderr' => "Can't create/write to file; Errcode: 13; Permission denied; [remaining stderr redacted]",
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The MariaDB dump command failed.');
        (new ReflectionMethod(CreateBackup::class, 'dumpDatabase'))->invoke(new CreateBackup(3), '/unused/fake-output');
    }

    public function test_unrecognized_stderr_is_not_logged_verbatim(): void
    {
        $result = (new ReflectionMethod(CreateBackup::class, 'sanitizeDumpError'))
            ->invoke(new CreateBackup(3), 'Unexpected failure: arbitrary-secret');

        $this->assertSame('[stderr redacted: no recognized diagnostic]', $result);
    }
}
