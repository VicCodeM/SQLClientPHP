<?php

namespace App\DTOs\Database;

readonly class SequenceMetadataDTO
{
    public function __construct(
        public string $name,
        public string $schema,
        public string $dataType = 'bigint',
        public int $startValue = 1,
        public int $minValue = 1,
        public ?int $maxValue = null,
        public int $incrementBy = 1,
        public bool $isCycled = false,
        public ?int $lastValue = null,
    ) {}
}
