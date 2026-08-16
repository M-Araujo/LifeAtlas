<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Dashboard;

Route::get('/', Dashboard::class)
    ->name('dashboard');


require __DIR__ . '/auth.php';
