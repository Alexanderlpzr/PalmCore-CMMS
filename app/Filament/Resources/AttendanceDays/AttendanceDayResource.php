<?php

namespace App\Filament\Resources\AttendanceDays;

use App\Filament\Resources\AttendanceDays\Pages\ListAttendanceDays;
use App\Filament\Resources\AttendanceDays\Tables\AttendanceDaysTable;
use App\Models\AttendanceDay;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Las horas que propuso el reloj, esperando firma.
 *
 * No tiene formulario de creación ni de edición a propósito: un día de asistencia no se
 * escribe, se deriva de las marcas de portería. Corregirlo a mano dejaría horas que no
 * corresponden a ningún escaneo, que es exactamente el problema que este módulo vino a
 * resolver. Se corrige arreglando la marca y reconstruyendo.
 */
class AttendanceDayResource extends Resource
{
    protected static ?string $model = AttendanceDay::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $modelLabel = 'Día de asistencia';

    protected static ?string $pluralModelLabel = 'Horas por confirmar';

    protected static string|UnitEnum|null $navigationGroup = 'Talento Humano';

    protected static ?int $navigationSort = 20;

    protected static bool $isScopedToTenant = true;

    public static function table(Table $table): Table
    {
        return AttendanceDaysTable::configure($table);
    }

    /** El contador es la bandeja: cuántos días faltan por firmar. */
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getEloquentQuery()->proposed()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceDays::route('/'),
        ];
    }
}
