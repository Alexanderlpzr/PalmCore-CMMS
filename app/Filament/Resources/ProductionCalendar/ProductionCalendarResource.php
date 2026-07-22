<?php

namespace App\Filament\Resources\ProductionCalendar;

use App\Filament\Resources\ProductionCalendar\Pages\ListProductionCalendarDays;
use App\Filament\Resources\ProductionCalendar\Schemas\ProductionCalendarDayForm;
use App\Filament\Resources\ProductionCalendar\Tables\ProductionCalendarTable;
use App\Models\ProductionCalendarDay;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * A3 — el calendario de producción, que es el denominador de la eficiencia.
 *
 * Sin estas filas la planta no tiene eficiencia: tiene disponibilidad de máquinas,
 * que es otra cosa y más pobre. Se edita en línea desde la tabla —una jornada es un
 * número, no un formulario— y se carga por mes desde la acción de cabecera, porque
 * nadie va a teclear 31 filas cada mes y un CMMS que lo exige se abandona.
 */
class ProductionCalendarResource extends Resource
{
    protected static ?string $model = ProductionCalendarDay::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'Día programado';

    protected static ?string $pluralModelLabel = 'Calendario de producción';

    protected static string|UnitEnum|null $navigationGroup = 'Estructura Operativa';

    protected static ?int $navigationSort = 3;

    protected static bool $isScopedToTenant = true;

    public static function shouldRegisterNavigation(): bool
    {
        // Oculto para los roles de tenant; solo el superadministrador de plataforma lo ve.
        return auth()->user()?->is_super_admin ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return ProductionCalendarDayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionCalendarTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionCalendarDays::route('/'),
        ];
    }
}
