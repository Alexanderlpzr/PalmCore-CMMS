<?php

namespace App\Domain\Analytics\DTOs;

readonly class TrendPoint
{
    public function __construct(
        public string $label,
        public ?float $value,
        public int $count = 0,
    ) {}
}
