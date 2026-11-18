<?php

namespace App\Filament\Resources\Commercial\SalesOffer\Pages;

use App\Filament\Resources\Commercial\SalesOffer\SalesOfferResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesOffer extends CreateRecord
{
    protected static string $resource = SalesOfferResource::class;

    protected static ?string $title = 'Nueva oferta mercantil';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
