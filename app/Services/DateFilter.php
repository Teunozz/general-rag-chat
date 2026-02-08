<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class DateFilter
{
    public function __construct(
        public readonly ?CarbonImmutable $startDate,
        public readonly ?CarbonImmutable $endDate,
        public readonly ?string $expression = null,
    ) {
    }

    public function isActive(): bool
    {
        return $this->startDate instanceof \Carbon\CarbonImmutable || $this->endDate instanceof \Carbon\CarbonImmutable;
    }
}
