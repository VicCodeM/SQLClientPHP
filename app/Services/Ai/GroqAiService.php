<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\GroqAiContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GroqAiService implements GroqAiContract
{
    protected string $defaultApiKey;

    protected string $baseUrl;

    public function __construct()
    {
        /** @var string|null $key */
        $key = config('services.groq.api_key');
        $this->defaultApiKey = $key ?? '';

        /** @var string|null $url */
        $url = config('services.groq.base_url');
        $this->baseUrl = $url ?? 'https://api.groq.com/openai/v1';
    }

    /**
     * Fetch active models dynamically from Groq Cloud (GET /openai/v1/models).
     *
     * @return array{models: list<array{id: string, owned_by: string, context_window?: int}>, recommended_model: string}
     */
    public function getAvailableModels(?string $apiKey = null): array
    {
        $key = $this->resolveApiKey($apiKey);

        if (empty($key)) {
            return [
                'models' => [
                    ['id' => 'llama-3.3-70b-versatile', 'owned_by' => 'meta', 'context_window' => 128000],
                    ['id' => 'llama-3.1-8b-instant', 'owned_by' => 'meta', 'context_window' => 128000],
                    ['id' => 'deepseek-r1-distill-llama-70b', 'owned_by' => 'deepseek', 'context_window' => 128000],
                    ['id' => 'mixtral-8x7b-32768', 'owned_by' => 'mistralai', 'context_window' => 32768],
                ],
                'recommended_model' => 'llama-3.3-70b-versatile',
            ];
        }

        try {
            $response = Http::withToken($key)
                ->timeout(10)
                ->get("{$this->baseUrl}/models");

            if (!$response->successful()) {
                throw new RuntimeException("Error consultando modelos de Groq: {$response->body()}");
            }

            /** @var array{data?: list<array{id: string, owned_by?: string, context_window?: int, active?: bool}>} $data */
            $data = $response->json();
            $rawModels = $data['data'] ?? [];

            $models = [];
            foreach ($rawModels as $m) {
                // Filter out audio or whisper models to focus on LLM text models
                if (str_contains($m['id'], 'whisper') || str_contains($m['id'], 'audio') || str_contains($m['id'], 'guard')) {
                    continue;
                }

                $models[] = [
                    'id' => $m['id'],
                    'owned_by' => $m['owned_by'] ?? 'groq',
                    'context_window' => $m['context_window'] ?? 128000,
                ];
            }

            // Find recommended model dynamically
            $recommended = 'llama-3.3-70b-versatile';
            $ids = array_column($models, 'id');

            if (in_array('llama-3.3-70b-versatile', $ids, true)) {
                $recommended = 'llama-3.3-70b-versatile';
            } elseif (in_array('deepseek-r1-distill-llama-70b', $ids, true)) {
                $recommended = 'deepseek-r1-distill-llama-70b';
            } elseif (in_array('llama-3.1-70b-versatile', $ids, true)) {
                $recommended = 'llama-3.1-70b-versatile';
            } elseif (!empty($ids)) {
                $recommended = $ids[0];
            }

            return [
                'models' => $models,
                'recommended_model' => $recommended,
            ];
        } catch (\Exception $e) {
            // Fallback gracefully
            return [
                'models' => [
                    ['id' => 'llama-3.3-70b-versatile', 'owned_by' => 'meta', 'context_window' => 128000],
                    ['id' => 'llama-3.1-8b-instant', 'owned_by' => 'meta', 'context_window' => 128000],
                ],
                'recommended_model' => 'llama-3.3-70b-versatile',
            ];
        }
    }

    /**
     * Convert natural language prompt to SQL query with database schema context.
     *
     * @param  array<string, mixed>  $schemaContext
     * @return array{sql: string, explanation: string, model: string}
     */
    public function generateSqlFromText(
        string $prompt,
        array $schemaContext = [],
        string $dialect = 'postgresql',
        ?string $model = null,
        ?string $apiKey = null
    ): array {
        $chosenModel = $model ?? 'llama-3.3-70b-versatile';
        $key = $this->resolveApiKey($apiKey);

        $contextStr = !empty($schemaContext)
            ? 'Database Schema Context: '.json_encode($schemaContext, JSON_PRETTY_PRINT)
            : 'No schema provided, infer reasonable table names.';

        $systemPrompt = <<<PROMPT
You are an expert SQL architect. Given the database dialect [{$dialect}] and the schema context, convert the user's natural language request into a single optimal, syntactically correct SQL query.
Return your answer strictly in the following JSON format:
{
  "sql": "SELECT ...;",
  "explanation": "Short and concise explanation in Spanish of what this query does."
}
{$contextStr}
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt],
        ];

        $rawResponse = $this->sendChatCompletion($messages, $chosenModel, $key, true);

        /** @var array{sql?: string, explanation?: string} $parsed */
        $parsed = json_decode($rawResponse, true) ?? [];

        return [
            'sql' => $parsed['sql'] ?? $rawResponse,
            'explanation' => $parsed['explanation'] ?? 'Consulta generada a partir de tu instrucción.',
            'model' => $chosenModel,
        ];
    }

    /**
     * Analyze and suggest performance optimizations and indexes for a SQL query.
     *
     * @param  array<string, mixed>  $schemaContext
     * @return array{analysis: string, optimized_sql: ?string, suggested_indexes: list<string>, model: string}
     */
    public function explainAndOptimizeSql(
        string $sql,
        string $dialect = 'postgresql',
        array $schemaContext = [],
        ?string $model = null,
        ?string $apiKey = null
    ): array {
        $chosenModel = $model ?? 'llama-3.3-70b-versatile';
        $key = $this->resolveApiKey($apiKey);

        $contextStr = !empty($schemaContext)
            ? 'Schema context: '.json_encode($schemaContext, JSON_PRETTY_PRINT)
            : '';

        $systemPrompt = <<<PROMPT
You are a senior Database Performance Tuning Engineer. Analyze the provided [{$dialect}] SQL query for performance bottlenecks, missing indexes, and optimizations.
Return your response strictly in the following JSON format:
{
  "analysis": "Detailed performance analysis in Spanish explaining potential table scans or bottlenecks.",
  "optimized_sql": "The rewritten faster SQL query, or null if already optimal.",
  "suggested_indexes": ["CREATE INDEX ... ON ...;", "CREATE INDEX ... ON ...;"]
}
{$contextStr}
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Analyze this query:\n{$sql}"],
        ];

        $rawResponse = $this->sendChatCompletion($messages, $chosenModel, $key, true);

        /** @var array{analysis?: string, optimized_sql?: ?string, suggested_indexes?: list<string>} $parsed */
        $parsed = json_decode($rawResponse, true) ?? [];

        return [
            'analysis' => $parsed['analysis'] ?? $rawResponse,
            'optimized_sql' => $parsed['optimized_sql'] ?? null,
            'suggested_indexes' => $parsed['suggested_indexes'] ?? [],
            'model' => $chosenModel,
        ];
    }

    /**
     * Fix SQL error based on syntax error or database driver error message.
     *
     * @return array{fixed_sql: string, explanation: string, model: string}
     */
    public function fixSqlError(
        string $sql,
        string $errorMessage,
        string $dialect = 'postgresql',
        ?string $model = null,
        ?string $apiKey = null
    ): array {
        $chosenModel = $model ?? 'llama-3.3-70b-versatile';
        $key = $this->resolveApiKey($apiKey);

        $systemPrompt = <<<PROMPT
You are a database troubleshooter. Fix the following [{$dialect}] SQL query that failed with the error provided.
Return your response strictly in JSON:
{
  "fixed_sql": "SELECT ...;",
  "explanation": "Explicación concisa en español del error encontrado y cómo fue corregido."
}
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "SQL:\n{$sql}\n\nError Message:\n{$errorMessage}"],
        ];

        $rawResponse = $this->sendChatCompletion($messages, $chosenModel, $key, true);

        /** @var array{fixed_sql?: string, explanation?: string} $parsed */
        $parsed = json_decode($rawResponse, true) ?? [];

        return [
            'fixed_sql' => $parsed['fixed_sql'] ?? $sql,
            'explanation' => $parsed['explanation'] ?? 'Consulta corregida.',
            'model' => $chosenModel,
        ];
    }

    /**
     * Contextual conversational chat with database AI assistant.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{reply: string, model: string}
     */
    public function chat(
        array $messages,
        ?string $model = null,
        ?string $apiKey = null
    ): array {
        $chosenModel = $model ?? 'llama-3.3-70b-versatile';
        $key = $this->resolveApiKey($apiKey);

        $systemMessage = [
            'role' => 'system',
            'content' => 'Eres un asistente experto en bases de datos relacionales (PostgreSQL, MySQL, SQLite, SQLCipher). Responde siempre en español, de forma clara, técnica y profesional.',
        ];

        $fullMessages = array_merge([$systemMessage], $messages);

        $reply = $this->sendChatCompletion($fullMessages, $chosenModel, $key, false);

        return [
            'reply' => $reply,
            'model' => $chosenModel,
        ];
    }

    /**
     * Helper to send Chat Completion to Groq OpenAI-compatible endpoint.
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    protected function sendChatCompletion(array $messages, string $model, string $apiKey, bool $jsonMode = false): string
    {
        if (empty($apiKey)) {
            // Mock response if no key configured in test environment
            if ($jsonMode) {
                return json_encode([
                    'sql' => 'SELECT id, name, created_at FROM users WHERE is_active = true ORDER BY created_at DESC;',
                    'explanation' => 'Consulta simulada en modo de prueba local.',
                    'analysis' => 'La consulta utiliza filtros sobre columnas indexadas.',
                    'optimized_sql' => 'SELECT id, name, created_at FROM users WHERE is_active = true ORDER BY created_at DESC;',
                    'suggested_indexes' => ['CREATE INDEX idx_users_active_created ON users (is_active, created_at);'],
                    'fixed_sql' => 'SELECT id, name FROM users;',
                ]) ?: '';
            }

            return 'Hola, soy tu asistente de base de datos impulsado por Groq Cloud. Configura tu GROQ_API_KEY para habilitar respuestas en tiempo real con ultra baja latencia.';
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.1,
        ];

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withToken($apiKey)
            ->timeout(20)
            ->post("{$this->baseUrl}/chat/completions", $payload);

        if (!$response->successful()) {
            throw new RuntimeException("Error en respuesta de Groq ({$response->status()}): {$response->body()}");
        }

        /** @var array{choices?: list<array{message?: array{content?: string}}>} $data */
        $data = $response->json();

        return $data['choices'][0]['message']['content'] ?? '';
    }

    protected function resolveApiKey(?string $apiKey): string
    {
        return !empty($apiKey) ? $apiKey : $this->defaultApiKey;
    }
}
