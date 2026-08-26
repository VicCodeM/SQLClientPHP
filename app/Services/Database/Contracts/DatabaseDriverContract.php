<?php

namespace App\Services\Database\Contracts;

use App\DTOs\ConnectionConfigDTO;
use App\DTOs\Database\ColumnMetadataDTO;
use App\DTOs\Database\ExplainResultDTO;
use App\DTOs\Database\ForeignKeyMetadataDTO;
use App\DTOs\Database\FunctionMetadataDTO;
use App\DTOs\Database\IndexMetadataDTO;
use App\DTOs\Database\QueryResultDTO;
use App\DTOs\Database\SequenceMetadataDTO;
use App\DTOs\Database\TableMetadataDTO;
use App\DTOs\Database\TriggerMetadataDTO;
use App\DTOs\Database\ViewMetadataDTO;
use PDO;

interface DatabaseDriverContract
{
    /**
     * Establish and return the underlying PDO connection.
     */
    public function connect(ConnectionConfigDTO $config): PDO;

    /**
     * Test live connectivity and return server version and round-trip latency.
     *
     * @return array{success: bool, latency_ms: float, version: string, message: ?string}
     */
    public function testConnection(ConnectionConfigDTO $config): array;

    /**
     * Get all databases accessible on the server instance.
     *
     * @return list<string>
     */
    public function getDatabases(): array;

    /**
     * Get all schemas for a specific database (e.g. public, auth, custom).
     *
     * @return list<string>
     */
    public function getSchemas(?string $database = null): array;

    /**
     * Get tables for a given schema.
     *
     * @return list<TableMetadataDTO>
     */
    public function getTables(string $schema): array;

    /**
     * Get columns for a specific table.
     *
     * @return list<ColumnMetadataDTO>
     */
    public function getTableColumns(string $schema, string $table): array;

    /**
     * Get indexes for a specific table.
     *
     * @return list<IndexMetadataDTO>
     */
    public function getTableIndexes(string $schema, string $table): array;

    /**
     * Get foreign keys for a specific table.
     *
     * @return list<ForeignKeyMetadataDTO>
     */
    public function getTableForeignKeys(string $schema, string $table): array;

    /**
     * Get views (standard and materialized) for a given schema.
     *
     * @return list<ViewMetadataDTO>
     */
    public function getViews(string $schema): array;

    /**
     * Get stored functions and procedures for a given schema.
     *
     * @return list<FunctionMetadataDTO>
     */
    public function getFunctions(string $schema): array;

    /**
     * Get triggers for a given schema or specific table.
     *
     * @return list<TriggerMetadataDTO>
     */
    public function getTriggers(string $schema, ?string $table = null): array;

    /**
     * Get sequences for a given schema.
     *
     * @return list<SequenceMetadataDTO>
     */
    public function getSequences(string $schema): array;

    /**
     * Generate reverse-engineered CREATE TABLE DDL script.
     */
    public function getTableDdl(string $schema, string $table): string;

    /**
     * Execute arbitrary SQL query with parameterized bindings.
     *
     * @param  array<int|string, mixed>  $bindings
     */
    public function executeQuery(string $sql, array $bindings = [], ?int $timeoutSeconds = null): QueryResultDTO;

    /**
     * Generate and parse EXPLAIN / ANALYZE execution plan.
     *
     * @param  array<int|string, mixed>  $bindings
     */
    public function explainQuery(string $sql, array $bindings = [], bool $analyze = false): ExplainResultDTO;

    /**
     * Close the connection.
     */
    public function disconnect(): void;
}
