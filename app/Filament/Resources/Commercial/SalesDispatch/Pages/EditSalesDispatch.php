<?php

namespace App\Filament\Resources\Commercial\SalesDispatch\Pages;

use App\Filament\Resources\Commercial\SalesDispatch\SalesDispatchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesDispatch extends EditRecord
{
    protected static string $resource = SalesDispatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
