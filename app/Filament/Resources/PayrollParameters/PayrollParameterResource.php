<?php

namespace App\Filament\Resources\PayrollParameters;

use App\Filament\Resources\PayrollParameters\Pages\CreatePayrollParameter;
use App\Filament\Resources\PayrollParameters\Pages\EditPayrollParameter;
use App\Filament\Resources\PayrollParameters\Pages\ListPayrollParameters;
use App\Filament\Resources\PayrollParameters\Schemas\PayrollParameterForm;
use App\Filament\Resources\PayrollParameters\Tables\PayrollParametersTable;
use App\Models\PayrollParameterVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Los números de la nómina que la ley cambia, con su historia.
 *
 * La pantalla muestra tramos de vigencia, no ajustes: cada fila es «esto valía tanto
 * entre estas fechas». Cambiar un valor abre un tramo nuevo y cierra el anterior, para
 * que reabrir la nómina de enero en abril siga liquidando con lo de enero.
 */
class PayrollParameterResource extends Resource
{
    protected static ?string $model = PayrollParameterVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $modelLabel = 'Parámetro de nómina';

    protected static ?string $pluralModelLabel = 'Parámetros de nómina';

    protected static string|UnitEnum|null $navigationGroup = 'Talento Humano';

    protected static ?int $navigationSort = 30;

    protected static bool $isScopedToTenant = true;

    public static function form(Schema $schema): Schema
    {
        return PayrollParameterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayrollParametersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollParameters::route('/'),
            'create' => CreatePayrollParameter::route('/create'),
            'edit' => EditPayrollParameter::route('/{record}/edit'),
        ];
    }
}
