<?php

namespace App\Filament\Resources\Commercial\Customer;

use App\Domain\Commercial\Enums\Market;
use App\Filament\Resources\Commercial\Customer\Pages\ManageCustomers;
use App\Models\Customer;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/** Los clientes que compran aceite y palmiste, con su destino: nacional o exportación. */
class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    protected static string|UnitEnum|null $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = 3;

    protected static bool $isScopedToTenant = true;

    protected static ?string $slug = 'clientes';

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
            Select::make('market')
                ->label('Destino')
                ->options(Market::options())
                ->default(Market::Nacional->value)
                ->selectablePlaceholder(false)
                ->required(),
            TextInput::make('tax_id')
                ->label('NIT')
                ->maxLength(30),
            Toggle::make('is_active')
                ->label('Activo')
                ->default(true)
                ->inline(false),
            TagsInput::make('aliases')
                ->label('Otros nombres')
                ->placeholder('Bio D, Altamira…')
                ->helperText('Cómo aparece en otros archivos. El importador de Excel los reconoce como este cliente.')
                ->columnSpanFull(),
            Textarea::make('notes')
                ->label('Notas')
                ->rows(2)
                ->maxLength(2000)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Cliente')->searchable()->sortable(),
                TextColumn::make('market')->label('Destino')
                    ->formatStateUsing(fn (Market $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('tax_id')->label('NIT')->placeholder('—')->toggleable(),
                TextColumn::make('offers_count')->label('Ofertas')->counts('offers')->alignEnd()->sortable(),
                TextColumn::make('dispatches_count')->label('Despachos')->counts('dispatches')->alignEnd()->sortable(),
                IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->filters([
                SelectFilter::make('market')->label('Destino')->options(Market::options()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('Todavía no hay clientes')
            ->emptyStateDescription('Créelos aquí o importe el Excel de despachos: los clientes se crean solos.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCustomers::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
