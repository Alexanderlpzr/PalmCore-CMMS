<?php

namespace App\Filament\Resources\PayrollConcepts;

use App\Filament\Resources\PayrollConcepts\Pages\ListPayrollConcepts;
use App\Filament\Resources\PayrollConcepts\Schemas\PayrollConceptForm;
use App\Filament\Resources\PayrollConcepts\Tables\PayrollConceptsTable;
use App\Models\PayrollConcept;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * La matriz de bases: qué concepto suma a qué.
 *
 * Es la tabla que más pleitos laborales evita. El libro de la extractora calcula cuatro
 * bases distintas y ninguna coincide con otra, y ese mapa cambia por convención, por
 * pacto y cada vez que se inventa un concepto nuevo.
 */
class PayrollConceptResource extends Resource
{
    protected static ?string $model = PayrollConcept::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $modelLabel = 'Concepto de nómina';

    protected static ?string $pluralModelLabel = 'Conceptos de nómina';

    protected static string|UnitEnum|null $navigationGroup = 'Talento Humano';

    protected static ?int $navigationSort = 35;

    protected static bool $isScopedToTenant = true;

    public static function form(Schema $schema): Schema
    {
        return PayrollConceptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayrollConceptsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollConcepts::route('/'),
        ];
    }
}
