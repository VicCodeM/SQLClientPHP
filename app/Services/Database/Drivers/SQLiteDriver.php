<?php

namespace App\Services\Database\Drivers;

use App\DTOs\ConnectionConfigDTO;
use App\DTOs\Database\ColumnMetadataDTO;
use App\DTOs\Database\ExplainResultDTO;
use App\DTOs\Database\ForeignKeyMetadataDTO;
use App\DTOs\Database\IndexMetadataDTO;
use App\DTOs\Database\SequenceMetadataDTO;
use App\DTOs\Database\TableMetadataDTO;
use App\DTOs\Database\TriggerMetadataDTO;
use App\DTOs\Database\ViewMetadataDTO;
use PDO;

class SQLiteDriver extends AbstractDatabaseDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(ConnectionConfigDTO $config): PDO
    {
        $this->config = $config;

        $dbPath = $config->databaseName;
        if ($dbPath !== ':memory:' && !str_starts_with($dbPath, '/') && !preg_match('/^[a-zA-Z]:[\\\\\/]/', $dbPath)) {
            $dbPath = database_path($dbPath);
        }

        $dsn = "sqlite:{$dbPath}";

        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10,
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON;');

        if (!empty($config->options['journal_mode'])) {
            $pdo->exec("PRAGMA journal_mode = {$config->options['journal_mode']};");
        }

        $this->pdo = $pdo;

        return $pdo;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabases(): array
    {
        $rows = $this->fetchAll('PRAGMA database_list;');

        return array_map(fn (array $row): string => (string) $row['name'], $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemas(?string $database = null): array
    {
        return ['main'];
    }

    /**
     * {@inheritdoc}
     */
    public function getTables(string $schema): array
    {
        $sql = "SELECT name, type FROM sqlite_master 
                WHERE type = 'table' AND name NOT LIKE 'sqlite_%' 
                ORDER BY name;";

        $rows = $this->fetchAll($sql);

        return array_map(function (array $row): TableMetadataDTO {
            $tableName = (string) $row['name'];
            $countRows = $this->fetchAll("SELECT count(*) as total FROM \"{$tableName}\";");
            $totalCount = !empty($countRows[0]['total']) ? (int) $countRows[0]['total'] : 0;

            return new TableMetadataDTO(
                name: $tableName,
                schema: 'main',
                type: 'BASE TABLE',
                estimatedRows: $totalCount,
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getTableColumns(string $schema, string $table): array
    {
        $rows = $this->fetchAll("PRAGMA table_info(\"{$table}\");");

        return array_map(function (array $row): ColumnMetadataDTO {
            $dataType = (string) ($row['type'] ?? 'TEXT');
            $isPk = ((int) $row['pk']) > 0;
            $isNotNull = ((int) $row['notnull']) === 1;

            return new ColumnMetadataDTO(
                name: (string) $row['name'],
                dataType: $dataType,
                fullType: $dataType,
                isNullable: !$isNotNull,
                defaultValue: isset($row['dflt_value']) ? (string) $row['dflt_value'] : null,
                isPrimaryKey: $isPk,
                isAutoIncrement: $isPk && strtoupper($dataType) === 'INTEGER',
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getTableIndexes(string $schema, string $table): array
    {
        $indexRows = $this->fetchAll("PRAGMA index_list(\"{$table}\");");

        $indexes = [];
        foreach ($indexRows as $row) {
            $idxName = (string) $row['name'];
            if (str_starts_with($idxName, 'sqlite_autoindex_')) {
                continue;
            }

            $infoRows = $this->fetchAll("PRAGMA index_info(\"{$idxName}\");");
            $columns = array_map(fn (array $r): string => (string) $r['name'], $infoRows);

            $indexes[] = new IndexMetadataDTO(
                name: $idxName,
                tableName: $table,
                columnNames: $columns,
                isUnique: ((int) $row['unique']) === 1,
                isPrimary: ($row['origin'] ?? '') === 'pk',
                type: 'BTREE',
            );
        }

        return $indexes;
    }

    /**
     * {@inheritdoc}
     */
    public function getTableForeignKeys(string $schema, string $table): array
    {
        $rows = $this->fetchAll("PRAGMA foreign_key_list(\"{$table}\");");

        return array_map(function (array $row) use ($table): ForeignKeyMetadataDTO {
            return new ForeignKeyMetadataDTO(
                name: "fk_{$table}_{$row['from']}",
                tableName: $table,
                columns: [(string) $row['from']],
                foreignSchema: 'main',
                foreignTable: (string) $row['table'],
                foreignColumns: [(string) $row['to']],
                onUpdate: (string) ($row['on_update'] ?? 'NO ACTION'),
                onDelete: (string) ($row['on_delete'] ?? 'NO ACTION'),
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getViews(string $schema): array
    {
        $sql = "SELECT name, sql FROM sqlite_master WHERE type = 'view' ORDER BY name;";
        $rows = $this->fetchAll($sql);

        return array_map(function (array $row): ViewMetadataDTO {
            return new ViewMetadataDTO(
                name: (string) $row['name'],
                schema: 'main',
                isMaterialized: false,
                definition: isset($row['sql']) ? (string) $row['sql'] : null,
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(string $schema): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getTriggers(string $schema, ?string $table = null): array
    {
        $sql = "SELECT name, tbl_name, sql FROM sqlite_master 
                WHERE type = 'trigger'".($table ? ' AND tbl_name = :table' : '').'
                ORDER BY name;';

        $bindings = $table ? ['table' => $table] : [];
        $rows = $this->fetchAll($sql, $bindings);

        return array_map(function (array $row): TriggerMetadataDTO {
            return new TriggerMetadataDTO(
                name: (string) $row['name'],
                schema: 'main',
                tableName: (string) $row['tbl_name'],
                timing: 'AFTER',
                events: ['ALL'],
                orientation: 'ROW',
                definition: isset($row['sql']) ? (string) $row['sql'] : null,
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getSequences(string $schema): array
    {
        $check = $this->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name='sqlite_sequence';");
        if (empty($check)) {
            return [];
        }

        $rows = $this->fetchAll('SELECT name, seq FROM sqlite_sequence;');

        return array_map(function (array $row): SequenceMetadataDTO {
            return new SequenceMetadataDTO(
                name: (string) $row['name'],
                schema: 'main',
                lastValue: (int) $row['seq'],
            );
        }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getTableDdl(string $schema, string $table): string
    {
        $rows = $this->fetchAll("SELECT sql FROM sqlite_master WHERE type='table' AND name = :table;", ['table' => $table]);

        if (!empty($rows[0]['sql'])) {
            return (string) $rows[0]['sql'].';';
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function explainQuery(string $sql, array $bindings = [], bool $analyze = false): ExplainResultDTO
    {
        $rows = $this->fetchAll("EXPLAIN QUERY PLAN {$sql}", $bindings);

        $rawOutput = [];
        $planTree = [];

        foreach ($rows as $row) {
            $line = "ID: {$row['id']}, Parent: {$row['parent']}, Detail: {$row['detail']}";
            $rawOutput[] = $line;
            $planTree[] = $row;
        }

        return new ExplainResultDTO(
            format: 'text',
            planNodeTree: ['nodes' => $planTree],
            rawOutput: $rawOutput,
        );
    }
}
