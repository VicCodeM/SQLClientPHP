<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\User;
use App\Services\Query\Contracts\QueryExecutionEngineContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueryExecutionController extends Controller
{
    public function __construct(
        protected QueryExecutionEngineContract $queryEngine,
    ) {}

    /**
     * Execute SQL query synchronously.
     */
    public function execute(Request $request, Connection $connection): JsonResponse
    {
        $validated = $request->validate([
            'sql' => ['required', 'string'],
            'bindings' => ['nullable', 'array'],
            'schema' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user() ?? User::query()->firstOrFail();

        /** @var array<int|string, mixed> $bindings */
        $bindings = $validated['bindings'] ?? [];
        /** @var string|null $schema */
        $schema = $validated['schema'] ?? null;

        $result = $this->queryEngine->execute(
            connection: $connection,
            user: $user,
            sql: (string) $validated['sql'],
            bindings: $bindings,
            schema: $schema,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'columns' => $result->columns,
                'rows' => $result->rows,
                'affected_rows' => $result->affectedRows,
                'duration_ms' => $result->durationMs,
                'is_select' => $result->isSelect,
                'message' => $result->message,
            ],
        ]);
    }

    /**
     * Stream large query results via Server-Sent Events (SSE).
     */
    public function stream(Request $request, Connection $connection): StreamedResponse
    {
        $validated = $request->validate([
            'sql' => ['required', 'string'],
            'bindings' => ['nullable', 'array'],
            'schema' => ['nullable', 'string'],
            'chunk_size' => ['nullable', 'integer', 'min:50', 'max:5000'],
        ]);

        /** @var User $user */
        $user = $request->user() ?? User::query()->firstOrFail();

        /** @var array<int|string, mixed> $bindings */
        $bindings = $validated['bindings'] ?? [];
        /** @var string|null $schema */
        $schema = $validated['schema'] ?? null;
        $chunkSize = (int) ($validated['chunk_size'] ?? 500);

        return response()->stream(function () use ($connection, $user, $validated, $bindings, $chunkSize, $schema): void {
            $stream = $this->queryEngine->stream(
                connection: $connection,
                user: $user,
                sql: (string) $validated['sql'],
                bindings: $bindings,
                chunkSize: $chunkSize,
                schema: $schema,
            );

            foreach ($stream as $event) {
                $eventType = $event['type'];
                $eventData = json_encode($event['data']);

                echo "event: {$eventType}\n";
                echo "data: {$eventData}\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
