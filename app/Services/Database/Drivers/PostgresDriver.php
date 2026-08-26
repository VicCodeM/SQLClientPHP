<?php

namespace App\Services\Database\Drivers;

use App\DTOs\ConnectionConfigDTO;
use App\DTOs\Database\ColumnMetadataDTO;
use App\DTOs\Database\ExplainResultDTO;
use App\DTOs\Database\ForeignKeyMetadataDTO;
use App\DTOs\Database\FunctionMetadataDTO;
use App\DTOs\Database\IndexMetadataDTO;
use App\DTOs\Database\SequenceMetadataDTO;
use App\DTOs\Database\TableMetadataDTO;
use App\DTOs\Database\TriggerMetadataDTO;
use App\DTOs\Database\ViewMetadataDTO;
use JsonException;
use PDO;

class PostgresDriver extends AbstractDatabaseDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(ConnectionConfigDTO $config): PDO
    {
        $this->config = $config;

        $host = $config->host ?? '127.0.0.1';
        $port = $config->port ?? 5432;
        $dbname = $config->databaseName;

        $dsnParts = ["pgsql:host={$host}", "port={$port}", "dbname={$dbname}"];

        if (!empty($config->sslOptions['sslmode'])) {
            $dsnParts[] = "sslmode={$config->sslOptions['sslmode']}";
        }

        if (!empty($config->sslOptions['sslrootcert'])) {
            $dsnParts[] = "sslrootcert={$config->sslOptions['sslrootcert']}";
        }

        $dsn = implode(';', $dsnParts);

        $pdo = new PDO($dsn, $config->username, $config->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10,
        ]);

        $pdo->exec("SET application_name = 'SQLClientWeb';");

        $this->pdo = $pdo;

        return $pdo;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabases(): array
    {
        $rows = $this->fetchAll('SELECT datname FROM pg_database WHERE datistemplate = false ORDER BY datname;');

        return array_map(fn (array $row): string => (string) $row['datname'], $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemas(?string $database = null): array
    {
        $sql = "SELECT schema_name FROM information_schema.schemata 
                WHERE schema_name NOT IN ('information_schema', 'pg_catalog', 'pg_toast') 
                AND schema_name NOT LIKE 'pg_temp_%'
                ORDER BY schema_name;";

        $rows = $this->fetchAll($sql);

        return array_map(fn (array $row): string => (string) $row['schema_name'], $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getTables(string $schema): array
    {
        $sql = "SELECT 
                    t.table_name,
                    t.table_type,
                    COALESCE(c.reltuples::bigint, 0) as estimated_rows,
                    COALESCE(pg_total_relation_size(quote_ident(t.table_schema) || '.' || quote_ident(t.table_name)), 0) as total_size_bytes,
                    obj_description((quote_ident(t.table_schema) || '.' || quote_ident(t.table_name))::regclass, 'pg_class') as comment
                FROM information_schema.tables t
                LEFT JOIN pg_class c ON c.relname = t.table_name
                LEFT JOIN pg_namespace n ON n.oid = c.relnamespace AND n.nspname = t.table_schema
                WHERE t.table_schema = :schema AND t.table_type = 'BASE TABLE'
                ORDER BY t.table_name;";

        $rows = $this->fetchAll($sql, ['schema' => $schema]);

        return array_map(function (array $row) use ($schema): TableMetadataDTO {
            return new TableMetadataDTO(
                name: (string) $row['table_name'],
                schema: $schema,
                type: (string) $row['table_type'],
                estimatedRows: (int) $row['estimated_rows'],
                totalSizeBytes: (int) $row['total_size_bytes'],
                comment: isset($row['comment']) ? (string) $row['comment'] : null,
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getTableColumns(string $schema, string $table): array
    {
        $sql = "SELECT 
                    c.column_name,
                    c.data_type,
                    c.udt_name,
                    c.is_nullable,
                    c.column_default,
                    c.character_maximum_length,
                    c.numeric_precision,
                    c.numeric_scale,
                    (SELECT COUNT(*) > 0 FROM information_schema.table_constraints tc 
                     JOIN information_schema.key_column_usage kcu 
                       ON tc.constraint_name = kcu.constraint_name 
                      AND tc.table_schema = kcu.table_schema
                     WHERE tc.constraint_type = 'PRIMARY KEY' 
                       AND tc.table_schema = c.table_schema 
                       AND tc.table_name = c.table_name 
                       AND kcu.column_name = c.column_name) as is_primary,
                    (COALESCE(c.column_default, '') LIKE 'nextval(%' OR c.is_identity = 'YES') as is_autoincrement,
                    col_description((quote_ident(c.table_schema) || '.' || quote_ident(c.table_name))::regclass, c.ordinal_position) as comment
                FROM information_schema.columns c
                WHERE c.table_schema = :schema AND c.table_name = :table
                ORDER BY c.ordinal_position;";

        $rows = $this->fetchAll($sql, ['schema' => $schema, 'table' => $table]);

        return array_map(function (array $row): ColumnMetadataDTO {
            $dataType = (string) $row['data_type'];
            $fullType = (string) ($row['udt_name'] ?? $dataType);

            return new ColumnMetadataDTO(
                name: (string) $row['column_name'],
                dataType: $dataType,
                fullType: $fullType,
                isNullable: $row['is_nullable'] === 'YES',
                defaultValue: isset($row['column_default']) ? (string) $row['column_default'] : null,
                isPrimaryKey: (bool) $row['is_primary'],
                isAutoIncrement: (bool) $row['is_autoincrement'],
                characterMaximumLength: isset($row['character_maximum_length']) ? (int) $row['character_maximum_length'] : null,
                numericPrecision: isset($row['numeric_precision']) ? (int) $row['numeric_precision'] : null,
                numericScale: isset($row['numeric_scale']) ? (int) $row['numeric_scale'] : null,
                comment: isset($row['comment']) ? (string) $row['comment'] : null,
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getTableIndexes(string $schema, string $table): array
    {
        $sql = 'SELECT 
                    i.relname as index_name,
                    idx.indisunique as is_unique,
                    idx.indisprimary as is_primary,
                    am.amname as index_type,
                    pg_get_indexdef(idx.indexrelid) as definition,
                    ARRAY(
                        SELECT pg_get_indexdef(idx.indexrelid, k + 1, true)
                        FROM generate_subscripts(idx.indkey, 1) as k
                        ORDER BY k
                    ) as column_names
                FROM pg_index idx
                JOIN pg_class i ON i.oid = idx.indexrelid
                JOIN pg_class t ON t.oid = idx.indrelid
                JOIN pg_namespace n ON n.oid = t.relnamespace
                JOIN pg_am am ON am.oid = i.relam
                WHERE n.nspname = :schema AND t.relname = :table
                ORDER BY i.relname;';

        $rows = $this->fetchAll($sql, ['schema' => $schema, 'table' => $table]);

        return array_map(function (array $row) use ($table): IndexMetadataDTO {
            /** @var string $colNamesRaw */
            $colNamesRaw = $row['column_names'] ?? '{}';
            $columns = array_filter(explode(',', trim((string) $colNamesRaw, '{}')));

            return new IndexMetadataDTO(
                name: (string) $row['index_name'],
                tableName: $table,
                columnNames: array_values($columns),
                isUnique: (bool) $row['is_unique'],
                isPrimary: (bool) $row['is_primary'],
                type: (string) $row['index_type'],
                definition: isset($row['definition']) ? (string) $row['definition'] : null,
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getTableForeignKeys(string $schema, string $table): array
    {
        $sql = "SELECT
                    tc.constraint_name,
                    kcu.column_name,
                    ccu.table_schema AS foreign_table_schema,
                    ccu.table_name AS foreign_table_name,
                    ccu.column_name AS foreign_column_name,
                    rc.update_rule,
                    rc.delete_rule
                FROM information_schema.table_constraints AS tc
                JOIN information_schema.key_column_usage AS kcu
                  ON tc.constraint_name = kcu.constraint_name
                  AND tc.table_schema = kcu.table_schema
                JOIN information_schema.constraint_column_usage AS ccu
                  ON ccu.constraint_name = tc.constraint_name
                  AND ccu.table_schema = tc.table_schema
                JOIN information_schema.referential_constraints AS rc
                  ON rc.constraint_name = tc.constraint_name
                WHERE tc.constraint_type = 'FOREIGN KEY'
                  AND tc.table_schema = :schema
                  AND tc.table_name = :table;";

        $rows = $this->fetchAll($sql, ['schema' => $schema, 'table' => $table]);

        return array_map(function (array $row) use ($table): ForeignKeyMetadataDTO {
            return new ForeignKeyMetadataDTO(
                name: (string) $row['constraint_name'],
                tableName: $table,
                columns: [(string) $row['column_name']],
                foreignSchema: (string) $row['foreign_table_schema'],
                foreignTable: (string) $row['foreign_table_name'],
                foreignColumns: [(string) $row['foreign_column_name']],
                onUpdate: (string) $row['update_rule'],
                onDelete: (string) $row['delete_rule'],
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getViews(string $schema): array
    {
        $sql = 'SELECT 
                    table_name as view_name,
                    view_definition as definition,
                    false as is_materialized
                FROM information_schema.views
                WHERE table_schema = :schema
                UNION ALL
                SELECT 
                    matviewname as view_name,
                    definition,
                    true as is_materialized
                FROM pg_matviews
                WHERE schemaname = :schema_mv
                ORDER BY view_name;';

        $rows = $this->fetchAll($sql, ['schema' => $schema, 'schema_mv' => $schema]);

        return array_map(function (array $row) use ($schema): ViewMetadataDTO {
            return new ViewMetadataDTO(
                name: (string) $row['view_name'],
                schema: $schema,
                isMaterialized: (bool) $row['is_materialized'],
                definition: isset($row['definition']) ? (string) $row['definition'] : null,
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(string $schema): array
    {
        $sql = "SELECT 
                    p.proname as function_name,
                    pg_get_function_result(p.oid) as return_type,
                    l.lanname as language,
                    pg_get_function_arguments(p.oid) as arguments,
                    pg_get_functiondef(p.oid) as definition,
                    obj_description(p.oid, 'pg_proc') as comment
                FROM pg_proc p
                JOIN pg_namespace n ON n.oid = p.pronamespace
                JOIN pg_language l ON l.oid = p.prolang
                WHERE n.nspname = :schema
                ORDER BY p.proname;";

        $rows = $this->fetchAll($sql, ['schema' => $schema]);

        return array_map(function (array $row) use ($schema): FunctionMetadataDTO {
            $argsRaw = isset($row['arguments']) ? (string) $row['arguments'] : '';
            $args = array_filter(array_map('trim', explode(',', $argsRaw)));

            return new FunctionMetadataDTO(
                name: (string) $row['function_name'],
                schema: $schema,
                returnType: (string) $row['return_type'],
                language: (string) $row['language'],
                argumentTypes: array_values($args),
                definition: isset($row['definition']) ? (string) $row['definition'] : null,
                comment: isset($row['comment']) ? (string) $row['comment'] : null,
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getTriggers(string $schema, ?string $table = null): array
    {
        $sql = 'SELECT 
                    trigger_name,
                    event_object_table as table_name,
                    action_timing as timing,
                    event_manipulation as event,
                    action_orientation as orientation,
                    action_statement
                FROM information_schema.triggers
                WHERE trigger_schema = :schema'.($table ? ' AND event_object_table = :table' : '').'
                ORDER BY trigger_name;';

        $bindings = ['schema' => $schema];
        if ($table) {
            $bindings['table'] = $table;
        }

        $rows = $this->fetchAll($sql, $bindings);

        return array_map(function (array $row) use ($schema): TriggerMetadataDTO {
            return new TriggerMetadataDTO(
                name: (string) $row['trigger_name'],
                schema: $schema,
                tableName: (string) $row['table_name'],
                timing: (string) $row['timing'],
                events: [(string) $row['event']],
                orientation: (string) $row['orientation'],
                actionStatement: isset($row['action_statement']) ? (string) $row['action_statement'] : null,
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getSequences(string $schema): array
    {
        $sql = 'SELECT 
                    sequence_name,
                    data_type,
                    start_value,
                    minimum_value,
                    maximum_value,
                    increment,
                    cycle_option
                FROM information_schema.sequences
                WHERE sequence_schema = :schema
                ORDER BY sequence_name;';

        $rows = $this->fetchAll($sql, ['schema' => $schema]);

        return array_map(function (array $row) use ($schema): SequenceMetadataDTO {
            return new SequenceMetadataDTO(
                name: (string) $row['sequence_name'],
                schema: $schema,
                dataType: (string) $row['data_type'],
                startValue: (int) $row['start_value'],
                minValue: (int) $row['minimum_value'],
                maxValue: isset($row['maximum_value']) ? (int) $row['maximum_value'] : null,
                incrementBy: (int) $row['increment'],
                isCycled: $row['cycle_option'] === 'YES',
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getTableDdl(string $schema, string $table): string
    {
        $columns = $this->getTableColumns($schema, $table);
        $foreignKeys = $this->getTableForeignKeys($schema, $table);

        $lines = [];
        $pkCols = [];

        foreach ($columns as $column) {
            $colDef = "    \"{$column->name}\" {$column->fullType}";
            if (!$column->isNullable) {
                $colDef .= ' NOT NULL';
            }
            if ($column->defaultValue !== null) {
                $colDef .= " DEFAULT {$column->defaultValue}";
            }
            if ($column->isPrimaryKey) {
                $pkCols[] = "\"{$column->name}\"";
            }
            $lines[] = $colDef;
        }

        if (!empty($pkCols)) {
            $lines[] = '    PRIMARY KEY ('.implode(', ', $pkCols).')';
        }

        foreach ($foreignKeys as $fk) {
            $cols = implode(', ', array_map(fn ($c) => "\"{$c}\"", $fk->columns));
            $fCols = implode(', ', array_map(fn ($c) => "\"{$c}\"", $fk->foreignColumns));
            $lines[] = "    CONSTRAINT \"{$fk->name}\" FOREIGN KEY ({$cols}) REFERENCES \"{$fk->foreignSchema}\".\"{$fk->foreignTable}\" ({$fCols}) ON UPDATE {$fk->onUpdate} ON DELETE {$fk->onDelete}";
        }

        return "CREATE TABLE \"{$schema}\".\"{$table}\" (\n".implode(",\n", $lines)."\n);";
    }

    /**
     * {@inheritdoc}
     */
    public function explainQuery(string $sql, array $bindings = [], bool $analyze = false): ExplainResultDTO
    {
        $analyzeOption = $analyze ? 'ANALYZE, ' : '';
        $explainSql = "EXPLAIN ({$analyzeOption}FORMAT JSON, VERBOSE, BUFFERS) {$sql}";

        $rows = $this->fetchAll($explainSql, $bindings);

        $rawOutput = [];
        $planTree = [];
        $executionTimeMs = null;
        $planningTimeMs = null;

        if (!empty($rows[0])) {
            /** @var string $jsonStr */
            $jsonStr = reset($rows[0]);
            $rawOutput[] = $jsonStr;

            try {
                /** @var list<array{Plan?: array<string, mixed>, 'Execution Time'?: float, 'Planning Time'?: float}> $parsed */
                $parsed = json_decode($jsonStr, true, 512, JSON_THROW_ON_ERROR);
                if (!empty($parsed[0])) {
                    $planTree = $parsed[0]['Plan'] ?? [];
                    $executionTimeMs = $parsed[0]['Execution Time'] ?? null;
                    $planningTimeMs = $parsed[0]['Planning Time'] ?? null;
                }
            } catch (JsonException) {
                $planTree = [];
            }
        }

        return new ExplainResultDTO(
            format: 'json',
            planNodeTree: $planTree,
            rawOutput: $rawOutput,
            executionTimeMs: $executionTimeMs,
            planningTimeMs: $planningTimeMs,
        );
    }
}
