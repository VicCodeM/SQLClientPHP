<?php

use App\Http\Controllers\Api\AiAssistantController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ConnectionManagerController;
use App\Http\Controllers\Api\QueryExecutionController;
use App\Http\Controllers\Api\QueryHistoryController;
use App\Http\Controllers\Api\SavedQueryController;
use App\Http\Controllers\Api\SchemaDesignController;
use App\Http\Controllers\Api\SchemaExplorerController;
use App\Http\Controllers\Api\TableDataController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Connections Management
    Route::get('connections', [ConnectionManagerController::class, 'index'])->name('api.connections.index');
    Route::post('connections', [ConnectionManagerController::class, 'store'])->name('api.connections.store');
    Route::post('connections/test', [ConnectionManagerController::class, 'test'])->name('api.connections.test');
    Route::delete('connections/{connection}', [ConnectionManagerController::class, 'destroy'])->name('api.connections.destroy');

    // Schema Live Explorer
    Route::get('connections/{connection}/schema/tree', [SchemaExplorerController::class, 'tree'])->name('api.connections.schema.tree');
    Route::get('connections/{connection}/tables/{table}/ddl', [SchemaExplorerController::class, 'tableDdl'])->name('api.connections.table.ddl');
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

    // AI Assistant (Groq Cloud)
    Route::get('ai/models', [AiAssistantController::class, 'models'])->name('api.ai.models');
    Route::post('ai/text-to-sql/{connection?}', [AiAssistantController::class, 'textToSql'])->name('api.ai.text-to-sql');
    Route::post('ai/optimize/{connection?}', [AiAssistantController::class, 'optimize'])->name('api.ai.optimize');
    Route::post('ai/fix/{connection?}', [AiAssistantController::class, 'fix'])->name('api.ai.fix');
    Route::post('ai/chat', [AiAssistantController::class, 'chat'])->name('api.ai.chat');
});
