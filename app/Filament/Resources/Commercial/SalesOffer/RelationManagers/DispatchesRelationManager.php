<?php

namespace App\Filament\Resources\Commercial\SalesOffer\RelationManagers;

use App\Domain\Commercial\Enums\DispatchStatus;
use App\Filament\Resources\Commercial\SalesDispatch\SalesDispatchResource;
use App\Models\SalesDispatch;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Los cargues que descuentan de la oferta, del más reciente al más antiguo. */
class DispatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'dispatches';

    protected static ?string $title = 'Despachos';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->dispatches()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('loaded_on')->label('Cargue')->date('d/m/Y')->sortable(),
                TextColumn::make('plate')->label('Placa'),
                TextColumn::make('remission_number')->label('Remisión')->placeholder('—'),
                TextColumn::make('departure_weight_tons')->label('Salida (tn)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->alignEnd(),
                TextColumn::make('arrival_weight_tons')->label('Llegada (tn)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->placeholder('En tránsito')
                    ->alignEnd(),
                TextColumn::make('invoice_number')->label('Factura')->placeholder('—'),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (DispatchStatus $state): string => $state->label())
                    ->color(fn (DispatchStatus $state): string => $state->color()),
            ])
            ->recordUrl(fn (SalesDispatch $record): string => SalesDispatchResource::getUrl('view', ['record' => $record]))
            ->defaultSort('loaded_on', 'desc')
            ->emptyStateHeading('Esta oferta todavía no tiene despachos');
    }
}
