<?php

namespace App\Jobs;

use App\Models\Backup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CreateBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $backupId) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('lifeatlas-database-backup'))
                ->releaseAfter(10)
                ->expireAfter(3600),
        ];
    }

    public function handle(): void
    {
        $backup = Backup::find($this->backupId);

        if (! $backup || $backup->status !== Backup::STATUS_PENDING) {
            return;
        }

        $backup->update([
            'status' => Backup::STATUS_RUNNING,
            'started_at' => now(),
            'error_message' => null,
        ]);

        $disk = Storage::disk('local');
        $directory = 'backups';

        $filename = sprintf(
            'lifeatlas-%s-%d.sql',
            $backup->backup_date->format('Y-m-d'),
            $backup->id,
        );
        $temporaryPath = $directory.'/.'.$filename.'.tmp';
        $finalPath = $directory.'/'.$filename;
        $temporaryFile = $disk->path($temporaryPath);
        $finalFile = $disk->path($finalPath);

        try {
            $this->prepareBackupDirectory($disk->path($directory));

            $this->dumpDatabase($temporaryFile);

            if (! is_file($temporaryFile) || filesize($temporaryFile) === 0) {
                throw new RuntimeException('The database dump did not create a usable file.');
            }

            if (! rename($temporaryFile, $finalFile)) {
                throw new RuntimeException('The temporary backup could not be finalized.');
            }

            $backup->update([
                'status' => Backup::STATUS_COMPLETED,
                'path' => $finalPath,
                'completed_at' => now(),
                'error_message' => null,
            ]);

            try {
                $this->pruneCompletedBackups($disk);
            } catch (Throwable $exception) {
                Log::error('LifeAtlas backup retention failed.', [
                    'backup_id' => $backup->id,
                    'exception' => $exception,
                ]);
            }
        } catch (Throwable $exception) {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }

            $backup->update([
                'status' => Backup::STATUS_FAILED,
                'error_message' => 'Backup failed. Your existing backups were not changed.',
                'completed_at' => now(),
            ]);

            Log::error('LifeAtlas database backup failed.', [
                'backup_id' => $backup->id,
                'exception' => $exception,
            ]);
        }
    }

    private function prepareBackupDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('The private backup directory could not be created.');
        }

        if (! @chmod($directory, 0700)) {
            throw new RuntimeException('The backup directory must be owned by the queue worker user with permissions 0700.');
        }

        clearstatcache(true, $directory);

        if (! is_writable($directory)) {
            throw new RuntimeException('The private backup directory is not writable by the queue worker user.');
        }
    }

    private function dumpDatabase(string $temporaryFile): void
    {
        $connection = config('database.connections.'.config('database.default'));

        if (! in_array($connection['driver'] ?? null, ['mariadb', 'mysql'], true)) {
            throw new RuntimeException('The configured database connection is not MariaDB or MySQL.');
        }

        $command = [
            'mariadb-dump',
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--user='.$connection['username'],
            '--single-transaction',
            '--routines',
            '--events',
            '--skip-lock-tables',
            '--result-file='.$temporaryFile,
            $connection['database'],
        ];

        if (! empty($connection['unix_socket'])) {
            $command[] = '--socket='.$connection['unix_socket'];
        }

        $result = Process::timeout(600)
            ->env(['MYSQL_PWD' => (string) $connection['password']])
            ->run($command);

        if (! $result->successful()) {
            Log::error('LifeAtlas MariaDB dump command failed.', [
                'backup_id' => $this->backupId,
                'exit_code' => $result->exitCode(),
                'stderr' => $this->sanitizeDumpError($result->errorOutput()),
            ]);

            throw new RuntimeException('The MariaDB dump command failed.');
        }
    }

    private function sanitizeDumpError(string $stderr): string
    {
        // Keep only recognized diagnostic phrases and numeric error codes.
        // Raw stderr can contain credentials, connection URLs, or database contents.
        preg_match_all('/\b(?:Permission denied|Access denied|No such file or directory|No space left on device|Read-only file system|Unknown database|Unknown server host|Connection refused|Lost connection|Server has gone away|Can\x27t connect|Can\x27t create\/write to file|Errcode: ?[0-9]+|Got error: ?[0-9]+)\b/i', $stderr, $matches);

        return $matches[0]
            ? implode('; ', array_unique($matches[0])).'; [remaining stderr redacted]'
            : '[stderr redacted: no recognized diagnostic]';
    }

    private function pruneCompletedBackups($disk): void
    {
        Backup::where('status', Backup::STATUS_COMPLETED)
            ->orderByDesc('backup_date')
            ->get()
            ->slice(5)
            ->each(function (Backup $backup) use ($disk) {
                if ($backup->path && $disk->exists($backup->path) && ! $disk->delete($backup->path)) {
                    return;
                }

                $backup->delete();
            });
    }
}
