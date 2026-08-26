<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Query\ReadOnlyViolationException;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\QueryHistory;
use App\Models\User;
use App\Services\Database\DatabaseDriverManager;
use App\Services\Vault\Contracts\EncryptedVaultContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchemaDesignController extends Controller
{
    public function __construct(
        protected EncryptedVaultContract $vault,
        protected DatabaseDriverManager $driverManager,
    ) {}

    /**
     * Get entity-relationship graph data (nodes and edges) for a given schema.
     */
    public function erd(Request $request, Connection $connection): JsonResponse
    {
        $schema = $request->input('schema', $connection->driver === 'sqlite' ? 'main' : 'public');

        $config = $this->vault->resolveConnectionConfig($connection);
        $driver = $this->driverManager->driver($config);

        try {
            $tables = $driver->getTables($schema);
            $nodes = [];
            $edges = [];

            foreach ($tables as $table) {
                $columns = $driver->getTableColumns($schema, $table->name);
                $foreignKeys = $driver->getTableForeignKeys($schema, $table->name);

                $nodes[] = [
                    'id' => $table->name,
                    'name' => $table->name,
                    'schema' => $schema,
                    'estimated_rows' => $table->estimatedRows,
                    'columns' => array_map(fn ($c) => [
                        'name' => $c->name,
                        'data_type' => $c->dataType,
                        'full_type' => $c->fullType,
                        'is_primary' => $c->isPrimaryKey,
                        'is_nullable' => $c->isNullable,
                        'is_auto_increment' => $c->isAutoIncrement,
                    ], $columns),
                ];

                foreach ($foreignKeys as $fk) {
                    $edges[] = [
                        'id' => "{$table->name}->{$fk->foreignTable}_{$fk->name}",
                        'source' => $table->name,
                        'source_columns' => $fk->columns,
                        'target' => $fk->foreignTable,
                        'target_columns' => $fk->foreignColumns,
                        'on_delete' => $fk->onDelete,
                        'on_update' => $fk->onUpdate,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'schema' => $schema,
                    'nodes' => $nodes,
                    'edges' => $edges,
                ],
            ]);
        } finally {
            $driver->disconnect();
        }
    }

    /**
     * Create a new table via visual designer specification.
     */
    public function createTable(Request $request, Connection $connection): JsonResponse
    {
        if ($connection->is_read_only) {
            throw ReadOnlyViolationException::destructiveOperationBlocked('CREATE TABLE');
        }

        $validated = $request->validate([
            'table_name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
            'schema' => ['nullable', 'string'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*.name' => ['required', 'string', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
            'columns.*.type' => ['required', 'string'],
            'columns.*.is_nullable' => ['nullable', 'boolean'],
            'columns.*.is_primary' => ['nullable', 'boolean'],
            'columns.*.is_auto_increment' => ['nullable', 'boolean'],
            'columns.*.is_unique' => ['nullable', 'boolean'],
            'columns.*.default_value' => ['nullable', 'string'],
            'foreign_keys' => ['nullable', 'array'],
            'foreign_keys.*.name' => ['nullable', 'string'],
            'foreign_keys.*.column' => ['required_with:foreign_keys', 'string'],
            'foreign_keys.*.foreign_table' => ['required_with:foreign_keys', 'string'],
            'foreign_keys.*.foreign_column' => ['required_with:foreign_keys', 'string'],
            'foreign_keys.*.on_delete' => ['nullable', 'in:CASCADE,SET NULL,RESTRICT,NO ACTION'],
            'foreign_keys.*.on_update' => ['nullable', 'in:CASCADE,SET NULL,RESTRICT,NO ACTION'],
        ]);

        /** @var User $user */
        $user = $request->user() ?? User::query()->firstOrFail();
        $schema = $validated['schema'] ?? ($connection->driver === 'sqlite' ? 'main' : 'public');
        $tableName = $validated['table_name'];
        /** @var list<array{name: string, type: string, is_nullable?: bool, is_primary?: bool, is_auto_increment?: bool, is_unique?: bool, default_value?: string}> $columns */
        $columns = $validated['columns'];
        /** @var list<array{name?: string, column: string, foreign_table: string, foreign_column: string, on_delete?: string, on_update?: string}> $foreignKeys */
        $foreignKeys = $validated['foreign_keys'] ?? [];

        $config = $this->vault->resolveConnectionConfig($connection);
        $driver = $this->driverManager->driver($config);
        $driverName = strtolower($config->driver);
        $quote = in_array($driverName, ['mysql', 'mariadb'], true) ? '`' : '"';

        $columnDefs = [];
        $pkCols = [];

        foreach ($columns as $col) {
            $colName = $col['name'];
            $colType = strtoupper($col['type']);
            $isNullable = (bool) ($col['is_nullable'] ?? false);
            $isPrimary = (bool) ($col['is_primary'] ?? false);
            $isAutoIncrement = (bool) ($col['is_auto_increment'] ?? false);
            $isUnique = (bool) ($col['is_unique'] ?? false);
            $default = $col['default_value'] ?? null;

            if ($driverName === 'sqlite' || $driverName === 'sqlcipher') {
                if ($isPrimary && $isAutoIncrement) {
                    $def = "{$quote}{$colName}{$quote} INTEGER PRIMARY KEY AUTOINCREMENT";
                    $columnDefs[] = $def;

                    continue;
                }
            }

            $def = "{$quote}{$colName}{$quote} {$colType}";

            if ($isAutoIncrement && in_array($driverName, ['mysql', 'mariadb'], true)) {
                $def .= ' AUTO_INCREMENT';
            }

            if (!$isNullable) {
                $def .= ' NOT NULL';
            }

            if ($default !== null && $default !== '') {
                $def .= " DEFAULT {$default}";
            }

            if ($isUnique && !$isPrimary) {
                $def .= ' UNIQUE';
            }

            if ($isPrimary) {
                $pkCols[] = "{$quote}{$colName}{$quote}";
            }

            $columnDefs[] = $def;
        }

        if (!empty($pkCols) && !($driverName === 'sqlite' && count($pkCols) === 1 && str_contains(implode('', $columnDefs), 'PRIMARY KEY AUTOINCREMENT'))) {
            $columnDefs[] = 'PRIMARY KEY ('.implode(', ', $pkCols).')';
        }

        foreach ($foreignKeys as $fk) {
            $fkName = $fk['name'] ?? "fk_{$tableName}_{$fk['column']}";
            $col = "{$quote}{$fk['column']}{$quote}";
            $fTable = "{$quote}{$fk['foreign_table']}{$quote}";
            $fCol = "{$quote}{$fk['foreign_column']}{$quote}";
            $onDel = $fk['on_delete'] ?? 'CASCADE';
            $onUpd = $fk['on_update'] ?? 'CASCADE';

            $columnDefs[] = "CONSTRAINT {$quote}{$fkName}{$quote} FOREIGN KEY ({$col}) REFERENCES {$fTable} ({$fCol}) ON UPDATE {$onUpd} ON DELETE {$onDel}";
        }

        $fullTableName = ($driverName === 'sqlite' || $driverName === 'sqlcipher')
            ? "{$quote}{$tableName}{$quote}"
            : "{$quote}{$schema}{$quote}.{$quote}{$tableName}{$quote}";

        $ddl = "CREATE TABLE {$fullTableName} (\n  ".implode(",\n  ", $columnDefs)."\n);";

        $startTime = microtime(true);

        try {
            $driver->executeQuery($ddl);
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            QueryHistory::create([
                'workspace_id' => $connection->workspace_id,
                'connection_id' => $connection->id,
                'user_id' => $user->id,
                'database_name' => $connection->database_name,
                'schema_name' => $schema,
                'query_text' => $ddl,
                'duration_ms' => $durationMs,
                'affected_rows' => 0,
                'status' => 'success',
                'executed_at' => now(),
            ]);

            AuditLog::create([
                'workspace_id' => $connection->workspace_id,
                'connection_id' => $connection->id,
                'user_id' => $user->id,
                'action' => 'DDL_CREATE_TABLE',
                'details' => [
                    'table' => $tableName,
                    'schema' => $schema,
                    'columns' => $columns,
                    'foreign_keys' => $foreignKeys,
                    'ddl' => $ddl,
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Tabla '{$tableName}' creada con éxito.",
                'data' => [
                    'ddl' => $ddl,
                ],
            ]);
        } catch (\Throwable $e) {
            $cleanMsg = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1');

            return response()->json([
                'success' => false,
                'message' => 'Error al crear tabla: '.$cleanMsg,
                'data' => [
                    'ddl' => $ddl,
                ],
            ], 422);
        } finally {
            $driver->disconnect();
        }
    }
}
