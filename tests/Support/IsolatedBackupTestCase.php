<?php

namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class IsolatedBackupTestCase extends TestCase
{
    protected string $backupRoot;

    private array $originalEnvironment = [];

    public function createApplication()
    {
        // Never load the real .env or its cached production configuration.
        $this->backupRoot = sys_get_temp_dir().'/lifeatlas-backup-test-'.bin2hex(random_bytes(12));
        mkdir($this->backupRoot.'/backups', 0700, true);
        file_put_contents($this->backupRoot.'/.env.isolated-backup-tests-unused', '');
        foreach ([
            'APP_CONFIG_CACHE' => $this->backupRoot.'/unused-config.php',
            'APP_ENV' => 'testing',
            'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'LOG_CHANNEL' => 'null',
            'BACKUP_STORAGE_ROOT' => $this->backupRoot,
            'BACKUP_PERMISSION_MODE' => 'host',
        ] as $key => $value) {
            $this->originalEnvironment[$key] ??= [
                'process' => getenv($key),
                'env_exists' => array_key_exists($key, $_ENV),
                'env' => $_ENV[$key] ?? null,
                'server_exists' => array_key_exists($key, $_SERVER),
                'server' => $_SERVER[$key] ?? null,
            ];
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->useEnvironmentPath($this->backupRoot);
        $app->loadEnvironmentFrom('.env.isolated-backup-tests-unused');
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config([
            'filesystems.disks.backups.root' => $this->backupRoot,
            'filesystems.disks.backups.permission_mode' => 'host',
            'logging.default' => 'null',
        ]);
        Storage::forgetDisk('backups');
        Process::fake();
        Process::preventStrayProcesses();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            try {
                if (isset($this->backupRoot)) {
                    (new Filesystem)->deleteDirectory($this->backupRoot);
                }
            } finally {
                foreach ($this->originalEnvironment as $key => $original) {
                    putenv($original['process'] === false ? $key : $key.'='.$original['process']);
                    if ($original['env_exists']) {
                        $_ENV[$key] = $original['env'];
                    } else {
                        unset($_ENV[$key]);
                    }
                    if ($original['server_exists']) {
                        $_SERVER[$key] = $original['server'];
                    } else {
                        unset($_SERVER[$key]);
                    }
                }
                $this->originalEnvironment = [];
            }
        }
    }
}
