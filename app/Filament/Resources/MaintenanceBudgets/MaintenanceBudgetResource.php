<?php

namespace App\Filament\Resources\MaintenanceBudgets;

use App\Filament\Resources\MaintenanceBudgets\Pages\CreateMaintenanceBudget;
use App\Filament\Resources\MaintenanceBudgets\Pages\EditMaintenanceBudget;
use App\Filament\Resources\MaintenanceBudgets\Pages\ListMaintenanceBudgets;
use App\Filament\Resources\MaintenanceBudgets\Schemas\MaintenanceBudgetForm;
use App\Filament\Resources\MaintenanceBudgets\Tables\MaintenanceBudgetsTable;
use App\Models\MaintenanceBudget;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * El presupuesto mensual que la gerencia le asigna al área de mantenimiento.
 *
 * Es el denominador del control de gastos: el reporte de "Gastos de Mantenimiento"
 * compara lo gastado contra estas filas. Uno por planta y por mes.
 */
class MaintenanceBudgetResource extends Resource
{
    protected static ?string $model = MaintenanceBudget::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'Presupuesto';

    protected static ?string $pluralModelLabel = 'Presupuestos';

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?int $navigationSort = 5;

    protected static bool $isScopedToTenant = true;

    public static function form(Schema $schema): Schema
    {
        return MaintenanceBudgetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenanceBudgetsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceBudgets::route('/'),
            'create' => CreateMaintenanceBudget::route('/create'),
            'edit' => EditMaintenanceBudget::route('/{record}/edit'),
        ];
    }
}
