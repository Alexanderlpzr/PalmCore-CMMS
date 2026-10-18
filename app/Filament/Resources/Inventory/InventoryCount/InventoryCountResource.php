<?php

namespace App\Filament\Resources\Inventory\InventoryCount;

use App\Domain\Inventory\Enums\InventoryCountScope;
use App\Domain\Inventory\Enums\InventoryCountStatus;
use App\Domain\Inventory\Services\InventoryCountService;
use App\Filament\Resources\Inventory\InventoryCount\Pages\CreateInventoryCount;
use App\Filament\Resources\Inventory\InventoryCount\Pages\ListInventoryCounts;
use App\Filament\Resources\Inventory\InventoryCount\Pages\ViewInventoryCount;
use App\Filament\Resources\Inventory\InventoryCount\RelationManagers\LinesRelationManager;
use App\Models\InventoryCount;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Los conteos de inventario: el conteo cíclico por ABC, el de una ubicación o el de todo
 * el almacén. Se cuenta sin ver lo que dice el sistema y al aplicarlo cada diferencia
 * queda como ajuste en el kardex.
 */
class InventoryCountResource extends Resource
{
    protected static ?string $model = InventoryCount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'Conteo';

    protected static ?string $pluralModelLabel = 'Conteos';

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?int $navigationSort = 5;

    protected static bool $isScopedToTenant = true;

    protected static ?string $slug = 'conteos';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Qué se va a contar')
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    Select::make('warehouse_id')
                        ->label('Almacén')
                        ->options(fn (): array => Warehouse::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn (): ?string => Warehouse::query()->where('is_active', true)->count() === 1 ? Warehouse::query()->where('is_active', true)->value('id') : null)
                        ->live()
                        ->required(),
                    Select::make('scope')
                        ->label('Alcance')
                        ->options(InventoryCountScope::options())
                        ->default(InventoryCountScope::Due->value)
                        ->helperText('Cíclico: los A cada mes, los B cada trimestre, los C cada semestre, y los que nunca se han contado.')
                        ->live()
                        ->required(),
                    Select::make('location_filter')
                        ->label('Ubicación')
                        ->options(fn (Get $get): array => filled($get('warehouse_id'))
                            ? app(InventoryCountService::class)->locations(Warehouse::query()->findOrFail($get('warehouse_id')))->mapWithKeys(fn (string $bin): array => [$bin => $bin])->all()
                            : [])
                        ->visible(fn (Get $get): bool => $get('scope') === InventoryCountScope::Location->value)
                        ->required(fn (Get $get): bool => $get('scope') === InventoryCountScope::Location->value),
                    Textarea::make('notes')
                        ->label('Observación (opcional)')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 2, 'lg' => 4])
                ->schema([
                    TextEntry::make('count_number')->label('Conteo'),
                    TextEntry::make('warehouse.name')->label('Almacén'),
                    TextEntry::make('scope')->label('Alcance')
                        ->formatStateUsing(fn (InventoryCount $record): string => $record->scope->label().($record->location_filter ? ': '.$record->location_filter : '')),
                    TextEntry::make('status')->label('Estado')->badge()
                        ->formatStateUsing(fn (InventoryCountStatus $state): string => $state->label())
                        ->color(fn (InventoryCountStatus $state): string => $state->color()),
                    TextEntry::make('createdBy.name')->label('Lo abrió'),
                    TextEntry::make('created_at')->label('Abierto')->dateTime('d/m/Y h:i a'),
                    TextEntry::make('appliedBy.name')->label('Lo aplicó')->placeholder('—'),
                    TextEntry::make('applied_at')->label('Aplicado')->dateTime('d/m/Y h:i a')->placeholder('—'),
                    TextEntry::make('notes')->label('Observación')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('count_number')->label('Conteo')->searchable(),
                TextColumn::make('warehouse.name')->label('Almacén'),
                TextColumn::make('scope')->label('Alcance')
                    ->formatStateUsing(fn (InventoryCount $record): string => $record->scope->label().($record->location_filter ? ': '.$record->location_filter : '')),
                TextColumn::make('lines_count')->counts('lines')->label('Repuestos')->alignEnd(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (InventoryCountStatus $state): string => $state->label())
                    ->color(fn (InventoryCountStatus $state): string => $state->color()),
                TextColumn::make('created_at')->label('Abierto')->dateTime('d/m/Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(InventoryCountStatus::options()),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Todavía no hay conteos')
            ->emptyStateDescription('Abra uno para contar lo que toca esta semana, una ubicación o todo el almacén.');
    }

    public static function getRelations(): array
    {
        return [
            'lines' => LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryCounts::route('/'),
            'create' => CreateInventoryCount::route('/create'),
            'view' => ViewInventoryCount::route('/{record}'),
        ];
    }
}
