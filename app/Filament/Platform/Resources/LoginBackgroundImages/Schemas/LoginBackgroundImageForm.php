<?php

namespace App\Filament\Platform\Resources\LoginBackgroundImages\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LoginBackgroundImageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('image_path')
                    ->label('Imagen')
                    ->image()
                    ->disk(persistent_disk())
                    ->directory('login')
                    ->imageResizeMode('cover')
                    ->imageCropAspectRatio('16:9')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('caption')
                    ->label('Descripción')
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->label('Orden')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Activa')
                    ->default(true),
            ]);
    }
}
