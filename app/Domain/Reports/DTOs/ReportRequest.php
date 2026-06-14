<?php

namespace App\Domain\Reports\DTOs;

use App\Domain\Reports\Enums\ReportType;

readonly class ReportRequest
{
    public function __construct(
        public ReportType $type,
        public string $tenantId,
        public string $requestedBy,
        public ?string $recordId = null,
    ) {}
}
