<?php

namespace App\Filament\Resources\Inventory\SparePart\Tables;

use App\Domain\Inventory\Enums\SparePartAbcClassification;
use App\Domain\Inventory\Enums\SparePartCategoryType;
use App\Domain\Inventory\Enums\SparePartCriticality;
use App\Domain\Inventory\Services\WarehouseDashboard;
use App\Domain\Reports\Services\SparePartLabelsPdfService;
use App\Models\Tenant;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SparePartTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->limitWithTooltip(35),
                // Este listado no tiene estado de ciclo de vida, así que la única
                // píldora se reserva a Criticidad, que es lo que decide si un
                // repuesto se repone con urgencia. Categoría y ABC son etiquetas
                // nominales: como texto se leen igual y dejan de competir.
                TextColumn::make('category_type')
                    ->label('Categoría')
                    ->formatStateUsing(fn (SparePartCategoryType $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('criticality')
                    ->label('Criticidad')
                    ->badge()
                    ->color(fn (SparePartCriticality $state): string => $state->color())
                    ->formatStateUsing(fn (SparePartCriticality $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('abc_classification')
                    ->label('ABC')
                    ->formatStateUsing(fn (SparePartAbcClassification $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('unit')
                    ->label('UM')
                    ->sortable(),
                TextColumn::make('unit_cost')
                    ->label('Costo unit.')
                    ->money('COP')
                    ->alignEnd()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_type')
                    ->label('Categoría')
                    ->options(SparePartCategoryType::options()),
                SelectFilter::make('criticality')
                    ->label('Criticidad')
                    ->options(SparePartCriticality::options()),
                SelectFilter::make('abc_classification')
                    ->label('Clasificación ABC')
                    ->options(SparePartAbcClassification::options()),
                // Los que abre el tablero de Almacén.
                Filter::make('bajo_minimo')
                    ->label('En el mínimo o por debajo')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => WarehouseDashboard::belowMinimum($query)),
                Filter::make('sin_existencias')
                    ->label('Sin existencias')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => WarehouseDashboard::outOfStock($query)),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                // Las etiquetas del estante: se marcan los repuestos y sale el PDF para imprimir.
                BulkAction::make('etiquetas')
                    ->label('Imprimir etiquetas QR')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('gray')
                    ->action(function (Collection $records): StreamedResponse {
                        /** @var Tenant $tenant */
                        $tenant = Filament::getTenant();
                        $pdf = app(SparePartLabelsPdfService::class)->generate($records, $tenant);

                        return response()->streamDownload(fn () => print ($pdf), 'ETIQUETAS-REPUESTOS-'.now()->format('Ymd').'.pdf');
                    }),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code');
    }
}
