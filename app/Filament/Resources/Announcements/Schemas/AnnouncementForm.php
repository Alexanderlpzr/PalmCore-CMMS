<?php

namespace App\Filament\Resources\Announcements\Schemas;

use App\Domain\Home\Enums\AnnouncementCategory;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AnnouncementForm
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
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('subtitle')
                            ->label('Subtítulo')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('body')
                            ->label('Cuerpo')
                            ->maxLength(2000)
                            ->rows(4)
                            ->columnSpanFull(),
                        Select::make('category')
                            ->label('Categoría')
                            ->options(AnnouncementCategory::options())
                            ->default(AnnouncementCategory::News->value)
                            ->required(),
                        FileUpload::make('image_path')
                            ->label('Imagen')
                            ->image()
                            ->disk('public')
                            ->directory('announcements')
                            ->columnSpanFull(),
                        TextInput::make('button_label')
                            ->label('Texto del botón')
                            ->maxLength(100),
                        TextInput::make('button_url')
                            ->label('URL del botón')
                            ->url()
                            ->maxLength(500),
                    ]),
                Section::make('Publicación')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                        Toggle::make('is_pinned')
                            ->label('Fijado')
                            ->default(false),
                        TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                        DateTimePicker::make('published_at')
                            ->label('Fecha de publicación')
                            ->required()
                            ->default(now())
                            ->seconds(false),
                        DateTimePicker::make('expires_at')
                            ->label('Fecha de expiración')
                            ->nullable()
                            ->seconds(false)
                            ->after('published_at'),
                    ]),
            ]);
    }
}
