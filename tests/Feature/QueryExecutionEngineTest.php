<?php

use App\Exceptions\Query\ReadOnlyViolationException;
use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\QueryHistory;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Query\Contracts\QueryExecutionEngineContract;

test('it executes query synchronously and records history and audit logs', function () {
    /** @var QueryExecutionEngineContract $engine */
    $engine = app(QueryExecutionEngineContract::class);

    $user = User::create([
        'name' => 'Data Analyst',
        'email' => 'analyst@data.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Analytics Workspace',
        'slug' => 'analytics-ws',
    ]);

    $dbFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test_sync_'.uniqid().'.sqlite';
    touch($dbFile);

    $connection = Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'Sales DB',
        'driver' => 'sqlite',
        'database_name' => $dbFile,
    ]);

    // Setup table in connection
    $engine->execute($connection, $user, 'CREATE TABLE customers (id INTEGER PRIMARY KEY, name TEXT, balance REAL);');
    $engine->execute($connection, $user, "INSERT INTO customers (name, balance) VALUES ('Company Alpha', 4500.50);");
    $engine->execute($connection, $user, "INSERT INTO customers (name, balance) VALUES ('Company Beta', 1200.00);");

    // Execute SELECT query
    $result = $engine->execute($connection, $user, 'SELECT * FROM customers WHERE balance > :min_balance', [
        'min_balance' => 2000.0,
    ]);

    expect($result->isSelect)->toBeTrue();
    expect($result->affectedRows)->toBe(1);
    expect($result->rows[0]['name'])->toBe('Company Alpha');

    // Verify QueryHistory logging
    $historyCount = QueryHistory::where('connection_id', $connection->id)->count();
    expect($historyCount)->toBe(4);

    // Verify AuditLog for DDL and DML operations
    $auditLogs = AuditLog::where('connection_id', $connection->id)->get();
    expect($auditLogs->count())->toBeGreaterThanOrEqual(3);
    $actions = $auditLogs->pluck('action')->all();
    expect($actions)->toContain('DDL_EXECUTE', 'DML_EXECUTE');

    if (file_exists($dbFile)) {
        unlink($dbFile);
    }
});

test('it blocks destructive operations on read-only connections', function () {
    /** @var QueryExecutionEngineContract $engine */
    $engine = app(QueryExecutionEngineContract::class);

    $user = User::create([
        'name' => 'Auditor',
        'email' => 'auditor@data.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Security Audit',
        'slug' => 'security-audit',
    ]);

    $readOnlyConnection = Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'Production Replica Read-Only',
        'driver' => 'sqlite',
        'database_name' => ':memory:',
        'is_read_only' => true,
    ]);

    expect(fn () => $engine->execute($readOnlyConnection, $user, 'DROP TABLE users;'))
        ->toThrow(ReadOnlyViolationException::class);

    expect(fn () => $engine->execute($readOnlyConnection, $user, 'DELETE FROM customers;'))
        ->toThrow(ReadOnlyViolationException::class);

    expect(fn () => $engine->execute($readOnlyConnection, $user, 'UPDATE accounts SET balance = 0;'))
        ->toThrow(ReadOnlyViolationException::class);
});

test('it streams query results in chunks with O(1) memory consumption', function () {
    /** @var QueryExecutionEngineContract $engine */
    $engine = app(QueryExecutionEngineContract::class);

    $user = User::create([
        'name' => 'Streaming Engineer',
        'email' => 'stream@data.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Streaming Hub',
        'slug' => 'streaming-hub',
    ]);

    $dbFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test_stream_'.uniqid().'.sqlite';
    touch($dbFile);

    $connection = Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'Telemetry DB',
        'driver' => 'sqlite',
        'database_name' => $dbFile,
    ]);

    $engine->execute($connection, $user, 'CREATE TABLE metrics (id INTEGER PRIMARY KEY, metric_key TEXT, value REAL);');

    for ($i = 1; $i <= 150; $i++) {
        $engine->execute($connection, $user, "INSERT INTO metrics (metric_key, value) VALUES ('cpu_usage_{$i}', {$i}.5);");
    }

    $events = [];
    $streamGenerator = $engine->stream($connection, $user, 'SELECT * FROM metrics ORDER BY id ASC', [], 50);

    foreach ($streamGenerator as $event) {
        $events[] = $event;
    }

    // Columns event + 3 chunks of 50 + Complete event = 5 events
    expect($events[0]['type'])->toBe('columns');
    expect($events[0]['data']['columns'])->toContain('id', 'metric_key', 'value');

    expect($events[1]['type'])->toBe('chunk');
    expect($events[1]['data']['rows'])->toHaveCount(50);

    expect($events[2]['type'])->toBe('chunk');
    expect($events[2]['data']['rows'])->toHaveCount(50);

    expect($events[3]['type'])->toBe('chunk');
    expect($events[3]['data']['rows'])->toHaveCount(50);

    expect($events[4]['type'])->toBe('complete');
    expect($events[4]['data']['total_rows'])->toBe(150);

    if (file_exists($dbFile)) {
        unlink($dbFile);
    }
});

test('it handles API execute and stream endpoints successfully', function () {
    $user = User::create([
        'name' => 'API Developer',
        'email' => 'api@client.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'API Workspace',
        'slug' => 'api-ws',
    ]);

    $dbFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test_api_'.uniqid().'.sqlite';
    touch($dbFile);

    $connection = Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'API Connection',
        'driver' => 'sqlite',
        'database_name' => $dbFile,
    ]);

    $this->actingAs($user);

    // Initial setup table
    $this->postJson(route('api.connections.query.execute', $connection->id), [
        'sql' => 'CREATE TABLE api_logs (id INTEGER PRIMARY KEY, endpoint TEXT);',
    ])->assertOk();

    // Insert row
    $this->postJson(route('api.connections.query.execute', $connection->id), [
        'sql' => "INSERT INTO api_logs (endpoint) VALUES ('/api/v1/test');",
    ])->assertOk();

    // Query rows
    $response = $this->postJson(route('api.connections.query.execute', $connection->id), [
        'sql' => 'SELECT * FROM api_logs;',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.affected_rows', 1);
    $response->assertJsonPath('data.rows.0.endpoint', '/api/v1/test');

    // Test SSE Stream endpoint
    $streamResponse = $this->post(route('api.connections.query.stream', $connection->id), [
        'sql' => 'SELECT * FROM api_logs;',
        'chunk_size' => 100,
    ]);

    $streamResponse->assertOk();
    $streamResponse->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

    if (file_exists($dbFile)) {
        unlink($dbFile);
    }
});
