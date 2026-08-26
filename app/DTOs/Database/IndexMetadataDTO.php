<?php

namespace App\DTOs\Database;

readonly class IndexMetadataDTO
{
    /**
     * @param  list<string>  $columnNames
     */
    public function __construct(
        public string $name,
        public string $tableName,
        public array $columnNames,
        public bool $isUnique,
        public bool $isPrimary,
        public string $type = 'BTREE',
        public ?string $definition = null,
    ) {}
}
