<?php

namespace App\Filament\Resources\CarouselSlides\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CarouselSlideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contenido')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Título')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('subtitle')
                            ->label('Subtítulo')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),
                        FileUpload::make('image_path')
                            ->label('Imagen')
                            ->image()
                            ->disk('public')
                            ->directory('carousel')
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('16:9')
                            ->columnSpanFull(),
                        TextInput::make('button_label')
                            ->label('Texto del botón')
                            ->maxLength(100),
                        TextInput::make('button_url')
                            ->label('URL del botón')
                            ->url()
                            ->maxLength(500),
                    ]),
                Section::make('Configuración')
                    ->columns(2)
                    ->schema([
                        TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                        DateTimePicker::make('starts_at')
                            ->label('Inicio de publicación')
                            ->nullable()
                            ->seconds(false),
                        DateTimePicker::make('ends_at')
                            ->label('Fin de publicación')
                            ->nullable()
                            ->seconds(false)
                            ->after('starts_at'),
                    ]),
            ]);
    }
}
