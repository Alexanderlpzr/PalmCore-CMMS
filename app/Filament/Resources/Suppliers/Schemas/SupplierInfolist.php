<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SupplierInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Proveedor')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Código'),
                        TextEntry::make('name')
                            ->label('Nombre'),
                        TextEntry::make('tax_id')
                            ->label('RUC / NIT')
                            ->placeholder('—'),
                        TextEntry::make('country_code')
                            ->label('País')
                            ->placeholder('—'),
                    ]),

                Section::make('Contacto')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('contact_name')
                            ->label('Persona de contacto')
                            ->placeholder('—'),
                        TextEntry::make('contact_email')
                            ->label('Email')
                            ->placeholder('—'),
                        TextEntry::make('contact_phone')
                            ->label('Teléfono')
                            ->placeholder('—'),
                        TextEntry::make('city')
                            ->label('Ciudad')
                            ->placeholder('—'),
                        TextEntry::make('address')
                            ->label('Dirección')
                            ->columnSpanFull()
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
