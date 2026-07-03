<?php

namespace App\Filament\Resources\Audit\Pages;

use App\Filament\Resources\Audit\AuditLogResource;
use App\Filament\Resources\Concerns\HasBackAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAuditLog extends ViewRecord
{
    use HasBackAction;

    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getBackAction(),
        ];
    }
}
