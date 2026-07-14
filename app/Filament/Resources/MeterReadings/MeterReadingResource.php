<?php

namespace App\Filament\Resources\MeterReadings;

use App\Filament\Resources\MeterReadings\Pages\ListMeterReadings;
use App\Filament\Resources\MeterReadings\Schemas\MeterReadingForm;
use App\Filament\Resources\MeterReadings\Tables\MeterReadingsTable;
use App\Models\EquipmentMeterReading;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * A3 — los horómetros en Filament.
 *
 * Solo listar y registrar. Una lectura no se edita ni se borra: es lo que el dial
 * decía ese día, y el acumulado de la máquina se construye encima de ella. Si el
 * número estaba mal, la corrección es otra lectura — incluido el dial cambiado, que
 * el servicio reconoce como reset en vez de rechazarlo como un horómetro que
 * «retrocede».
 */
class MeterReadingResource extends Resource
{
    protected static ?string $model = EquipmentMeterReading::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $modelLabel = 'Lectura de horómetro';

    protected static ?string $pluralModelLabel = 'Horómetros';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 6;

    protected static bool $isScopedToTenant = true;

    public static function form(Schema $schema): Schema
    {
        return MeterReadingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MeterReadingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMeterReadings::route('/'),
        ];
    }
}
