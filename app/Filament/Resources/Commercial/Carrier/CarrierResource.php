<?php

namespace App\Filament\Resources\Commercial\Carrier;

use App\Filament\Resources\Commercial\Carrier\Pages\ManageCarriers;
use App\Models\Carrier;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
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

/** Las transportadoras que llevan los cargues: Suracarga, Solutrans, Summa… */
class CarrierResource extends Resource
{
    protected static ?string $model = Carrier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $modelLabel = 'Transportadora';

    protected static ?string $pluralModelLabel = 'Transportadoras';

    protected static string|UnitEnum|null $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = 6;

    protected static bool $isScopedToTenant = true;

    protected static ?string $slug = 'transportadoras';

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
            TextInput::make('tax_id')
                ->label('NIT')
                ->maxLength(30),
            Toggle::make('is_active')
                ->label('Activa')
                ->default(true),
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
                TextColumn::make('name')->label('Transportadora')->searchable()->sortable(),
                TextColumn::make('tax_id')->label('NIT')->placeholder('—'),
                TextColumn::make('dispatches_count')->label('Despachos')->counts('dispatches')->alignEnd()->sortable(),
                IconColumn::make('is_active')->label('Activa')->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('Todavía no hay transportadoras');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCarriers::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
