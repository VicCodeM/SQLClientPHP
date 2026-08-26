<?php

use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\ConnectionGroup;
use App\Models\Permission;
use App\Models\QueryHistory;
use App\Models\Role;
use App\Models\SavedQuery;
use App\Models\SshTunnel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

test('it creates user with UUID and tests roles and permissions', function () {
    $user = User::create([
        'name' => 'Victor Maldonado',
        'email' => 'victor@example.com',
        'password' => 'secret123',
        'is_master_admin' => true,
    ]);

    expect(Str::isUuid($user->id))->toBeTrue();
    expect($user->is_master_admin)->toBeTrue();
    expect($user->hasPermission('any-permission'))->toBeTrue();

    $role = Role::create([
        'name' => 'DBA Administrator',
        'slug' => 'dba-admin',
    ]);

    $permission = Permission::create([
        'name' => 'Execute DDL',
        'slug' => 'execute-ddl',
        'module' => 'schema',
    ]);

    $role->permissions()->attach($permission->id);
    $user->roles()->attach($role->id);

    expect($user->roles)->toHaveCount(1);
    expect($role->permissions)->toHaveCount(1);
});

test('it manages workspaces, memberships and connection groups', function () {
    $owner = User::create([
        'name' => 'Owner User',
        'email' => 'owner@example.com',
        'password' => 'secret123',
    ]);

    $member = User::create([
        'name' => 'Dev Member',
        'email' => 'member@example.com',
        'password' => 'secret123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Main Engineering',
        'slug' => 'main-engineering',
        'settings' => ['theme' => 'dark'],
    ]);

    $workspace->members()->attach($member->id, ['role' => 'editor']);

    $group = ConnectionGroup::create([
        'workspace_id' => $workspace->id,
        'name' => 'Production Clusters',
        'color' => '#EF4444',
    ]);

    expect($workspace->owner->id)->toBe($owner->id);
    expect($workspace->members)->toHaveCount(1);
    expect($workspace->connectionGroups)->toHaveCount(1);
    expect($group->workspace->id)->toBe($workspace->id);
});

test('it creates multi-engine connections with encrypted credentials and ssh tunnels', function () {
    $user = User::create([
        'name' => 'Engineer',
        'email' => 'eng@example.com',
        'password' => 'secret123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Data Platform',
        'slug' => 'data-platform',
    ]);

    $tunnel = SshTunnel::create([
        'workspace_id' => $workspace->id,
        'name' => 'Bastion US-East',
        'host' => 'bastion.example.com',
        'port' => 2222,
        'username' => 'bastion_user',
        'auth_type' => 'password',
        'encrypted_credentials' => 'SuperSecretTunnelPass123',
    ]);

    $pgConnection = Connection::create([
        'workspace_id' => $workspace->id,
        'ssh_tunnel_id' => $tunnel->id,
        'name' => 'PostgreSQL Cluster Primary',
        'driver' => 'pgsql',
        'host' => '10.0.1.50',
        'port' => 5432,
        'database_name' => 'ecommerce_db',
        'username' => 'pgadmin',
        'encrypted_password' => 'PostgresSecretPass!987',
        'environment' => 'production',
        'use_ssh_tunnel' => true,
        'ssl_options' => ['sslmode' => 'require'],
    ]);

    $sqlCipherConnection = Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'Encrypted Local SQLCipher Vault',
        'driver' => 'sqlcipher',
        'database_name' => '/storage/databases/secure_vault.db',
        'encrypted_password' => 'CipherKeyPassphrase2026',
        'options' => ['cipher_compatibility' => 4],
    ]);

    expect($pgConnection->sshTunnel->name)->toBe('Bastion US-East');
    expect($pgConnection->encrypted_password)->toBe('PostgresSecretPass!987');
    expect($sqlCipherConnection->driver)->toBe('sqlcipher');
    expect($sqlCipherConnection->encrypted_password)->toBe('CipherKeyPassphrase2026');
});

test('it tracks query history, saved queries and audit logs', function () {
    $user = User::create([
        'name' => 'Analyst',
        'email' => 'analyst@example.com',
        'password' => 'secret123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Analytics Hub',
        'slug' => 'analytics-hub',
    ]);

    $connection = Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'Warehouse PG',
        'driver' => 'pgsql',
        'database_name' => 'analytics',
        'encrypted_password' => 'secret',
    ]);

    $history = QueryHistory::create([
        'workspace_id' => $workspace->id,
        'connection_id' => $connection->id,
        'user_id' => $user->id,
        'database_name' => 'analytics',
        'schema_name' => 'public',
        'query_text' => 'SELECT count(*) FROM orders WHERE created_at >= NOW() - INTERVAL \'30 days\';',
        'duration_ms' => 45,
        'affected_rows' => 1,
        'status' => 'success',
    ]);

    $saved = SavedQuery::create([
        'workspace_id' => $workspace->id,
        'connection_id' => $connection->id,
        'user_id' => $user->id,
        'title' => 'Monthly Active Users KPI',
        'query_text' => 'SELECT count(DISTINCT user_id) FROM session_logs;',
        'tags' => ['kpi', 'monthly', 'users'],
        'is_shared' => true,
    ]);

    $audit = AuditLog::create([
        'workspace_id' => $workspace->id,
        'connection_id' => $connection->id,
        'user_id' => $user->id,
        'action' => 'DDL_EXECUTE',
        'details' => ['statement' => 'ALTER TABLE users ADD COLUMN is_vip BOOLEAN;'],
        'ip_address' => '127.0.0.1',
    ]);

    expect($history->user->email)->toBe('analyst@example.com');
    expect($saved->tags)->toContain('monthly');
    expect($audit->action)->toBe('DDL_EXECUTE');
});
