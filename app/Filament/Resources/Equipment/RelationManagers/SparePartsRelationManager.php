<?php

namespace App\Filament\Resources\Equipment\RelationManagers;

use App\Models\EquipmentSparePart;
use App\Models\SparePart;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Listado de repuestos del equipo. Solo se anota qué repuesto lleva; el nombre
 * es lo único obligatorio.
 *
 * No lleva existencias ni horas: para el stock está el almacén (SparePart) y
 * para las piezas con vida útil está la pestaña Piezas.
 */
class SparePartsRelationManager extends RelationManager
{
    protected static string $relationship = 'spareParts';

    protected static ?string $title = 'Repuestos';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->spareParts()->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Se puede anotar un repuesto sin salir de la ficha del equipo.
     *
     * Filament deja los relation managers de solo lectura en la página «Ver», que
     * es justo donde el mecánico está mirando el equipo cuando se acuerda del
     * repuesto. Mandarlo a «Editar» por escribir un nombre sobra. Los permisos
     * los sigue aplicando EquipmentSparePartPolicy.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Opcional: enlazarlo al repuesto del almacén deja ver cuánto hay y, en la
                // ficha del repuesto, en qué equipos va.
                Select::make('spare_part_id')
                    ->label('Repuesto del almacén')
                    ->helperText('Opcional. Si ya está en el almacén, elíjalo y se llenan los datos.')
                    ->options(fn (): array => SparePart::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])
                        ->mapWithKeys(fn (SparePart $part): array => [$part->id => "{$part->code} · {$part->name}"])->all())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        $part = $state ? SparePart::query()->find($state) : null;

                        if ($part === null) {
                            return;
                        }

                        if (blank($get('name'))) {
                            $set('name', $part->name);
                        }

                        if (blank($get('part_number'))) {
                            $set('part_number', $part->code);
                        }
                    })
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->label('Nombre del repuesto')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('part_number')
                    ->label('Referencia')
                    ->helperText('Opcional. El número de parte con el que se pide.')
                    ->maxLength(100),
                TextInput::make('unit_cost')
                    ->label('Costo')
                    ->helperText('Opcional. Lo que cuesta reponerlo.')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$')
                    // Sin la máscara, teclear «150.000» rompe el cast numérico y el
                    // valor llega mal (o en 0) sin ningún aviso.
                    ->mask(RawJs::make('$money($input)'))
                    ->stripCharacters(','),
                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['sparePart' => fn ($part) => $part->withSum('warehouseStock', 'current_stock')]))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Repuesto')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('part_number')
                    ->label('Referencia')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('in_stock')
                    ->label('En almacén')
                    ->state(fn (EquipmentSparePart $record): ?float => $record->sparePart?->totalStock())
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->placeholder('Sin enlazar'),
                // Solo el dato: cuánto cuesta el repuesto. No suma ni alimenta nada.
                TextColumn::make('unit_cost')
                    ->label('Costo')
                    ->money(fn (): string => $this->getOwnerRecord()->currency_code ?? 'COP')
                    ->alignEnd()
                    ->placeholder('—'),
                TextColumn::make('notes')
                    ->label('Notas')
                    ->limitWithTooltip(60)
                    ->placeholder('—'),
            ])
            ->emptyStateHeading('Sin repuestos registrados')
            ->emptyStateDescription('Anota los repuestos que lleva este equipo para saber qué pedir cuando haga falta.')
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar repuesto')
                    ->tooltip('Anotar un repuesto que lleva este equipo')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = Filament::getTenant()->id;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->tooltip('Editar este repuesto'),
                DeleteAction::make()
                    ->tooltip('Quitar este repuesto de la lista'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
