<?php

use App\Models\Connection;
use App\Models\User;
use App\Models\Workspace;

test('it discovers active models dynamically and suggests recommended model', function () {
    $user = User::create([
        'name' => 'AI Engineer',
        'email' => 'ai@groq.com',
        'password' => 'password123',
    ]);

    $this->actingAs($user);

    $response = $this->getJson(route('api.ai.models'));

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonStructure([
        'success',
        'data' => [
            'models',
            'recommended_model',
        ],
    ]);

    $models = $response->json('data.models');
    expect($models)->toBeArray();
    expect(count($models))->toBeGreaterThan(0);
    expect($response->json('data.recommended_model'))->toBeString();
});

test('it converts natural language text to SQL with schema context', function () {
    $user = User::create([
        'name' => 'Prompt Engineer',
        'email' => 'prompt@groq.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'AI Workspace',
        'slug' => 'ai-ws',
    ]);

    $connection = Connection::create([
        'workspace_id' => $workspace->id,
        'name' => 'Analytics DB',
        'driver' => 'sqlite',
        'database_name' => ':memory:',
    ]);

    $this->actingAs($user);

    $response = $this->postJson(route('api.ai.text-to-sql', $connection->id), [
        'prompt' => 'Obtén todos los usuarios activos ordenados por fecha de creación de forma descendente',
        'model' => 'llama-3.3-70b-versatile',
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonStructure([
        'success',
        'data' => [
            'sql',
            'explanation',
            'model',
        ],
    ]);

    expect($response->json('data.sql'))->toContain('SELECT');
});

test('it analyzes and optimizes SQL queries', function () {
    $user = User::create([
        'name' => 'Tuning DBA',
        'email' => 'dba@groq.com',
        'password' => 'password123',
    ]);

    $this->actingAs($user);

    $response = $this->postJson(route('api.ai.optimize'), [
        'sql' => 'SELECT * FROM orders WHERE total_price > 500 AND status = \'completed\';',
        'model' => 'llama-3.3-70b-versatile',
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonStructure([
        'success',
        'data' => [
            'analysis',
            'optimized_sql',
            'suggested_indexes',
            'model',
        ],
    ]);
});

test('it fixes SQL syntax errors and handles chat messages', function () {
    $user = User::create([
        'name' => 'SQL Developer',
        'email' => 'dev@groq.com',
        'password' => 'password123',
    ]);

    $this->actingAs($user);

    // Fix SQL error
    $fixResponse = $this->postJson(route('api.ai.fix'), [
        'sql' => 'SELECT id, name FROMM users GROUP BY;',
        'error_message' => 'Syntax error at or near FROMM',
    ]);

    $fixResponse->assertOk();
    $fixResponse->assertJsonPath('success', true);
    expect($fixResponse->json('data.fixed_sql'))->toBeString();

    // Chat
    $chatResponse = $this->postJson(route('api.ai.chat'), [
        'messages' => [
            ['role' => 'user', 'content' => '¿Cuál es la diferencia entre INNER JOIN y LEFT JOIN?'],
        ],
    ]);

    $chatResponse->assertOk();
    $chatResponse->assertJsonPath('success', true);
    expect($chatResponse->json('data.reply'))->toBeString();
});
