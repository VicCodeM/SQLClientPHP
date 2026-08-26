<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Services\Ai\Contracts\GroqAiContract;
use App\Services\Database\DatabaseDriverManager;
use App\Services\Vault\Contracts\EncryptedVaultContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAssistantController extends Controller
{
    public function __construct(
        protected GroqAiContract $groqAi,
        protected EncryptedVaultContract $vault,
        protected DatabaseDriverManager $driverManager,
    ) {}

    /**
     * Get dynamic list of available models from Groq Cloud.
     */
    public function models(Request $request): JsonResponse
    {
        $apiKey = $request->header('X-Groq-Api-Key') ?? $request->input('api_key');
        $result = $this->groqAi->getAvailableModels($apiKey);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Generate SQL from natural language prompt with schema context.
     */
    public function textToSql(Request $request, ?Connection $connection = null): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'schema' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
            'api_key' => ['nullable', 'string'],
        ]);

        $apiKey = $request->header('X-Groq-Api-Key') ?? ($validated['api_key'] ?? null);
        $dialect = 'postgresql';
        $schemaContext = [];

        if ($connection) {
            $dialect = $connection->driver;
            $schema = $validated['schema'] ?? ($dialect === 'sqlite' ? 'main' : 'public');

            try {
                $config = $this->vault->resolveConnectionConfig($connection);
                $driver = $this->driverManager->driver($config);
                $tables = $driver->getTables($schema);

                foreach (array_slice($tables, 0, 15) as $tbl) {
                    $cols = $driver->getTableColumns($schema, $tbl->name);
                    $schemaContext[$tbl->name] = array_map(fn ($c) => "{$c->name} ({$c->fullType})", $cols);
                }

                $driver->disconnect();
            } catch (\Exception $e) {
                // Ignore schema fetch failure and continue
            }
        }

        $result = $this->groqAi->generateSqlFromText(
            $validated['prompt'],
            $schemaContext,
            $dialect,
            $validated['model'] ?? null,
            $apiKey
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Analyze and optimize SQL query.
     */
    public function optimize(Request $request, ?Connection $connection = null): JsonResponse
    {
        $validated = $request->validate([
            'sql' => ['required', 'string'],
            'model' => ['nullable', 'string'],
            'api_key' => ['nullable', 'string'],
        ]);

        $apiKey = $request->header('X-Groq-Api-Key') ?? ($validated['api_key'] ?? null);
        $dialect = $connection ? $connection->driver : 'postgresql';

        $result = $this->groqAi->explainAndOptimizeSql(
            $validated['sql'],
            $dialect,
            [],
            $validated['model'] ?? null,
            $apiKey
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Fix SQL query syntax or execution error.
     */
    public function fix(Request $request, ?Connection $connection = null): JsonResponse
    {
        $validated = $request->validate([
            'sql' => ['required', 'string'],
            'error_message' => ['required', 'string'],
            'model' => ['nullable', 'string'],
            'api_key' => ['nullable', 'string'],
        ]);

        $apiKey = $request->header('X-Groq-Api-Key') ?? ($validated['api_key'] ?? null);
        $dialect = $connection ? $connection->driver : 'postgresql';

        $result = $this->groqAi->fixSqlError(
            $validated['sql'],
            $validated['error_message'],
            $dialect,
            $validated['model'] ?? null,
            $apiKey
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Conversational chat endpoint.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.role' => ['required', 'in:user,assistant,system'],
            'messages.*.content' => ['required', 'string'],
            'model' => ['nullable', 'string'],
            'api_key' => ['nullable', 'string'],
        ]);

        $apiKey = $request->header('X-Groq-Api-Key') ?? ($validated['api_key'] ?? null);

        /** @var list<array{role: string, content: string}> $messages */
        $messages = $validated['messages'];

        $result = $this->groqAi->chat(
            $messages,
            $validated['model'] ?? null,
            $apiKey
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
