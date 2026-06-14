<?php

namespace App\Domain\Alerts\Data;

use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;

final class CreateAlertData
{
    /**
     * @param  string[]  $notifiableUserIds  IDs de usuarios a notificar cuando la alerta se crea
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly AlertSeverity $severity,
        public readonly AlertCategory $category,
        public readonly string $title,
        public readonly ?string $message = null,
        public readonly ?string $entityType = null,
        public readonly ?string $entityId = null,
        public readonly array $metadata = [],
        public readonly array $notifiableUserIds = [],
    ) {}
}
