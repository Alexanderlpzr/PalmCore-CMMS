<?php

namespace App\Filament\Resources\Commercial\SalesOffer\Pages;

use App\Filament\Resources\Commercial\SalesOffer\SalesOfferResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesOffer extends ViewRecord
{
    protected static string $resource = SalesOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
