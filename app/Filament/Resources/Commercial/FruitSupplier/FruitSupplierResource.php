<?php

namespace App\Filament\Resources\Commercial\FruitSupplier;

use App\Filament\Resources\Commercial\FruitSupplier\Pages\ManageFruitSuppliers;
use App\Models\FruitSupplier;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
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

/** Los proveedores de fruta de palma, en el orden en que aparecen en el plan. */
class FruitSupplierResource extends Resource
{
    protected static ?string $model = FruitSupplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'Proveedor de fruta';

    protected static ?string $pluralModelLabel = 'Proveedores de fruta';

    protected static string|UnitEnum|null $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = 7;

    protected static bool $isScopedToTenant = true;

    protected static ?string $slug = 'proveedores-de-fruta';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'sm' => 2])->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(150)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('tenant_id', filament()->getTenant()?->getKey())->whereNull('deleted_at')),
            TextInput::make('sort_order')
                ->label('Orden en el plan')
                ->numeric()
                ->minValue(0)
                ->default(0),
            Toggle::make('is_active')
                ->label('Activo')
                ->helperText('Los inactivos no salen en el plan ni en la fruta recibida, salvo que tengan cifras.')
                ->default(true),
            TagsInput::make('aliases')
                ->label('Otros nombres')
                ->helperText('Cómo aparece en otros archivos. El importador de Excel los reconoce.')
                ->columnSpanFull(),
            Textarea::make('notes')->label('Notas')->rows(2)->maxLength(2000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Proveedor')->searchable()->sortable(),
                TextColumn::make('sort_order')->label('Orden')->numeric()->alignEnd()->sortable(),
                IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make()])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Todavía no hay proveedores de fruta')
            ->emptyStateDescription('Créelos aquí o importe el plan del Excel en «Plan anual».');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFruitSuppliers::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
