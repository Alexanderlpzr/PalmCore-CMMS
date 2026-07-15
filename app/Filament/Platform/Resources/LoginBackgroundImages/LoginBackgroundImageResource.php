<?php

namespace App\Filament\Platform\Resources\LoginBackgroundImages;

use App\Filament\Platform\Resources\LoginBackgroundImages\Pages\CreateLoginBackgroundImage;
use App\Filament\Platform\Resources\LoginBackgroundImages\Pages\EditLoginBackgroundImage;
use App\Filament\Platform\Resources\LoginBackgroundImages\Pages\ListLoginBackgroundImages;
use App\Filament\Platform\Resources\LoginBackgroundImages\Schemas\LoginBackgroundImageForm;
use App\Filament\Platform\Resources\LoginBackgroundImages\Tables\LoginBackgroundImagesTable;
use App\Models\LoginBackgroundImage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class LoginBackgroundImageResource extends Resource
{
    protected static ?string $model = LoginBackgroundImage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Carrusel de Login';

    protected static ?string $modelLabel = 'Imagen';

    protected static ?string $pluralModelLabel = 'Carrusel de Login';

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->is_super_admin ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return LoginBackgroundImageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoginBackgroundImagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoginBackgroundImages::route('/'),
            'create' => CreateLoginBackgroundImage::route('/create'),
            'edit' => EditLoginBackgroundImage::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
