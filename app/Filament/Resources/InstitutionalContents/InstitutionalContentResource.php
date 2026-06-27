<?php

namespace App\Filament\Resources\InstitutionalContents;

use App\Filament\Resources\InstitutionalContents\Pages\CreateInstitutionalContent;
use App\Filament\Resources\InstitutionalContents\Pages\EditInstitutionalContent;
use App\Filament\Resources\InstitutionalContents\Pages\ListInstitutionalContents;
use App\Filament\Resources\InstitutionalContents\Schemas\InstitutionalContentForm;
use App\Filament\Resources\InstitutionalContents\Tables\InstitutionalContentsTable;
use App\Models\InstitutionalContent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class InstitutionalContentResource extends Resource
{
    protected static ?string $model = InstitutionalContent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?string $navigationLabel = 'Contenido CMS';

    protected static ?string $modelLabel = 'Contenido';

    protected static ?string $pluralModelLabel = 'Contenido Institucional';

    protected static bool $isScopedToTenant = false;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->is_super_admin ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->is_super_admin ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->is_super_admin ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->is_super_admin ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->is_super_admin ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return InstitutionalContentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstitutionalContentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstitutionalContents::route('/'),
            'create' => CreateInstitutionalContent::route('/create'),
            'edit' => EditInstitutionalContent::route('/{record}/edit'),
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
