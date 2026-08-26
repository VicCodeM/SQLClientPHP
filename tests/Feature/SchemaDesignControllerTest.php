<?php

use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Query\Contracts\QueryExecutionEngineContract;

test('it generates ERD graph data with nodes and edges for schema', function () {
    $user = User::create([
        'name' => 'ERD Architect',
        'email' => 'erd@data.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'ERD Workspace',
        'slug' => 'erd-ws',
    ]);

    $dbFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test_erd_'.uniqid().'.sqlite';
    touch($dbFile);

    $connection = Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'Relational DB',
        'driver' => 'sqlite',
        'database_name' => $dbFile,
    ]);

    /** @var QueryExecutionEngineContract $engine */
    $engine = app(QueryExecutionEngineContract::class);
    $engine->execute($connection, $user, 'CREATE TABLE departments (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL);');
    $engine->execute($connection, $user, 'CREATE TABLE employees (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        dept_id INTEGER NOT NULL,
        full_name TEXT NOT NULL,
        FOREIGN KEY (dept_id) REFERENCES departments(id) ON DELETE CASCADE
    );');

    $this->actingAs($user);

    $response = $this->getJson(route('api.connections.schema.erd', $connection->id).'?schema=main');

    $response->assertOk();
    $response->assertJsonPath('success', true);

    $nodes = $response->json('data.nodes');
    expect($nodes)->toHaveCount(2);

    $tableNames = array_column($nodes, 'name');
    expect($tableNames)->toContain('departments', 'employees');

    $edges = $response->json('data.edges');
    expect($edges)->toHaveCount(1);
    expect($edges[0]['source'])->toBe('employees');
    expect($edges[0]['target'])->toBe('departments');

    if (file_exists($dbFile)) {
        unlink($dbFile);
    }
});

test('it creates a new table visually and records audit log', function () {
    $user = User::create([
        'name' => 'Database Designer',
        'email' => 'designer@data.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Designer Workspace',
        'slug' => 'designer-ws',
    ]);

    $dbFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test_design_'.uniqid().'.sqlite';
    touch($dbFile);

    $connection = Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'Design DB',
        'driver' => 'sqlite',
        'database_name' => $dbFile,
    ]);

    $this->actingAs($user);

    $response = $this->postJson(route('api.connections.table.create', $connection->id), [
        'table_name' => 'invoices',
        'schema' => 'main',
        'columns' => [
            [
                'name' => 'id',
                'type' => 'INTEGER',
                'is_primary' => true,
                'is_auto_increment' => true,
                'is_nullable' => false,
            ],
            [
                'name' => 'invoice_number',
                'type' => 'VARCHAR(50)',
                'is_unique' => true,
                'is_nullable' => false,
            ],
            [
                'name' => 'total_amount',
                'type' => 'REAL',
                'default_value' => '0.0',
                'is_nullable' => false,
            ],
        ],
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);

    // Verify table exists by running a test insert
    /** @var QueryExecutionEngineContract $engine */
    $engine = app(QueryExecutionEngineContract::class);
    $insertRes = $engine->execute($connection, $user, "INSERT INTO invoices (invoice_number, total_amount) VALUES ('INV-2026-001', 999.50);");
    expect($insertRes->affectedRows)->toBe(1);

    // Verify AuditLog
    $audit = AuditLog::where('connection_id', $connection->id)->where('action', 'DDL_CREATE_TABLE')->first();
    expect($audit)->not->toBeNull();
    expect($audit?->details['table'])->toBe('invoices');

    if (file_exists($dbFile)) {
        unlink($dbFile);
    }
});

test('it prevents creating tables on read-only connection', function () {
    $user = User::create([
        'name' => 'Restricted Designer',
        'email' => 'restricted@data.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Restricted Space',
        'slug' => 'restricted-space',
    ]);

    $connection = Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'Locked DB',
        'driver' => 'sqlite',
        'database_name' => ':memory:',
        'is_read_only' => true,
    ]);

    $this->actingAs($user);

    $this->postJson(route('api.connections.table.create', $connection->id), [
        'table_name' => 'forbidden_table',
        'columns' => [
            ['name' => 'id', 'type' => 'INTEGER', 'is_primary' => true],
        ],
    ])->assertStatus(500); // ReadOnlyViolationException
});
