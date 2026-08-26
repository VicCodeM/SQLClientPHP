<?php

namespace App\DTOs\Database;

readonly class TriggerMetadataDTO
{
    /**
     * @param  list<string>  $events
     */
    public function __construct(
        public string $name,
        public string $schema,
        public string $tableName,
        public string $timing, // BEFORE, AFTER, INSTEAD OF
        public array $events, // INSERT, UPDATE, DELETE
        public string $orientation, // ROW, STATEMENT
        public ?string $actionStatement = null,
        public ?string $definition = null,
    ) {}
}
