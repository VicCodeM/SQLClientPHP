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
use PDOException;

class TableDataController extends Controller
{
    public function __construct(
        protected EncryptedVaultContract $vault,
        protected DatabaseDriverManager $driverManager,
    ) {}

    /**
     * Get paginated table rows with primary key information.
     */
    public function data(Request $request, Connection $connection): JsonResponse
    {
        $validated = $request->validate([
            'table' => ['required', 'string'],
            'schema' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'sort_by' => ['nullable', 'string'],
            'sort_dir' => ['nullable', 'in:asc,desc,ASC,DESC'],
        ]);

        $schema = $validated['schema'] ?? ($connection->driver === 'sqlite' ? 'main' : 'public');
        $table = $validated['table'];
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 50);
        $offset = ($page - 1) * $perPage;

        $config = $this->vault->resolveConnectionConfig($connection);
        $driver = $this->driverManager->driver($config);

        try {
            $columns = $driver->getTableColumns($schema, $table);
            $primaryKeys = array_values(array_map(
                fn ($c) => $c->name,
                array_filter($columns, fn ($c) => $c->isPrimaryKey)
            ));

            $quote = in_array(strtolower($config->driver), ['mysql', 'mariadb'], true) ? '`' : '"';

            $orderByClause = '';
            if (!empty($validated['sort_by'])) {
                $dir = strtoupper($validated['sort_dir'] ?? 'ASC');
                $sortBy = str_replace($quote, '', $validated['sort_by']);
                $orderByClause = "ORDER BY {$quote}{$sortBy}{$quote} {$dir}";
            } elseif (!empty($primaryKeys)) {
                $pkCols = implode(', ', array_map(fn ($k) => "{$quote}{$k}{$quote} ASC", $primaryKeys));
                $orderByClause = "ORDER BY {$pkCols}";
            }

            $fromTable = $connection->driver === 'sqlite'
                ? "{$quote}{$table}{$quote}"
                : "{$quote}{$schema}{$quote}.{$quote}{$table}{$quote}";

            $countSql = "SELECT count(*) as total_count FROM {$fromTable};";
            $countResult = $driver->executeQuery($countSql);
            $totalRows = (int) ($countResult->rows[0]['total_count'] ?? 0);

            $dataSql = "SELECT * FROM {$fromTable} {$orderByClause} LIMIT {$perPage} OFFSET {$offset};";
            $result = $driver->executeQuery($dataSql);

            return response()->json([
                'success' => true,
                'data' => [
                    'columns' => $result->columns,
                    'column_definitions' => $columns,
                    'primary_keys' => $primaryKeys,
                    'rows' => $result->rows,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total_rows' => $totalRows,
                        'total_pages' => (int) ceil($totalRows / max(1, $perPage)),
                    ],
                ],
            ]);
        } finally {
            $driver->disconnect();
        }
    }

    /**
     * Atomically update a single record inline using primary key predicates.
     */
    public function updateRow(Request $request, Connection $connection): JsonResponse
    {
        if ($connection->is_read_only) {
            throw ReadOnlyViolationException::destructiveOperationBlocked('UPDATE');
        }

        $validated = $request->validate([
            'table' => ['required', 'string'],
            'schema' => ['nullable', 'string'],
            'primary_keys' => ['required', 'array', 'min:1'],
            'updated_values' => ['required', 'array', 'min:1'],
        ]);

        /** @var User $user */
        $user = $request->user() ?? User::query()->firstOrFail();
        $schema = $validated['schema'] ?? ($connection->driver === 'sqlite' ? 'main' : 'public');
        $table = $validated['table'];
        /** @var array<string, mixed> $primaryKeys */
        $primaryKeys = $validated['primary_keys'];
        /** @var array<string, mixed> $updatedValues */
        $updatedValues = $validated['updated_values'];

        $config = $this->vault->resolveConnectionConfig($connection);
        $driver = $this->driverManager->driver($config);
        $quote = in_array(strtolower($config->driver), ['mysql', 'mariadb'], true) ? '`' : '"';

        $setClauses = [];
        $bindings = [];

        foreach ($updatedValues as $column => $value) {
            $param = 'val_'.$column;
            $setClauses[] = "{$quote}{$column}{$quote} = :{$param}";
            $bindings[$param] = $value;
        }

        $whereClauses = [];
        foreach ($primaryKeys as $pkCol => $pkVal) {
            $param = 'pk_'.$pkCol;
            $whereClauses[] = "{$quote}{$pkCol}{$quote} = :{$param}";
            $bindings[$param] = $pkVal;
        }

        $fromTable = $connection->driver === 'sqlite'
            ? "{$quote}{$table}{$quote}"
            : "{$quote}{$schema}{$quote}.{$quote}{$table}{$quote}";

        $sql = "UPDATE {$fromTable} SET ".implode(', ', $setClauses).' WHERE '.implode(' AND ', $whereClauses).';';

        $startTime = microtime(true);

        try {
            $result = $driver->executeQuery($sql, $bindings);
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            QueryHistory::create([
                'workspace_id' => $connection->workspace_id,
                'connection_id' => $connection->id,
                'user_id' => $user->id,
                'database_name' => $connection->database_name,
                'schema_name' => $schema,
                'query_text' => $sql,
                'duration_ms' => $durationMs,
                'affected_rows' => $result->affectedRows,
                'status' => 'success',
                'executed_at' => now(),
            ]);

            AuditLog::create([
                'workspace_id' => $connection->workspace_id,
                'connection_id' => $connection->id,
                'user_id' => $user->id,
                'action' => 'DML_UPDATE_INLINE',
                'details' => [
                    'table' => $table,
                    'primary_keys' => $primaryKeys,
                    'updated_values' => $updatedValues,
                    'sql' => $sql,
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Registro actualizado ({$result->affectedRows} fila afectada).",
            ]);
        } catch (PDOException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el registro: '.$e->getMessage(),
            ], 422);
        } finally {
            $driver->disconnect();
        }
    }

    /**
     * Insert a new record into table.
     */
    public function insertRow(Request $request, Connection $connection): JsonResponse
    {
        if ($connection->is_read_only) {
            throw ReadOnlyViolationException::destructiveOperationBlocked('INSERT');
        }

        $validated = $request->validate([
            'table' => ['required', 'string'],
            'schema' => ['nullable', 'string'],
            'values' => ['required', 'array', 'min:1'],
        ]);

        /** @var User $user */
        $user = $request->user() ?? User::query()->firstOrFail();
        $schema = $validated['schema'] ?? ($connection->driver === 'sqlite' ? 'main' : 'public');
        $table = $validated['table'];
        /** @var array<string, mixed> $values */
        $values = $validated['values'];

        $config = $this->vault->resolveConnectionConfig($connection);
        $driver = $this->driverManager->driver($config);
        $quote = in_array(strtolower($config->driver), ['mysql', 'mariadb'], true) ? '`' : '"';

        $columns = [];
        $placeholders = [];
        $bindings = [];

        foreach ($values as $col => $val) {
            $param = 'val_'.$col;
            $columns[] = "{$quote}{$col}{$quote}";
            $placeholders[] = ":{$param}";
            $bindings[$param] = $val;
        }

        $fromTable = $connection->driver === 'sqlite'
            ? "{$quote}{$table}{$quote}"
            : "{$quote}{$schema}{$quote}.{$quote}{$table}{$quote}";

        $sql = "INSERT INTO {$fromTable} (".implode(', ', $columns).') VALUES ('.implode(', ', $placeholders).');';

        try {
            $result = $driver->executeQuery($sql, $bindings);

            AuditLog::create([
                'workspace_id' => $connection->workspace_id,
                'connection_id' => $connection->id,
                'user_id' => $user->id,
                'action' => 'DML_INSERT_ROW',
                'details' => [
                    'table' => $table,
                    'values' => $values,
                    'sql' => $sql,
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Fila creada exitosamente.',
            ]);
        } catch (PDOException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al insertar fila: '.$e->getMessage(),
            ], 422);
        } finally {
            $driver->disconnect();
        }
    }

    /**
     * Delete a record by primary key predicate.
     */
    public function deleteRow(Request $request, Connection $connection): JsonResponse
    {
        if ($connection->is_read_only) {
            throw ReadOnlyViolationException::destructiveOperationBlocked('DELETE');
        }

        $validated = $request->validate([
            'table' => ['required', 'string'],
            'schema' => ['nullable', 'string'],
            'primary_keys' => ['required', 'array', 'min:1'],
        ]);

        /** @var User $user */
        $user = $request->user() ?? User::query()->firstOrFail();
        $schema = $validated['schema'] ?? ($connection->driver === 'sqlite' ? 'main' : 'public');
        $table = $validated['table'];
        /** @var array<string, mixed> $primaryKeys */
        $primaryKeys = $validated['primary_keys'];

        $config = $this->vault->resolveConnectionConfig($connection);
        $driver = $this->driverManager->driver($config);
        $quote = in_array(strtolower($config->driver), ['mysql', 'mariadb'], true) ? '`' : '"';

        $whereClauses = [];
        $bindings = [];

        foreach ($primaryKeys as $col => $val) {
            $param = 'pk_'.$col;
            $whereClauses[] = "{$quote}{$col}{$quote} = :{$param}";
            $bindings[$param] = $val;
        }

        $fromTable = $connection->driver === 'sqlite'
            ? "{$quote}{$table}{$quote}"
            : "{$quote}{$schema}{$quote}.{$quote}{$table}{$quote}";

        $sql = "DELETE FROM {$fromTable} WHERE ".implode(' AND ', $whereClauses).';';

        try {
            $result = $driver->executeQuery($sql, $bindings);

            AuditLog::create([
                'workspace_id' => $connection->workspace_id,
                'connection_id' => $connection->id,
                'user_id' => $user->id,
                'action' => 'DML_DELETE_ROW',
                'details' => [
                    'table' => $table,
                    'primary_keys' => $primaryKeys,
                    'sql' => $sql,
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Registro eliminado ({$result->affectedRows} fila afectada).",
            ]);
        } catch (PDOException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar fila: '.$e->getMessage(),
            ], 422);
        } finally {
            $driver->disconnect();
        }
    }
}
