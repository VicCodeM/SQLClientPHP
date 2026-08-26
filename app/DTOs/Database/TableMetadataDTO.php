<?php

namespace App\DTOs\Database;

readonly class TableMetadataDTO
{
    /**
     * @param  list<ColumnMetadataDTO>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $name,
        public string $schema,
        public string $type = 'BASE TABLE',
        public ?int $estimatedRows = null,
        public ?int $totalSizeBytes = null,
        public ?string $comment = null,
        public array $columns = [],
        public array $extra = [],
    ) {}
}
