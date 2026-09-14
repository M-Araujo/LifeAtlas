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
            $disk->makeDirectory($directory);

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
            throw new RuntimeException('The MariaDB dump command failed.');
        }
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
