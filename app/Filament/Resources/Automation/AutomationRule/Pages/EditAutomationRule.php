<?php

namespace App\Filament\Resources\Automation\AutomationRule\Pages;

use App\Filament\Resources\Automation\AutomationRule\AutomationRuleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditAutomationRule extends EditRecord
{
    protected static string $resource = AutomationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(AutomationRuleResource::getUrl('index')),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Automatización actualizada';
    }
}
