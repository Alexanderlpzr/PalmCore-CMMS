<?php

namespace App\Filament\Resources\InstitutionalContents\Schemas;

use App\Domain\Home\Enums\InstitutionalContentType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstitutionalContentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contenido')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label('Tipo')
                            ->options(InstitutionalContentType::options())
                            ->required()
                            ->default('news'),
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('subtitle')
                            ->label('Subtítulo')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->maxLength(2000)
                            ->rows(3)
                            ->columnSpanFull(),
                        FileUpload::make('image_path')
                            ->label('Imagen')
                            ->image()
                            ->disk('public')
                            ->directory('institutional')
                            ->columnSpanFull(),
                        TextInput::make('button_text')
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
                        TextInput::make('display_order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                        Toggle::make('is_global')
                            ->label('Visible para todos los tenants')
                            ->default(false)
                            ->helperText('Si está activado, todos los tenants verán este contenido. Si no, selecciona los tenants específicos abajo.'),
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

                Section::make('Tenants')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        Select::make('tenants')
                            ->label('Tenants que verán este contenido')
                            ->multiple()
                            ->relationship('tenants', 'name')
                            ->searchable()
                            ->preloadOptionLabels()
                            ->helperText('Solo aplica cuando "Visible para todos los tenants" está desactivado.'),
                    ]),
            ]);
    }
}
