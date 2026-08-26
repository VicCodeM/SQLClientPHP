<?php

namespace App\DTOs\Database;

readonly class QueryResultDTO
{
    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public int $affectedRows,
        public float $durationMs,
        public bool $isSelect,
        public ?string $message = null,
    ) {}
}
