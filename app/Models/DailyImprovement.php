<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyImprovement extends Model
{
    public const STATUSES = [
        'identified' => 'Identified',
        'working_on_it' => 'Working on it',
        'improving' => 'Improving',
        'resolved' => 'Resolved',
    ];

    protected $fillable = ['issue', 'solution', 'status', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'immutable_datetime'];
    }

    public function practice()
    {
        return $this->belongsTo(DailyPractice::class, 'daily_practice_id');
    }

    public function applyStatus(string $status): void
    {
        $this->resolved_at = $status === 'resolved'
            ? ($this->resolved_at ?? now('Europe/Lisbon')) : null;
        $this->status = $status;
    }
}
