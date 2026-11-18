<?php

namespace App\Filament\Resources\Commercial\SalesOffer\Pages;

use App\Filament\Resources\Commercial\SalesOffer\SalesOfferResource;
use App\Models\SalesOffer;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSalesOffers extends ListRecords
{
    protected static string $resource = SalesOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva oferta'),
        ];
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'abiertas' => Tab::make('Abiertas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open())
                ->badge(fn (): int => SalesOffer::query()->open()->count()),
            'cerradas' => Tab::make('Cerradas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->closed()),
            'todas' => Tab::make('Todas'),
        ];
    }
}
