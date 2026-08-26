<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Services\Database\DatabaseDriverManager;
use App\Services\Vault\Contracts\EncryptedVaultContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PDOException;

class SchemaExplorerController extends Controller
{
    public function __construct(
        protected EncryptedVaultContract $vault,
        protected DatabaseDriverManager $driverManager,
    ) {}

    /**
     * Get complete live introspected schema tree for a connection.
     */
    public function tree(Request $request, Connection $connection): JsonResponse
    {
        $config = $this->vault->resolveConnectionConfig($connection);
        $driver = $this->driverManager->driver($config);

        try {
            $driver->connect($config);
            $schemas = $driver->getSchemas();

            if (empty($schemas)) {
                $schemas = [$connection->driver === 'sqlite' ? 'main' : 'public'];
            }

            $tree = [];

            foreach ($schemas as $schemaName) {
                $tables = $driver->getTables($schemaName);
                $views = $driver->getViews($schemaName);
                $functions = $driver->getFunctions($schemaName);
                $triggers = $driver->getTriggers($schemaName);

                $tablesData = [];
                foreach ($tables as $t) {
                    $cols = $driver->getTableColumns($schemaName, $t->name);
                    $tablesData[] = [
                        'name' => $t->name,
                        'schema' => $schemaName,
                        'estimated_rows' => $t->estimatedRows,
                        'columns' => array_map(fn ($c) => [
                            'name' => $c->name,
                            'full_type' => $c->fullType,
                            'is_primary' => $c->isPrimaryKey,
                            'is_nullable' => $c->isNullable,
                        ], $cols),
                    ];
                }

                $viewsData = array_map(fn ($v) => [
                    'name' => $v->name,
                    'schema' => $schemaName,
                    'is_materialized' => $v->isMaterialized,
                ], $views);

                $functionsData = array_map(fn ($f) => [
                    'name' => $f->name,
                    'schema' => $schemaName,
                    'return_type' => $f->returnType,
                ], $functions);

                $triggersData = array_map(fn ($tr) => [
                    'name' => $tr->name,
                    'table_name' => $tr->tableName,
                    'timing' => $tr->timing,
                    'event' => implode(', ', $tr->events),
                ], $triggers);

                $tree[] = [
                    'schema' => $schemaName,
                    'tables' => $tablesData,
                    'views' => $viewsData,
                    'functions' => $functionsData,
                    'triggers' => $triggersData,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'connection' => [
                        'id' => $connection->id,
                        'name' => $connection->name,
                        'driver' => $connection->driver,
                        'database' => $connection->database_name,
                        'is_read_only' => $connection->is_read_only,
                    ],
                    'schemas' => $tree,
                ],
            ]);
        } catch (PDOException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al explorar base de datos: '.$e->getMessage(),
            ], 422);
        } finally {
            $driver->disconnect();
        }
    }

    /**
     * Get table DDL script.
     */
    public function tableDdl(Request $request, Connection $connection, string $table): JsonResponse
    {
        $schema = $request->input('schema', $connection->driver === 'sqlite' ? 'main' : 'public');
        $config = $this->vault->resolveConnectionConfig($connection);
        $driver = $this->driverManager->driver($config);

        try {
            $ddl = $driver->getTableDdl($schema, $table);

            return response()->json([
                'success' => true,
                'data' => [
                    'table' => $table,
                    'schema' => $schema,
                    'ddl' => $ddl,
                ],
            ]);
        } catch (PDOException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } finally {
            $driver->disconnect();
        }
    }
}
