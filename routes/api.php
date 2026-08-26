<?php

use App\Http\Controllers\Api\QueryExecutionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('connections/{connection}/query/execute', [QueryExecutionController::class, 'execute'])
        ->name('api.connections.query.execute');

    Route::post('connections/{connection}/query/stream', [QueryExecutionController::class, 'stream'])
        ->name('api.connections.query.stream');
});
