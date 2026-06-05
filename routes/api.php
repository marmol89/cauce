<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Marmol89\Cauce\Http\Controllers\ApiController;

Route::get('/status', [ApiController::class, 'status']);
Route::get('/jobs', [ApiController::class, 'jobs']);
Route::get('/jobs/{id}', [ApiController::class, 'job'])->where('id', '[0-9A-HJKMNP-TV-Z]{26}');
Route::post('/jobs/{id}/retry', [ApiController::class, 'retry'])->middleware('can:mutateCauce')->where('id', '[0-9A-HJKMNP-TV-Z]{26}');
Route::delete('/jobs/{id}', [ApiController::class, 'delete'])->middleware('can:mutateCauce')->where('id', '[0-9A-HJKMNP-TV-Z]{26}');
Route::get('/failed', [ApiController::class, 'failed']);
Route::get('/metrics', [ApiController::class, 'metrics']);
