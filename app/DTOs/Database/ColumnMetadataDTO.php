<?php

namespace App\DTOs\Database;

readonly class ColumnMetadataDTO
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $name,
        public string $dataType,
        public string $fullType,
        public bool $isNullable,
        public ?string $defaultValue,
        public bool $isPrimaryKey,
        public bool $isAutoIncrement,
        public ?int $characterMaximumLength = null,
        public ?int $numericPrecision = null,
        public ?int $numericScale = null,
        public ?string $comment = null,
        public array $extra = [],
    ) {}
}
