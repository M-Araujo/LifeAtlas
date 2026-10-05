<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Principle extends Model
{
    protected $fillable = ['text', 'source_reference', 'source_url', 'verified_at', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function practices()
    {
        return $this->hasMany(DailyPractice::class);
    }
}
