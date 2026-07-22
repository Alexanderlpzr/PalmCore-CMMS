<?php

namespace App\Filament\Resources\Contractors;

use App\Filament\Resources\Contractors\Pages\CreateContractor;
use App\Filament\Resources\Contractors\Pages\EditContractor;
use App\Filament\Resources\Contractors\Pages\ListContractors;
use App\Filament\Resources\Contractors\Schemas\ContractorForm;
use App\Filament\Resources\Contractors\Tables\ContractorsTable;
use App\Models\Contractor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ContractorResource extends Resource
{
    protected static ?string $model = Contractor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'Contratista';

    protected static ?string $pluralModelLabel = 'Contratistas';

    protected static string|UnitEnum|null $navigationGroup = 'Gestión de Activos';

    protected static ?int $navigationSort = 4;

    protected static bool $isScopedToTenant = true;

    public static function shouldRegisterNavigation(): bool
    {
        // Oculto para los roles de tenant; solo el superadministrador de plataforma lo ve.
        return auth()->user()?->is_super_admin ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return ContractorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContractorsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContractors::route('/'),
            'create' => CreateContractor::route('/create'),
            'edit' => EditContractor::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
