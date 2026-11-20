<?php

namespace App\Filament\Resources\Commercial\OilTank;

use App\Filament\Resources\Commercial\OilTank\Pages\ManageOilTanks;
use App\Models\OilTank;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/** Los tanques de aceite de la planta. Los administra la Directora; producción solo los mide. */
class OilTankResource extends Resource
{
    protected static ?string $model = OilTank::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $modelLabel = 'Tanque';

    protected static ?string $pluralModelLabel = 'Tanques';

    protected static string|UnitEnum|null $navigationGroup = 'Producción';

    protected static ?int $navigationSort = 4;

    protected static bool $isScopedToTenant = true;

    protected static ?string $slug = 'tanques';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canCreate();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'sm' => 2])->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('tenant_id', filament()->getTenant()?->getKey())->whereNull('deleted_at')),
            TextInput::make('capacity_tons')
                ->label('Capacidad')
                ->numeric()
                ->minValue(0)
                ->suffix('tn'),
            TextInput::make('sort_order')
                ->label('Orden')
                ->numeric()
                ->minValue(0)
                ->default(0),
            Toggle::make('is_active')
                ->label('Activo')
                ->helperText('Los inactivos ya no se miden.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Tanque')->searchable()->sortable(),
                TextColumn::make('capacity_tons')->label('Capacidad (tn)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('readings_count')->label('Mediciones')->counts('readings')->alignEnd(),
                IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make()])
            ->defaultSort('sort_order')
            ->emptyStateHeading('Todavía no hay tanques')
            ->emptyStateDescription('Créelos con su nombre y su capacidad para empezar a medirlos.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOilTanks::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
