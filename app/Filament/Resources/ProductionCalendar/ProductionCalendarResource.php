<?php

namespace App\Filament\Resources\ProductionCalendar;

use App\Filament\Resources\ProductionCalendar\Pages\CapturaDiaria;
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
 * número, no un formulario— y se carga por período, porque nadie va a teclear 31
 * filas cada mes y un CMMS que lo exige se abandona.
 *
 * Dos puertas, y no se pisan: «Programar mes» siembra la jornada por adelantado,
 * cuando todavía no hay fruta que anotar, y {@see CapturaDiaria} cierra el día con las
 * horas y las toneladas reales, enseñando el RFF del mes acumulándose. Esta tabla queda
 * para la corrección puntual del día que salió mal.
 */
class ProductionCalendarResource extends Resource
{
    protected static ?string $model = ProductionCalendarDay::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'Día de producción';

    protected static ?string $pluralModelLabel = 'Producción';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 7;

    protected static bool $isScopedToTenant = true;

    public static function shouldRegisterNavigation(): bool
    {
        // La planta escribe su propio denominador. Estuvo reservado al superadmin
        // mientras no hubo forma cómoda de cargarlo; con la captura semanal ya la hay,
        // y quien conoce las horas y la fruta es el planificador, no el proveedor.
        return auth()->user()?->can('viewAny', ProductionCalendarDay::class) ?? false;
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
            'diaria' => CapturaDiaria::route('/diaria'),
        ];
    }
}
