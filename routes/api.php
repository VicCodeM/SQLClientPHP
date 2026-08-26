<?php

use App\Http\Controllers\Api\QueryExecutionController;
use App\Http\Controllers\Api\TableDataController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('connections/{connection}/query/execute', [QueryExecutionController::class, 'execute'])
        ->name('api.connections.query.execute');

    Route::post('connections/{connection}/query/stream', [QueryExecutionController::class, 'stream'])
        ->name('api.connections.query.stream');

    Route::post('connections/{connection}/table/data', [TableDataController::class, 'data'])
        ->name('api.connections.table.data');

    Route::post('connections/{connection}/table/row/update', [TableDataController::class, 'updateRow'])
        ->name('api.connections.table.row.update');

    Route::post('connections/{connection}/table/row/insert', [TableDataController::class, 'insertRow'])
        ->name('api.connections.table.row.insert');

    Route::post('connections/{connection}/table/row/delete', [TableDataController::class, 'deleteRow'])
        ->name('api.connections.table.row.delete');
});
