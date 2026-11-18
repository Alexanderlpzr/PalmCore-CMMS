<?php

namespace App\Filament\Resources\Commercial\SalesDispatch\Pages;

use App\Filament\Resources\Commercial\SalesDispatch\SalesDispatchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesDispatch extends CreateRecord
{
    protected static string $resource = SalesDispatchResource::class;

    protected static ?string $title = 'Registrar cargue';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Cargue registrado: queda en tránsito';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
