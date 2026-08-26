<?php

namespace App\Services\Database\Drivers;

use App\DTOs\ConnectionConfigDTO;
use App\DTOs\Database\QueryResultDTO;
use App\Services\Database\Contracts\DatabaseDriverContract;
use PDO;
use PDOException;
use PDOStatement;

abstract class AbstractDatabaseDriver implements DatabaseDriverContract
{
    protected ?PDO $pdo = null;

    protected ?ConnectionConfigDTO $config = null;

    /**
     * {@inheritdoc}
     */
    public function testConnection(ConnectionConfigDTO $config): array
    {
        $startTime = microtime(true);

        try {
            $pdo = $this->connect($config);
            /** @var string $version */
            $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'success' => true,
                'latency_ms' => $latency,
                'version' => $version,
                'message' => 'Conexión exitosa al motor de base de datos.',
            ];
        } catch (PDOException $e) {
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'success' => false,
                'latency_ms' => $latency,
                'version' => 'N/A',
                'message' => 'Error de conexión: '.$e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function executeQuery(string $sql, array $bindings = [], ?int $timeoutSeconds = null): QueryResultDTO
    {
        if ($this->pdo === null) {
            throw new PDOException('No hay una conexión PDO activa.');
        }

        $trimmed = trim($sql);
        $isSelect = (bool) preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN|PRAGMA|WITH)\b/i', $trimmed);

        $startTime = microtime(true);

        /** @var PDOStatement $stmt */
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $columns = [];
        $rows = [];
        $affectedRows = $stmt->rowCount();

        if ($isSelect) {
            $columnCount = $stmt->columnCount();
            for ($i = 0; $i < $columnCount; $i++) {
                /** @var array{name?: string}|false $meta */
                $meta = $stmt->getColumnMeta($i);
                $columns[] = is_array($meta) && isset($meta['name']) ? $meta['name'] : "col_{$i}";
            }

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $affectedRows = count($rows);
        }

        return new QueryResultDTO(
            columns: $columns,
            rows: $rows,
            affectedRows: $affectedRows,
            durationMs: $durationMs,
            isSelect: $isSelect,
            message: $isSelect ? "{$affectedRows} filas obtenidas ({$durationMs} ms)" : "Sentencia ejecutada. {$affectedRows} filas afectadas ({$durationMs} ms)",
        );
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): void
    {
        $this->pdo = null;
        $this->config = null;
    }

    /**
     * Helper to run query returning associative rows.
     *
     * @param  array<int|string, mixed>  $bindings
     * @return list<array<string, mixed>>
     */
    protected function fetchAll(string $sql, array $bindings = []): array
    {
        if ($this->pdo === null) {
            throw new PDOException('No hay una conexión PDO activa.');
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);

        /** @var list<array<string, mixed>> $result */
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }
}
