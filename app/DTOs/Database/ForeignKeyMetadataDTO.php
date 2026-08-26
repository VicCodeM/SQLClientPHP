<?php

namespace App\DTOs\Database;

readonly class ForeignKeyMetadataDTO
{
    /**
     * @param  list<string>  $columns
     * @param  list<string>  $foreignColumns
     */
    public function __construct(
        public string $name,
        public string $tableName,
        public array $columns,
        public string $foreignSchema,
        public string $foreignTable,
        public array $foreignColumns,
        public string $onUpdate = 'NO ACTION',
        public string $onDelete = 'NO ACTION',
    ) {}
}
