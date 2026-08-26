<?php

use App\Http\Controllers\StudioController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StudioController::class, 'index'])->name('studio.index');
Route::get('/studio', [StudioController::class, 'index'])->name('studio.home');
