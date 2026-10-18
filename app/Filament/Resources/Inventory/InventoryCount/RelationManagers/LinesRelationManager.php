<?php

namespace App\Filament\Resources\Inventory\InventoryCount\RelationManagers;

use App\Domain\Inventory\Services\InventoryCountService;
use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * La planilla del conteo en pantalla: se escribe lo que hay en el estante, ubicación por
 * ubicación. Lo que dice el sistema no se ve mientras se cuenta —conteo ciego—, salvo que
 * se pida para revisar las diferencias antes de aplicar.
 */
class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Repuestos a contar';

    public bool $showDifferences = false;

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function table(Table $table): Table
    {
        /** @var InventoryCount $count */
        $count = $this->getOwnerRecord();
        $differencesVisible = fn (): bool => ! $count->isOpen() || $this->showDifferences;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('sparePart:id,code,name,unit'))
            ->columns([
                TextColumn::make('bin_location')->label('Ubicación')->placeholder('—')->sortable(),
                TextColumn::make('sparePart.name')
                    ->label('Repuesto')
                    ->description(fn (InventoryCountLine $record): ?string => $record->sparePart?->code)
                    ->searchable()
                    ->limitWithTooltip(40),
                TextColumn::make('sparePart.unit')->label('Unidad')
                    ->formatStateUsing(fn ($state): string => is_object($state) ? $state->value : (string) $state),
                TextInputColumn::make('counted_quantity')
                    ->label('Contado')
                    ->type('number')
                    ->rules(['nullable', 'numeric', 'min:0'])
                    ->disabled(fn (): bool => ! $count->isOpen() || ! (auth()->user()?->can('update', $count) ?? false))
                    ->updateStateUsing(function (InventoryCountLine $record, $state): mixed {
                        /** @var User $user */
                        $user = auth()->user();
                        app(InventoryCountService::class)->record($record, $state === null || $state === '' ? null : (float) $state, $user);

                        return $state;
                    }),
                TextColumn::make('expected_quantity')
                    ->label('Sistema')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->visible($differencesVisible),
                TextColumn::make('difference')
                    ->label('Diferencia')
                    ->state(fn (InventoryCountLine $record): ?float => $record->difference())
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->placeholder('Sin contar')
                    ->color(fn (?float $state): string => match (true) {
                        $state === null, $state == 0 => 'gray',
                        $state > 0 => 'success',
                        default => 'danger',
                    })
                    ->visible($differencesVisible),
            ])
            ->filters([
                TernaryFilter::make('contado')
                    ->label('Contado')
                    ->trueLabel('Ya contados')
                    ->falseLabel('Sin contar')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('counted_quantity'),
                        false: fn (Builder $query) => $query->whereNull('counted_quantity'),
                    ),
            ])
            ->headerActions([
                Action::make('diferencias')
                    ->label(fn (): string => $this->showDifferences ? 'Ocultar lo del sistema' : 'Ver diferencias')
                    ->icon(fn (): Heroicon => $this->showDifferences ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedEye)
                    ->color('gray')
                    ->visible(fn (): bool => $count->isOpen())
                    ->action(fn () => $this->showDifferences = ! $this->showDifferences),
            ])
            ->defaultSort('bin_location')
            ->paginated([25, 50, 100]);
    }
}
