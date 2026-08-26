<?php

use App\Models\Connection;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

test('it renders studio workspace with registered database connections', function () {
    $user = User::create([
        'name' => 'Studio User',
        'email' => 'studio@client.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Default Workspace',
        'slug' => 'default-ws',
    ]);

    Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'PostgreSQL Prod',
        'driver' => 'pgsql',
        'database_name' => 'prod_db',
        'environment' => 'production',
    ]);

    Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'MySQL Staging',
        'driver' => 'mysql',
        'database_name' => 'staging_db',
        'environment' => 'staging',
    ]);

    $response = $this->get(route('studio.index'));

    $response->assertOk();
    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('Studio/Index')
            ->has('connections', 2)
            ->where('connections.0.name', 'MySQL Staging')
            ->where('connections.1.name', 'PostgreSQL Prod')
    );
});
