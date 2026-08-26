<?php

namespace App\Services\Ai\Contracts;

interface GroqAiContract
{
    /**
     * Fetch active models dynamically from Groq Cloud (GET /openai/v1/models).
     *
     * @return array{models: list<array{id: string, owned_by: string, context_window?: int}>, recommended_model: string}
     */
    public function getAvailableModels(?string $apiKey = null): array;

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
    ): array;

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
    ): array;

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
    ): array;

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
    ): array;
}
