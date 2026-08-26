<?php

use App\Exceptions\Query\ReadOnlyViolationException;
use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Query\Contracts\QueryExecutionEngineContract;

test('it paginates table data and returns primary keys and columns', function () {
    $user = User::create([
        'name' => 'Data Manager',
        'email' => 'manager@data.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Grid Workspace',
        'slug' => 'grid-ws',
    ]);

    $dbFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test_grid_'.uniqid().'.sqlite';
    touch($dbFile);

    $connection = Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'Inventory DB',
        'driver' => 'sqlite',
        'database_name' => $dbFile,
    ]);

    /** @var QueryExecutionEngineContract $engine */
    $engine = app(QueryExecutionEngineContract::class);
    $engine->execute($connection, $user, 'CREATE TABLE products (id INTEGER PRIMARY KEY, sku TEXT, stock INTEGER);');

    for ($i = 1; $i <= 30; $i++) {
        $engine->execute($connection, $user, "INSERT INTO products (sku, stock) VALUES ('SKU-{$i}', {$i} * 10);");
    }

    $this->actingAs($user);

    $response = $this->postJson(route('api.connections.table.data', $connection->id), [
        'table' => 'products',
        'page' => 1,
        'per_page' => 10,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.pagination.total_rows', 30);
    $response->assertJsonPath('data.pagination.total_pages', 3);
    $response->assertJsonPath('data.primary_keys.0', 'id');
    expect($response->json('data.rows'))->toHaveCount(10);

    if (file_exists($dbFile)) {
        unlink($dbFile);
    }
});

test('it updates a record inline and registers audit trail', function () {
    $user = User::create([
        'name' => 'Data Editor',
        'email' => 'editor@data.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Edit Workspace',
        'slug' => 'edit-ws',
    ]);

    $dbFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test_edit_'.uniqid().'.sqlite';
    touch($dbFile);

    $connection = Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'Catalog DB',
        'driver' => 'sqlite',
        'database_name' => $dbFile,
    ]);

    /** @var QueryExecutionEngineContract $engine */
    $engine = app(QueryExecutionEngineContract::class);
    $engine->execute($connection, $user, 'CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, price REAL);');
    $engine->execute($connection, $user, "INSERT INTO items (id, name, price) VALUES (1, 'Old Item', 19.99);");

    $this->actingAs($user);

    $response = $this->postJson(route('api.connections.table.row.update', $connection->id), [
        'table' => 'items',
        'primary_keys' => ['id' => 1],
        'updated_values' => ['name' => 'New Premium Item', 'price' => 29.99],
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);

    // Verify row was updated in database
    $queryRes = $engine->execute($connection, $user, 'SELECT name, price FROM items WHERE id = 1');
    expect($queryRes->rows[0]['name'])->toBe('New Premium Item');
    expect((float) $queryRes->rows[0]['price'])->toBe(29.99);

    // Verify AuditLog
    $audit = AuditLog::where('connection_id', $connection->id)->where('action', 'DML_UPDATE_INLINE')->first();
    expect($audit)->not->toBeNull();
    expect($audit?->details['table'])->toBe('items');

    if (file_exists($dbFile)) {
        unlink($dbFile);
    }
});

test('it inserts and deletes a row from the data grid', function () {
    $user = User::create([
        'name' => 'Admin Operator',
        'email' => 'admin@data.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Crud Workspace',
        'slug' => 'crud-ws',
    ]);

    $dbFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test_crud_'.uniqid().'.sqlite';
    touch($dbFile);

    $connection = Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'Operations DB',
        'driver' => 'sqlite',
        'database_name' => $dbFile,
    ]);

    /** @var QueryExecutionEngineContract $engine */
    $engine = app(QueryExecutionEngineContract::class);
    $engine->execute($connection, $user, 'CREATE TABLE tags (id INTEGER PRIMARY KEY, label TEXT);');

    $this->actingAs($user);

    // Insert row
    $insertRes = $this->postJson(route('api.connections.table.row.insert', $connection->id), [
        'table' => 'tags',
        'values' => ['label' => 'urgent-task'],
    ]);
    $insertRes->assertOk();

    $checkRes = $engine->execute($connection, $user, 'SELECT * FROM tags WHERE label = :l', ['l' => 'urgent-task']);
    expect($checkRes->rows)->toHaveCount(1);
    $tagId = $checkRes->rows[0]['id'];

    // Delete row
    $delRes = $this->postJson(route('api.connections.table.row.delete', $connection->id), [
        'table' => 'tags',
        'primary_keys' => ['id' => $tagId],
    ]);
    $delRes->assertOk();

    $afterDel = $engine->execute($connection, $user, 'SELECT count(*) as cnt FROM tags');
    expect($afterDel->rows[0]['cnt'])->toBe(0);

    if (file_exists($dbFile)) {
        unlink($dbFile);
    }
});

test('it blocks inline table updates and deletions on read-only connection', function () {
    $user = User::create([
        'name' => 'ReadOnly User',
        'email' => 'ro@data.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Read Only Space',
        'slug' => 'ro-space',
    ]);

    $connection = Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'Archive DB',
        'driver' => 'sqlite',
        'database_name' => ':memory:',
        'is_read_only' => true,
    ]);

    $this->actingAs($user);

    $this->postJson(route('api.connections.table.row.update', $connection->id), [
        'table' => 'logs',
        'primary_keys' => ['id' => 1],
        'updated_values' => ['status' => 'archived'],
    ])->assertStatus(500); // ReadOnlyViolationException

    $this->postJson(route('api.connections.table.row.insert', $connection->id), [
        'table' => 'logs',
        'values' => ['message' => 'test'],
    ])->assertStatus(500);

    $this->postJson(route('api.connections.table.row.delete', $connection->id), [
        'table' => 'logs',
        'primary_keys' => ['id' => 1],
    ])->assertStatus(500);
});
