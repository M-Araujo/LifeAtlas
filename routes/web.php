<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Dashboard;
use App\Livewire\History;
use App\Livewire\Stats;

Route::get('/', Dashboard::class)
    ->name('dashboard');

Route::get('/history', History::class)
    ->name('history');

Route::get('/stats', Stats::class)
    ->name('stats');


require __DIR__ . '/auth.php';
