<?php

namespace App\Services\Query\Contracts;

use App\DTOs\Database\QueryResultDTO;
use App\Models\Connection;
use App\Models\User;
use Generator;

interface QueryExecutionEngineContract
{
    /**
     * Execute SQL query synchronously with automated history logging and audit tracking.
     *
     * @param  array<int|string, mixed>  $bindings
     */
    public function execute(
        Connection $connection,
        User $user,
        string $sql,
        array $bindings = [],
        ?string $schema = null,
    ): QueryResultDTO;

    /**
     * Stream large query results in chunks with constant memory consumption (O(1) RAM).
     *
     * @param  array<int|string, mixed>  $bindings
     * @return Generator<int, array{type: string, data: mixed}>
     */
    public function stream(
        Connection $connection,
        User $user,
        string $sql,
        array $bindings = [],
        int $chunkSize = 500,
        ?string $schema = null,
    ): Generator;

    /**
     * Check if SQL contains destructive/modifying operations (INSERT, UPDATE, DELETE, DROP, TRUNCATE, ALTER).
     */
    public function isDestructiveQuery(string $sql): bool;
}
