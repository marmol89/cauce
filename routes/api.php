<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Marmol89\Cauce\Http\Controllers\ApiController;

Route::get('/status', [ApiController::class, 'status']);
Route::get('/jobs', [ApiController::class, 'jobs']);
Route::get('/jobs/{id}', [ApiController::class, 'job']);
Route::post('/jobs/{id}/retry', [ApiController::class, 'retry'])->middleware('can:mutateCauce');
Route::delete('/jobs/{id}', [ApiController::class, 'delete'])->middleware('can:mutateCauce');
Route::get('/failed', [ApiController::class, 'failed']);
Route::get('/metrics', [ApiController::class, 'metrics']);
Route::get('/health', [ApiController::class, 'health']);
