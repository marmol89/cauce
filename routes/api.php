<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Marmol89\Cauce\Http\Controllers\ApiController;

Route::prefix('cauce/api')->middleware([
    \Marmol89\Cauce\Http\Middleware\Authorize::class,
    'api',
    'throttle:120,1',
])->group(function (): void {
    Route::get('/status', [ApiController::class, 'status']);
    Route::get('/jobs', [ApiController::class, 'jobs']);
    Route::get('/jobs/{id}', [ApiController::class, 'job']);
    Route::post('/jobs/{id}/retry', [ApiController::class, 'retry']);
    Route::delete('/jobs/{id}', [ApiController::class, 'delete']);
    Route::get('/failed', [ApiController::class, 'failed']);
    Route::get('/metrics', [ApiController::class, 'metrics']);
    Route::get('/health', [ApiController::class, 'health']);
});
