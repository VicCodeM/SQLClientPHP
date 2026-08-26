<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\QueryExecutionController;
use App\Http\Controllers\Api\QueryHistoryController;
use App\Http\Controllers\Api\SavedQueryController;
use App\Http\Controllers\Api\SchemaDesignController;
use App\Http\Controllers\Api\TableDataController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Execution & Streaming
    Route::post('connections/{connection}/query/execute', [QueryExecutionController::class, 'execute'])
        ->name('api.connections.query.execute');

    Route::post('connections/{connection}/query/stream', [QueryExecutionController::class, 'stream'])
        ->name('api.connections.query.stream');

    // Table Data & CRUD
    Route::post('connections/{connection}/table/data', [TableDataController::class, 'data'])
        ->name('api.connections.table.data');

    Route::post('connections/{connection}/table/row/update', [TableDataController::class, 'updateRow'])
        ->name('api.connections.table.row.update');

    Route::post('connections/{connection}/table/row/insert', [TableDataController::class, 'insertRow'])
        ->name('api.connections.table.row.insert');

    Route::post('connections/{connection}/table/row/delete', [TableDataController::class, 'deleteRow'])
        ->name('api.connections.table.row.delete');

    // Schema Design & ERD
    Route::get('connections/{connection}/schema/erd', [SchemaDesignController::class, 'erd'])
        ->name('api.connections.schema.erd');

    Route::post('connections/{connection}/table/create', [SchemaDesignController::class, 'createTable'])
        ->name('api.connections.table.create');

    // Query History
    Route::get('history', [QueryHistoryController::class, 'index'])->name('api.history.index');
    Route::delete('history/clear', [QueryHistoryController::class, 'clear'])->name('api.history.clear');

    // Saved Queries / Snippets
    Route::get('snippets', [SavedQueryController::class, 'index'])->name('api.snippets.index');
    Route::post('snippets', [SavedQueryController::class, 'store'])->name('api.snippets.store');
    Route::put('snippets/{savedQuery}', [SavedQueryController::class, 'update'])->name('api.snippets.update');
    Route::delete('snippets/{savedQuery}', [SavedQueryController::class, 'destroy'])->name('api.snippets.destroy');

    // Audit Logs
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('api.audit.index');
});
