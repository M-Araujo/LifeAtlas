<?php

use App\Livewire\DailyPractice;
use App\Livewire\Dashboard;
use App\Livewire\History;
use App\Livewire\Stats;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)
    ->name('dashboard');

Route::get('/history', History::class)
    ->name('history');

Route::get('/stats', Stats::class)
    ->name('stats');

Route::get('/daily-practice', DailyPractice::class)
    ->name('daily-practice');
