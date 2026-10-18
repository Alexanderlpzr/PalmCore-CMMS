<?php

namespace App\Filament\Resources\Inventory\SparePart\RelationManagers;

use App\Domain\Inventory\Enums\InventoryTransactionType;
use App\Models\EquipmentSparePart;
use App\Models\InventoryTransaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * «Dónde se usa», como en Limble: los equipos que llevan este repuesto, según la lista de
 * repuestos de cada equipo, y cuánto se les ha despachado. Solo lectura: el enlace lo hace
 * mantenimiento desde la ficha del equipo.
 */
class EquipmentUsageRelationManager extends RelationManager
{
    protected static string $relationship = 'equipmentUsage';

    protected static ?string $title = 'Dónde se usa';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->equipmentUsage()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('equipment:id,code,name'))
            ->columns([
                TextColumn::make('equipment.name')
                    ->label('Equipo')
                    ->description(fn (EquipmentSparePart $record): ?string => $record->equipment?->code),
                TextColumn::make('part_number')->label('Referencia')->placeholder('—'),
                TextColumn::make('consumed')
                    ->label('Despachado (12 meses)')
                    ->state(fn (EquipmentSparePart $record): float => abs((float) InventoryTransaction::query()
                        ->where('spare_part_id', $record->spare_part_id)
                        ->where('type', InventoryTransactionType::Consumption->value)
                        ->where('performed_at', '>=', now()->subYear())
                        ->whereHas('workOrder', fn (Builder $order) => $order->where('equipment_id', $record->equipment_id))
                        ->sum('quantity')))
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd(),
                TextColumn::make('notes')->label('Notas')->limitWithTooltip(50)->placeholder('—'),
            ])
            ->emptyStateHeading('No está enlazado a ningún equipo')
            ->emptyStateDescription('Mantenimiento lo enlaza desde la pestaña Repuestos de cada equipo.');
    }
}
