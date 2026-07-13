<?php

namespace App\Filament\Resources\Contractors\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContractorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contratista')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Razón social')
                            ->required()
                            ->maxLength(150)
                            ->unique(ignoreRecord: true)
                            ->placeholder('Montajes Industriales HF'),
                        TextInput::make('tax_id')
                            ->label('NIT')
                            ->maxLength(30),
                        TextInput::make('specialty')
                            ->label('Especialidad')
                            ->maxLength(60)
                            ->placeholder('Montajes, mecánico, eléctrico…'),
                        TextInput::make('hourly_rate')
                            ->label('Tarifa de referencia por hora')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            // Lo pactado se congela en cada OT: esta tarifa es solo
                            // una referencia para cotizar, nunca reescribe un costo.
                            ->helperText('Solo referencia. El costo real se pacta en cada OT y queda congelado allí.'),
                    ]),

                Section::make('Contacto')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_name')
                            ->label('Persona de contacto')
                            ->maxLength(120),
                        TextInput::make('contact_phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(40),
                        TextInput::make('contact_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(150),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
