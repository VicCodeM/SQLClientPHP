<?php

namespace App\DTOs\Database;

readonly class ExplainResultDTO
{
    /**
     * @param  array<string, mixed>  $planNodeTree
     * @param  list<string>  $rawOutput
     */
    public function __construct(
        public string $format, // json, text
        public array $planNodeTree,
        public array $rawOutput,
        public ?float $executionTimeMs = null,
        public ?float $planningTimeMs = null,
    ) {}
}
