<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'backup_date',
        'status',
        'path',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'backup_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING], true);
    }
}
