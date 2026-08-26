<?php

use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\QueryHistory;
use App\Models\SavedQuery;
use App\Models\User;
use App\Models\Workspace;

test('it lists and filters query execution history and supports clearing history', function () {
    $user = User::create([
        'name' => 'History User',
        'email' => 'history@client.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'History WS',
        'slug' => 'history-ws',
    ]);

    $connection = Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'History DB',
        'driver' => 'sqlite',
        'database_name' => ':memory:',
    ]);

    QueryHistory::create([
        'workspace_id' => $workspace->id,
        'connection_id' => $connection->id,
        'user_id' => $user->id,
        'database_name' => 'main',
        'query_text' => 'SELECT count(*) FROM users;',
        'duration_ms' => 12,
        'affected_rows' => 1,
        'status' => 'success',
        'executed_at' => now(),
    ]);

    QueryHistory::create([
        'workspace_id' => $workspace->id,
        'connection_id' => $connection->id,
        'user_id' => $user->id,
        'database_name' => 'main',
        'query_text' => 'SELECT invalid_syntax;',
        'duration_ms' => 4,
        'affected_rows' => 0,
        'status' => 'error',
        'error_message' => 'no such column: invalid_syntax',
        'executed_at' => now(),
    ]);

    $this->actingAs($user);

    // List history
    $res = $this->getJson(route('api.history.index', [
        'workspace_id' => $workspace->id,
        'status' => 'success',
    ]));

    $res->assertOk();
    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.query_text'))->toBe('SELECT count(*) FROM users;');

    // Clear history
    $delRes = $this->deleteJson(route('api.history.clear'), [
        'workspace_id' => $workspace->id,
    ]);

    $delRes->assertOk();
    expect(QueryHistory::where('workspace_id', $workspace->id)->count())->toBe(0);
});

test('it creates, updates, lists and deletes saved query snippets', function () {
    $user = User::create([
        'name' => 'Snippet User',
        'email' => 'snippet@client.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Snippet WS',
        'slug' => 'snippet-ws',
    ]);

    $this->actingAs($user);

    // Create snippet
    $createRes = $this->postJson(route('api.snippets.store'), [
        'workspace_id' => $workspace->id,
        'title' => 'Top 10 Active Customers',
        'description' => 'Calculates active customers by order volume',
        'query_text' => 'SELECT * FROM customers ORDER BY total_orders DESC LIMIT 10;',
        'tags' => ['analytics', 'customers', 'sales'],
    ]);

    $createRes->assertStatus(201);
    $snippetId = $createRes->json('data.id');

    // List snippets
    $listRes = $this->getJson(route('api.snippets.index', [
        'workspace_id' => $workspace->id,
        'search' => 'Customers',
    ]));

    $listRes->assertOk();
    expect($listRes->json('data'))->toHaveCount(1);

    // Update snippet
    $updateRes = $this->putJson(route('api.snippets.update', $snippetId), [
        'title' => 'Top 25 Active Customers',
        'query_text' => 'SELECT * FROM customers ORDER BY total_orders DESC LIMIT 25;',
    ]);

    $updateRes->assertOk();
    $saved = SavedQuery::find($snippetId);
    expect($saved?->title)->toBe('Top 25 Active Customers');

    // Delete snippet
    $delRes = $this->deleteJson(route('api.snippets.destroy', $snippetId));
    $delRes->assertOk();
    expect(SavedQuery::find($snippetId))->toBeNull();
});

test('it returns paginated audit log entries with filters', function () {
    $user = User::create([
        'name' => 'Audit Officer',
        'email' => 'officer@client.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Audit WS',
        'slug' => 'audit-ws',
    ]);

    $connection = Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'Production Database',
        'driver' => 'pgsql',
        'database_name' => 'prod_db',
    ]);

    AuditLog::create([
        'workspace_id' => $workspace->id,
        'connection_id' => $connection->id,
        'user_id' => $user->id,
        'action' => 'DDL_DROP_TABLE',
        'details' => ['table' => 'legacy_logs', 'ip' => '192.168.1.50'],
        'ip_address' => '192.168.1.50',
        'created_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->getJson(route('api.audit.index', [
        'workspace_id' => $workspace->id,
        'action' => 'DDL_DROP_TABLE',
    ]));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.action'))->toBe('DDL_DROP_TABLE');
    expect($response->json('data.0.details.table'))->toBe('legacy_logs');
});
