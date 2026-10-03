<?php

namespace App\Filament\Resources\AttendanceScans;

use App\Filament\Resources\AttendanceScans\Pages\ListAttendanceScans;
use App\Filament\Resources\AttendanceScans\Tables\AttendanceScansTable;
use App\Models\AttendanceScan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Lo que marcó la puerta, día por día y persona por persona.
 *
 * Es el historial que «Horas por confirmar» resume: aquí se ve cada marca suelta, de
 * dónde vino —el carné o una corrección a mano— y quién la registró. Las anuladas también
 * aparecen, con quién las anuló y por qué, porque ninguna marca se borra.
 */
class AttendanceScanResource extends Resource
{
    protected static ?string $model = AttendanceScan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $modelLabel = 'marca de portería';

    protected static ?string $pluralModelLabel = 'Marcas de portería';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|UnitEnum|null $navigationGroup = 'Talento Humano';

    protected static ?int $navigationSort = 21;

    protected static bool $isScopedToTenant = true;

    public static function table(Table $table): Table
    {
        return AttendanceScansTable::configure($table);
    }

    /** Con las anuladas: el filtro de la tabla decide si se ven. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScope(AttendanceScan::VALID_SCOPE);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceScans::route('/'),
        ];
    }
}
