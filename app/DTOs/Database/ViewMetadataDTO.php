<?php

namespace App\DTOs\Database;

readonly class ViewMetadataDTO
{
    public function __construct(
        public string $name,
        public string $schema,
        public bool $isMaterialized = false,
        public ?string $definition = null,
        public ?string $comment = null,
    ) {}
}
