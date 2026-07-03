<?php

namespace App\Filament\Resources\Automation\AutomationRule\Pages;

use App\Filament\Resources\Automation\AutomationRule\AutomationRuleResource;
use App\Filament\Resources\Concerns\HasBackAction;
use Filament\Resources\Pages\EditRecord;

class EditAutomationRule extends EditRecord
{
    use HasBackAction;

    protected static string $resource = AutomationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getBackAction(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Automatización actualizada';
    }
}
