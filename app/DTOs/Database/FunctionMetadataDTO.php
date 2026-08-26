<?php

namespace App\DTOs\Database;

readonly class FunctionMetadataDTO
{
    /**
     * @param  list<string>  $argumentTypes
     */
    public function __construct(
        public string $name,
        public string $schema,
        public string $returnType,
        public string $language = 'plpgsql',
        public array $argumentTypes = [],
        public ?string $definition = null,
        public ?string $comment = null,
    ) {}
}
