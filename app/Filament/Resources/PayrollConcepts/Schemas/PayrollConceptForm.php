<?php

namespace App\Filament\Resources\PayrollConcepts\Schemas;

use App\Domain\HumanResources\Enums\PayrollConceptType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayrollConceptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Concepto')
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label('Código')
                        ->required()
                        ->maxLength(40)
                        ->unique(ignoreRecord: true),
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(120),
                    Select::make('type')
                        ->label('Tipo')
                        ->options(PayrollConceptType::options())
                        ->default(PayrollConceptType::Devengado->value)
                        ->required()
                        ->native(false),
                    Toggle::make('is_active')->label('Activo')->default(true),
                ]),

            Section::make('A qué bases suma')
                ->description(
                    'Las cuatro bases no coinciden entre sí, y eso es lo correcto: el auxilio de transporte '
                    .'entra a la base de prima pero no al IBC, y las horas extras entran a prima pero no a la '
                    .'base de vacaciones.'
                )
                ->columns(2)
                ->schema([
                    Toggle::make('counts_ibc_health')->label('IBC de salud'),
                    Toggle::make('counts_ibc_pension')->label('IBC de pensión'),
                    Toggle::make('counts_severance_base')->label('Base de prima y cesantías'),
                    Toggle::make('counts_vacation_base')->label('Base de vacaciones'),
                ]),

            Textarea::make('notes')->label('Notas')->rows(2)->columnSpanFull(),
        ]);
    }
}
