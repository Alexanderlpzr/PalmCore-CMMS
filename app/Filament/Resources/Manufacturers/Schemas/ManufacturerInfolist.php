<?php

namespace App\Filament\Resources\Manufacturers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ManufacturerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Fabricante')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Código'),
                        TextEntry::make('name')
                            ->label('Nombre'),
                        TextEntry::make('country_code')
                            ->label('País')
                            ->placeholder('—'),
                        TextEntry::make('website')
                            ->label('Sitio web')
                            ->url()
                            ->placeholder('—'),
                    ]),

                Section::make('Contacto')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('contact_email')
                            ->label('Email')
                            ->placeholder('—'),
                        TextEntry::make('contact_phone')
                            ->label('Teléfono')
                            ->placeholder('—'),
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->columnSpanFull()
                            ->placeholder('Sin notas'),
                        IconEntry::make('is_active')
                            ->label('Activo')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->label('Creado')
                            ->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }
}
