<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Marmol89\Cauce\Http\Controllers\DashboardController;

Route::get('/', [DashboardController::class, 'index'])->name('cauce.dashboard');
Route::get('/jobs', [DashboardController::class, 'jobs'])->name('cauce.jobs');
Route::get('/jobs/{id}', [DashboardController::class, 'job'])->name('cauce.jobs.show');
Route::post('/jobs/{id}', [DashboardController::class, 'jobAction'])->middleware('can:mutateCauce')->name('cauce.jobs.action');
Route::get('/failed', [DashboardController::class, 'failed'])->name('cauce.failed');
Route::get('/metrics', [DashboardController::class, 'metrics'])->name('cauce.metrics');
Route::get('/queues', [DashboardController::class, 'queues'])->name('cauce.queues');
