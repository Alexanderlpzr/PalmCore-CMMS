<?php

namespace App\Filament\Resources\Commercial\SalesOffer\Pages;

use App\Filament\Resources\Commercial\SalesOffer\SalesOfferResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesOffer extends EditRecord
{
    protected static string $resource = SalesOfferResource::class;

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
