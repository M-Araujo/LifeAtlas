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

        $claimed = Backup::whereKey($backup->id)->where('status', Backup::STATUS_PENDING)->update([
            'status' => Backup::STATUS_RUNNING,
            'started_at' => now(),
            'error_message' => null,
        ]);
        if (! $claimed) {
            return;
        }

        $directory = 'backups';

        $filename = sprintf(
            'lifeatlas-%s-%d.sql',
            $backup->backup_date->format('Y-m-d'),
            $backup->id,
        );
        $finalPath = $directory.'/'.$filename;
        $temporaryFile = null;
        $temporaryIdentity = null;

        try {
            $root = config('filesystems.disks.backups.root');
            $resolvedRoot = is_string($root) ? realpath($root) : false;
            $repository = realpath(base_path());

            if (! $resolvedRoot || ! $repository || $resolvedRoot === $repository
                || str_starts_with($resolvedRoot, $repository.DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('The backup storage root must exist outside the repository.');
            }

            $disk = Storage::disk('backups');
            $resolvedDirectory = $this->authorizedDirectory($disk);
            $this->prepareBackupDirectory($resolvedDirectory);
            $finalFile = $resolvedDirectory.'/'.$filename;

            // Reserve a private, unpredictable temporary name exclusively. Never
            // reuse another invocation's temporary file, including after a retry.
            $candidate = $resolvedDirectory.'/.'.$filename.'.'.bin2hex(random_bytes(16)).'.tmp';
            $handle = @fopen($candidate, 'x+b');
            if ($handle === false) {
                throw new RuntimeException('An exclusive temporary backup file could not be created.');
            }
            $temporaryIdentity = fstat($handle);
            fclose($handle);
            $temporaryFile = $candidate;

            $this->dumpDatabase($temporaryFile);

            $temporaryStat = $this->regularFile($temporaryFile);
            if (! $temporaryStat || ! $this->sameFile($temporaryIdentity, $temporaryStat)) {
                throw new RuntimeException('The database dump did not create a usable file.');
            }

            if ($this->authorizedDirectory($disk) !== $resolvedDirectory) {
                throw new RuntimeException('The backup directory changed during the dump.');
            }

            $this->publishBackup($temporaryFile, $finalFile);
            $this->removeOwnedTemporaryFile($temporaryFile, $temporaryIdentity);
            $temporaryFile = null;

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
            if ($temporaryFile !== null && $temporaryIdentity !== null) {
                $this->removeOwnedTemporaryFile($temporaryFile, $temporaryIdentity);
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

    private function authorizedDirectory($disk): string
    {
        $root = realpath($disk->path(''));
        $path = $disk->path('backups');
        clearstatcache(true, $path);
        $resolved = realpath($path);
        $repository = realpath(base_path());

        if (! $root || ! $resolved || ! $repository || is_link($path)
            || $resolved !== $root.DIRECTORY_SEPARATOR.'backups'
            || $resolved === $repository || str_starts_with($resolved, $repository.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The authorized backup directory must exist outside the repository without symlinks.');
        }

        return $resolved;
    }

    private function regularFile(string $path): ?array
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);

        return $stat && ($stat['mode'] & 0170000) === 0100000
            && $stat['size'] > 0 && $stat['nlink'] === 1 && is_readable($path) ? $stat : null;
    }

    private function sameFile(array $before, array $after): bool
    {
        return $before['dev'] === $after['dev'] && $before['ino'] === $after['ino'];
    }

    private function publishBackup(string $temporaryFile, string $finalFile): void
    {
        // link() publishes a complete file atomically and fails if any entry
        // already occupies the destination. Never fall back to rename/copy.
        if (! @link($temporaryFile, $finalFile)) {
            throw new RuntimeException('Backup publication failed: destination occupied or filesystem does not support hard links. Existing files were preserved.');
        }
    }

    private function removeOwnedTemporaryFile(string $path, array $identity): void
    {
        if (is_link(dirname($path)) || realpath(dirname($path)) !== dirname($path)) {
            Log::warning('LifeAtlas backup temporary cleanup skipped an unsafe directory.', ['backup_id' => $this->backupId]);

            return;
        }
        clearstatcache(true, $path);
        $current = @lstat($path);
        if ($current && ($current['mode'] & 0170000) === 0100000 && $this->sameFile($identity, $current)) {
            if (! @unlink($path)) {
                Log::warning('LifeAtlas backup temporary cleanup failed.', ['backup_id' => $this->backupId]);
            }
        } else {
            Log::warning('LifeAtlas backup temporary cleanup skipped a changed file.', ['backup_id' => $this->backupId]);
        }
    }

    private function prepareBackupDirectory(string $directory): void
    {
        $mode = config('filesystems.disks.backups.permission_mode');

        if (! in_array($mode, ['host', 'posix'], true)) {
            throw new RuntimeException('The backup permission mode must be host or posix.');
        }

        if (! is_dir($directory)) {
            throw new RuntimeException('The external backup directory must already exist.');
        }

        if ($mode === 'posix' && ! @chmod($directory, 0700)) {
            throw new RuntimeException('The backup directory must be owned by the queue worker user with permissions 0700.');
        }

        clearstatcache(true, $directory);

        if ($mode === 'posix' && (fileperms($directory) & 0777) !== 0700) {
            throw new RuntimeException('The backup filesystem did not enforce directory permissions 0700. Use host mode with restricted host access on Windows mounts.');
        }

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
        $directory = $this->authorizedDirectory($disk);
        $available = [];
        $seen = [];

        foreach (Backup::where('status', Backup::STATUS_COMPLETED)
            ->orderByDesc('backup_date')
            ->get() as $backup) {
            // Only direct SQL children are eligible. Never normalize untrusted
            // database paths: reject absolute paths, traversal and separators.
            if (! is_string($backup->path)
                || ! preg_match('/\Abackups\/[A-Za-z0-9][A-Za-z0-9._-]*\.sql\z/D', $backup->path)) {
                Log::warning('LifeAtlas backup retention skipped an invalid path.', ['backup_id' => $backup->id]);

                continue;
            }

            $path = $directory.'/'.substr($backup->path, strlen('backups/'));
            $stat = $this->regularFile($path);
            if (! $stat || realpath($path) !== $path) {
                Log::warning('LifeAtlas backup retention skipped an unavailable or unsafe file.', ['backup_id' => $backup->id]);

                continue;
            }
            // Duplicate records must not count one file as multiple copies.
            $identity = $stat['dev'].':'.$stat['ino'];
            if (isset($seen[$identity])) {
                Log::warning('LifeAtlas backup retention skipped a duplicate file.', ['backup_id' => $backup->id]);

                continue;
            }
            $seen[$identity] = true;
            $available[] = [$backup, $path, $stat];
        }

        foreach (array_slice($available, 5) as [$backup, $path, $stat]) {
            // Revalidate immediately before deletion and stop on any ambiguity.
            // The host must restrict concurrent directory changes to trusted users.
            if ($this->authorizedDirectory($disk) !== $directory) {
                throw new RuntimeException('The backup directory changed during retention.');
            }
            $current = $this->regularFile($path);
            $protected = array_slice($available, 0, 5);
            foreach ($protected as [, $protectedPath, $protectedStat]) {
                $present = $this->regularFile($protectedPath);
                if (! $present || ! $this->sameFile($protectedStat, $present)) {
                    Log::warning('LifeAtlas backup retention stopped because a retained file changed.');

                    return;
                }
            }
            if (! $current || ! $this->sameFile($stat, $current) || ! @unlink($path)) {
                Log::warning('LifeAtlas backup retention preserved an uncertain record.', ['backup_id' => $backup->id]);

                continue;
            }

            $backup->delete();
        }
    }
}
