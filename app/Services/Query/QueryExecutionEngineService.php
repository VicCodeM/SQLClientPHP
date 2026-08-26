<?php

namespace App\Services\Query;

use App\DTOs\Database\QueryResultDTO;
use App\Exceptions\Query\QueryExecutionException;
use App\Exceptions\Query\ReadOnlyViolationException;
use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\QueryHistory;
use App\Models\User;
use App\Services\Database\DatabaseDriverManager;
use App\Services\Query\Contracts\QueryExecutionEngineContract;
use App\Services\Vault\Contracts\EncryptedVaultContract;
use Generator;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

class QueryExecutionEngineService implements QueryExecutionEngineContract
{
    public function __construct(
        protected EncryptedVaultContract $vault,
        protected DatabaseDriverManager $driverManager,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function isDestructiveQuery(string $sql): bool
    {
        $trimmed = strtoupper(trim($sql));

        $destructiveKeywords = [
            'INSERT',
            'UPDATE',
            'DELETE',
            'DROP',
            'TRUNCATE',
            'ALTER',
            'REPLACE',
            'CREATE',
            'GRANT',
            'REVOKE',
        ];

        foreach ($destructiveKeywords as $keyword) {
            if (preg_match('/^'.preg_quote($keyword, '/').'\b/i', $trimmed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(
        Connection $connection,
        User $user,
        string $sql,
        array $bindings = [],
        ?string $schema = null,
    ): QueryResultDTO {
        if ($connection->is_read_only && $this->isDestructiveQuery($sql)) {
            throw ReadOnlyViolationException::destructiveOperationBlocked($this->extractStatementType($sql));
        }

        $config = $this->vault->resolveConnectionConfig($connection);
        $driver = $this->driverManager->driver($config);

        $startTime = microtime(true);

        try {
            if ($schema && in_array(strtolower($config->driver), ['pgsql', 'postgres', 'postgresql'], true)) {
                $driver->connect($config)->exec("SET search_path TO \"{$schema}\", public;");
            } elseif ($schema && in_array(strtolower($config->driver), ['mysql', 'mariadb'], true)) {
                $driver->connect($config)->exec("USE `{$schema}`;");
            }

            $result = $driver->executeQuery($sql, $bindings);

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            // Registro automático de historial
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

            // Auditoría para operaciones DDL o modificaciones
            if ($this->isDestructiveQuery($sql)) {
                AuditLog::create([
                    'workspace_id' => $connection->workspace_id,
                    'connection_id' => $connection->id,
                    'user_id' => $user->id,
                    'action' => $this->resolveAuditAction($sql),
                    'details' => [
                        'sql' => $sql,
                        'affected_rows' => $result->affectedRows,
                        'duration_ms' => $durationMs,
                    ],
                    'ip_address' => request()->ip(),
                    'created_at' => now(),
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            QueryHistory::create([
                'workspace_id' => $connection->workspace_id,
                'connection_id' => $connection->id,
                'user_id' => $user->id,
                'database_name' => $connection->database_name,
                'schema_name' => $schema,
                'query_text' => $sql,
                'duration_ms' => $durationMs,
                'affected_rows' => 0,
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'executed_at' => now(),
            ]);

            throw new QueryExecutionException("Error en la ejecución de la consulta: {$e->getMessage()}", 0, $e);
        } finally {
            $driver->disconnect();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stream(
        Connection $connection,
        User $user,
        string $sql,
        array $bindings = [],
        int $chunkSize = 500,
        ?string $schema = null,
    ): Generator {
        if ($connection->is_read_only && $this->isDestructiveQuery($sql)) {
            throw ReadOnlyViolationException::destructiveOperationBlocked($this->extractStatementType($sql));
        }

        $config = $this->vault->resolveConnectionConfig($connection);
        $driver = $this->driverManager->driver($config);
        $pdo = $driver->connect($config);

        if ($schema && in_array(strtolower($config->driver), ['pgsql', 'postgres', 'postgresql'], true)) {
            $pdo->exec("SET search_path TO \"{$schema}\", public;");
        } elseif ($schema && in_array(strtolower($config->driver), ['mysql', 'mariadb'], true)) {
            $pdo->exec("USE `{$schema}`;");
        }

        $startTime = microtime(true);
        $totalRows = 0;

        try {
            /** @var PDOStatement $stmt */
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);

            $columns = [];
            $columnCount = $stmt->columnCount();
            for ($i = 0; $i < $columnCount; $i++) {
                /** @var array{name?: string}|false $meta */
                $meta = $stmt->getColumnMeta($i);
                $columns[] = is_array($meta) && isset($meta['name']) ? $meta['name'] : "col_{$i}";
            }

            yield [
                'type' => 'columns',
                'data' => [
                    'columns' => $columns,
                ],
            ];

            $chunk = [];
            $chunkIndex = 0;

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $chunk[] = $row;
                $totalRows++;

                if (count($chunk) >= $chunkSize) {
                    yield [
                        'type' => 'chunk',
                        'data' => [
                            'chunk_index' => $chunkIndex++,
                            'rows' => $chunk,
                            'total_so_far' => $totalRows,
                        ],
                    ];
                    $chunk = [];
                }
            }

            if (!empty($chunk)) {
                yield [
                    'type' => 'chunk',
                    'data' => [
                        'chunk_index' => $chunkIndex,
                        'rows' => $chunk,
                        'total_so_far' => $totalRows,
                    ],
                ];
            }

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            yield [
                'type' => 'complete',
                'data' => [
                    'total_rows' => $totalRows,
                    'duration_ms' => $durationMs,
                ],
            ];

            QueryHistory::create([
                'workspace_id' => $connection->workspace_id,
                'connection_id' => $connection->id,
                'user_id' => $user->id,
                'database_name' => $connection->database_name,
                'schema_name' => $schema,
                'query_text' => $sql,
                'duration_ms' => (int) $durationMs,
                'affected_rows' => $totalRows,
                'status' => 'success',
                'executed_at' => now(),
            ]);
        } catch (PDOException $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            QueryHistory::create([
                'workspace_id' => $connection->workspace_id,
                'connection_id' => $connection->id,
                'user_id' => $user->id,
                'database_name' => $connection->database_name,
                'schema_name' => $schema,
                'query_text' => $sql,
                'duration_ms' => $durationMs,
                'affected_rows' => 0,
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'executed_at' => now(),
            ]);

            yield [
                'type' => 'error',
                'data' => [
                    'message' => $e->getMessage(),
                ],
            ];
        } finally {
            $driver->disconnect();
        }
    }

    protected function extractStatementType(string $sql): string
    {
        $words = preg_split('/\s+/', trim($sql));

        return isset($words[0]) ? strtoupper($words[0]) : 'UNKNOWN';
    }

    protected function resolveAuditAction(string $sql): string
    {
        $type = $this->extractStatementType($sql);

        return match ($type) {
            'DROP', 'ALTER', 'CREATE', 'TRUNCATE' => 'DDL_EXECUTE',
            'INSERT', 'UPDATE', 'DELETE', 'REPLACE' => 'DML_EXECUTE',
            default => 'QUERY_EXECUTE',
        };
    }
}
