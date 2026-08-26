<?php

namespace App\Services\Database\Drivers;

use App\DTOs\ConnectionConfigDTO;
use App\DTOs\Database\ColumnMetadataDTO;
use App\DTOs\Database\ExplainResultDTO;
use App\DTOs\Database\ForeignKeyMetadataDTO;
use App\DTOs\Database\FunctionMetadataDTO;
use App\DTOs\Database\IndexMetadataDTO;
use App\DTOs\Database\TableMetadataDTO;
use App\DTOs\Database\TriggerMetadataDTO;
use App\DTOs\Database\ViewMetadataDTO;
use JsonException;
use PDO;

class MySQLDriver extends AbstractDatabaseDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(ConnectionConfigDTO $config): PDO
    {
        $this->config = $config;

        $host = $config->host ?? '127.0.0.1';
        $port = $config->port ?? 3306;
        $dbname = $config->databaseName;

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if (!empty($config->sslOptions['ssl_ca'])) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $config->sslOptions['ssl_ca'];
        }

        if (!empty($config->sslOptions['ssl_key'])) {
            $options[PDO::MYSQL_ATTR_SSL_KEY] = $config->sslOptions['ssl_key'];
        }

        if (!empty($config->sslOptions['ssl_cert'])) {
            $options[PDO::MYSQL_ATTR_SSL_CERT] = $config->sslOptions['ssl_cert'];
        }

        $pdo = new PDO($dsn, $config->username, $config->password, $options);
        $this->pdo = $pdo;

        return $pdo;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabases(): array
    {
        $rows = $this->fetchAll("SHOW DATABASES WHERE `Database` NOT IN ('information_schema', 'performance_schema', 'mysql', 'sys');");

        return array_map(fn (array $row): string => (string) reset($row), $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemas(?string $database = null): array
    {
        return $this->getDatabases();
    }

    /**
     * {@inheritdoc}
     */
    public function getTables(string $schema): array
    {
        $sql = "SELECT 
                    table_name,
                    table_type,
                    COALESCE(table_rows, 0) as estimated_rows,
                    COALESCE(data_length + index_length, 0) as total_size_bytes,
                    table_comment as comment
                FROM information_schema.tables 
                WHERE table_schema = :schema AND table_type = 'BASE TABLE'
                ORDER BY table_name;";

        $rows = $this->fetchAll($sql, ['schema' => $schema]);

        return array_map(function (array $row) use ($schema): TableMetadataDTO {
            return new TableMetadataDTO(
                name: (string) $row['table_name'],
                schema: $schema,
                type: (string) $row['table_type'],
                estimatedRows: (int) $row['estimated_rows'],
                totalSizeBytes: (int) $row['total_size_bytes'],
                comment: !empty($row['comment']) ? (string) $row['comment'] : null,
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getTableColumns(string $schema, string $table): array
    {
        $sql = 'SELECT 
                    column_name,
                    data_type,
                    column_type,
                    is_nullable,
                    column_default,
                    column_key,
                    extra,
                    character_maximum_length,
                    numeric_precision,
                    numeric_scale,
                    column_comment
                FROM information_schema.columns
                WHERE table_schema = :schema AND table_name = :table
                ORDER BY ordinal_position;';

        $rows = $this->fetchAll($sql, ['schema' => $schema, 'table' => $table]);

        return array_map(function (array $row): ColumnMetadataDTO {
            $isPrimary = ($row['column_key'] ?? '') === 'PRI';
            $isAutoIncrement = str_contains((string) ($row['extra'] ?? ''), 'auto_increment');

            return new ColumnMetadataDTO(
                name: (string) $row['column_name'],
                dataType: (string) $row['data_type'],
                fullType: (string) ($row['column_type'] ?? $row['data_type']),
                isNullable: $row['is_nullable'] === 'YES',
                defaultValue: isset($row['column_default']) ? (string) $row['column_default'] : null,
                isPrimaryKey: $isPrimary,
                isAutoIncrement: $isAutoIncrement,
                characterMaximumLength: isset($row['character_maximum_length']) ? (int) $row['character_maximum_length'] : null,
                numericPrecision: isset($row['numeric_precision']) ? (int) $row['numeric_precision'] : null,
                numericScale: isset($row['numeric_scale']) ? (int) $row['numeric_scale'] : null,
                comment: !empty($row['column_comment']) ? (string) $row['column_comment'] : null,
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getTableIndexes(string $schema, string $table): array
    {
        $sql = 'SELECT 
                    index_name,
                    non_unique,
                    index_type,
                    GROUP_CONCAT(column_name ORDER BY seq_in_index) as column_names
                FROM information_schema.statistics
                WHERE table_schema = :schema AND table_name = :table
                GROUP BY index_name, non_unique, index_type
                ORDER BY index_name;';

        $rows = $this->fetchAll($sql, ['schema' => $schema, 'table' => $table]);

        return array_map(function (array $row) use ($table): IndexMetadataDTO {
            $cols = explode(',', (string) $row['column_names']);
            $isPrimary = $row['index_name'] === 'PRIMARY';
            $isUnique = ((int) $row['non_unique']) === 0;

            return new IndexMetadataDTO(
                name: (string) $row['index_name'],
                tableName: $table,
                columnNames: $cols,
                isUnique: $isUnique,
                isPrimary: $isPrimary,
                type: (string) $row['index_type'],
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getTableForeignKeys(string $schema, string $table): array
    {
        $sql = 'SELECT
                    kcu.constraint_name,
                    kcu.column_name,
                    kcu.referenced_table_schema AS foreign_table_schema,
                    kcu.referenced_table_name AS foreign_table_name,
                    kcu.referenced_column_name AS foreign_column_name,
                    rc.update_rule,
                    rc.delete_rule
                FROM information_schema.key_column_usage AS kcu
                JOIN information_schema.referential_constraints AS rc
                  ON rc.constraint_name = kcu.constraint_name
                  AND rc.constraint_schema = kcu.constraint_schema
                WHERE kcu.table_schema = :schema
                  AND kcu.table_name = :table
                  AND kcu.referenced_table_name IS NOT NULL;';

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
                    view_definition as definition
                FROM information_schema.views
                WHERE table_schema = :schema
                ORDER BY table_name;';

        $rows = $this->fetchAll($sql, ['schema' => $schema]);

        return array_map(function (array $row) use ($schema): ViewMetadataDTO {
            return new ViewMetadataDTO(
                name: (string) $row['view_name'],
                schema: $schema,
                isMaterialized: false,
                definition: isset($row['definition']) ? (string) $row['definition'] : null,
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(string $schema): array
    {
        $sql = 'SELECT 
                    routine_name as function_name,
                    routine_type,
                    dtd_identifier as return_type,
                    routine_definition as definition,
                    routine_comment as comment
                FROM information_schema.routines
                WHERE routine_schema = :schema
                ORDER BY routine_name;';

        $rows = $this->fetchAll($sql, ['schema' => $schema]);

        return array_map(function (array $row) use ($schema): FunctionMetadataDTO {
            return new FunctionMetadataDTO(
                name: (string) $row['function_name'],
                schema: $schema,
                returnType: (string) ($row['return_type'] ?? $row['routine_type']),
                language: 'SQL',
                definition: isset($row['definition']) ? (string) $row['definition'] : null,
                comment: !empty($row['comment']) ? (string) $row['comment'] : null,
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
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getTableDdl(string $schema, string $table): string
    {
        $rows = $this->fetchAll("SHOW CREATE TABLE `{$schema}`.`{$table}`;");

        if (!empty($rows[0]['Create Table'])) {
            return (string) $rows[0]['Create Table'].';';
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function explainQuery(string $sql, array $bindings = [], bool $analyze = false): ExplainResultDTO
    {
        $explainSql = "EXPLAIN FORMAT=JSON {$sql}";
        $rows = $this->fetchAll($explainSql, $bindings);

        $planTree = [];
        $rawOutput = [];

        if (!empty($rows[0]['EXPLAIN'])) {
            $jsonStr = (string) $rows[0]['EXPLAIN'];
            $rawOutput[] = $jsonStr;

            try {
                /** @var array<string, mixed> $planTree */
                $planTree = json_decode($jsonStr, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $planTree = [];
            }
        }

        return new ExplainResultDTO(
            format: 'json',
            planNodeTree: $planTree,
            rawOutput: $rawOutput,
        );
    }
}
