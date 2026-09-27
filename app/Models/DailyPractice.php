<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class DailyPractice extends Model
{
    protected $fillable = ['practice_date', 'principle_id'];

    protected function casts(): array
    {
        return ['practice_date' => 'immutable_date'];
    }

    public function setPracticeDateAttribute($value): void
    {
        // Keep DATE storage identical in MariaDB and SQLite (no time suffix).
        $this->attributes['practice_date'] = CarbonImmutable::parse($value, 'Europe/Lisbon')->toDateString();
    }

    public function principle()
    {
        return $this->belongsTo(Principle::class);
    }

    public function improvements()
    {
        return $this->hasMany(DailyImprovement::class)->orderBy('created_at')->orderBy('id');
    }
}
