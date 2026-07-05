<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\RelationManagers;

use App\Domain\Maintenance\Enums\WorkOrderPartStatus;
use App\Domain\Maintenance\Services\WorkOrderInventoryService;
use App\Models\SparePart;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
use App\Models\WorkOrderPart;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class PartsRelationManager extends RelationManager
{
    protected static string $relationship = 'parts';

    protected static ?string $title = 'Repuestos';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Repuesto')
                ->columns(2)
                ->schema([
                    TextInput::make('part_code')
                        ->label('Código')
                        ->maxLength(255),
                    Select::make('unit')
                        ->label('Unidad')
                        ->options([
                            'pcs' => 'Piezas',
                            'kg' => 'Kilogramos',
                            'l' => 'Litros',
                            'm' => 'Metros',
                            'hr' => 'Horas',
                        ])
                        ->default('pcs'),
                    TextInput::make('description')
                        ->label('Descripción')
                        ->maxLength(500)
                        ->columnSpanFull(),
                    TextInput::make('quantity')
                        ->label('Cantidad')
                        ->numeric()
                        ->required()
                        ->minValue(0.0001)
                        ->default(1)
                        ->live(),
                    TextInput::make('unit_cost')
                        ->label('Costo unitario')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('$')
                        ->helperText('Solo para repuestos sin enlace de inventario.'),
                ]),

            Section::make('Enlace de Inventario')
                ->description('Opcional. Vincula este repuesto al módulo de inventario para reserva y trazabilidad de stock.')
                ->columns(2)
                ->schema([
                    Select::make('spare_part_id')
                        ->label('Repuesto (inventario)')
                        ->options(fn (): array => SparePart::where('is_active', true)
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn (SparePart $sp): array => [$sp->id => "{$sp->code} — {$sp->name}"])
                            ->toArray()
                        )
                        ->searchable()
                        ->nullable()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('warehouse_id', null);
                            $set('unit_cost_snapshot', null);
                        }),

                    Select::make('warehouse_id')
                        ->label('Almacén')
                        ->options(fn (): array => Warehouse::where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray()
                        )
                        ->searchable()
                        ->nullable()
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                            $sparePartId = $get('spare_part_id');

                            if ($state !== null && $sparePartId !== null) {
                                $wsp = WarehouseSparePart::where('warehouse_id', $state)
                                    ->where('spare_part_id', $sparePartId)
                                    ->first();

                                $set('unit_cost_snapshot', $wsp?->average_unit_cost);
                            } else {
                                $set('unit_cost_snapshot', null);
                            }
                        }),

                    TextInput::make('unit_cost_snapshot')
                        ->label('Costo snapshot')
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->prefix('$')
                        ->helperText('Se congela desde el costo promedio del almacén al crear. No se modifica después.')
                        ->nullable(),

                    Placeholder::make('available_stock_info')
                        ->label('Stock disponible')
                        ->content(function (Get $get): HtmlString|string {
                            $sparePartId = $get('spare_part_id');
                            $warehouseId = $get('warehouse_id');
                            $quantity = (float) ($get('quantity') ?? 0);

                            if ($sparePartId === null || $warehouseId === null) {
                                return '—';
                            }

                            $wsp = WarehouseSparePart::where('warehouse_id', $warehouseId)
                                ->where('spare_part_id', $sparePartId)
                                ->first();

                            if ($wsp === null) {
                                return new HtmlString('<span class="text-warning-600 font-medium">Sin registro de stock en este almacén</span>');
                            }

                            $available = $wsp->available_stock;

                            if ($quantity > 0 && $available < $quantity) {
                                return new HtmlString(
                                    "<span class=\"text-danger-600 font-medium\">{$available} — ⚠ Stock insuficiente para la cantidad solicitada</span>"
                                );
                            }

                            return new HtmlString("<span class=\"text-success-600 font-medium\">{$available}</span>");
                        }),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('part_code')
                    ->label('Código libre')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sparePart.code')
                    ->label('Repuesto (cód.)')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('sparePart.name')
                    ->label('Repuesto')
                    ->limit(35)
                    ->placeholder('—'),
                TextColumn::make('warehouse.name')
                    ->label('Almacén')
                    ->placeholder('—'),
                TextColumn::make('quantity')
                    ->label('Cant. solicit.')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (WorkOrderPartStatus $state): string => $state->color())
                    ->formatStateUsing(fn (WorkOrderPartStatus $state): string => $state->label()),
                TextColumn::make('reserved_quantity')
                    ->label('Reservado')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('issued_quantity')
                    ->label('Emitido')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('—'),
                TextColumn::make('returned_quantity')
                    ->label('Devuelto')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('unit_cost_snapshot')
                    ->label('Costo snap.')
                    ->money('COP')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_cost')
                    ->label('Total')
                    ->money('COP')
                    ->getStateUsing(fn (WorkOrderPart $record): float => $record->hasInventoryLink()
                        ? round((float) $record->issued_quantity * (float) $record->unit_cost_snapshot, 2)
                        : (float) ($record->total_cost ?? 0)
                    )
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->tooltip('Solicitar un repuesto para esta OT')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = $this->getOwnerRecord()->tenant_id;
                        $data['total_cost'] = isset($data['quantity'], $data['unit_cost'])
                            ? round((float) $data['quantity'] * (float) $data['unit_cost'], 2)
                            : null;

                        return $data;
                    }),
            ])
            ->recordActions([
                // ── Ver detalle ───────────────────────────────────────────────
                Action::make('view_part')
                    ->label('Ver')
                    ->tooltip('Ver el detalle de este repuesto')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading('Detalle del repuesto')
                    ->fillForm(fn (WorkOrderPart $record): array => [
                        'status_label' => $record->status->label(),
                        'warehouse_name' => $record->warehouse?->name ?? '—',
                        'spare_part_display' => $record->sparePart
                            ? "{$record->sparePart->code} — {$record->sparePart->name}"
                            : '—',
                        'issued_quantity' => (string) $record->issued_quantity,
                        'returned_quantity' => (string) $record->returned_quantity,
                        'total_cost_value' => number_format(
                            $record->hasInventoryLink()
                                ? round((float) $record->issued_quantity * (float) $record->unit_cost_snapshot, 2)
                                : (float) ($record->total_cost ?? 0),
                            2
                        ),
                    ])
                    ->schema([
                        TextInput::make('status_label')
                            ->label('Estado')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('spare_part_display')
                            ->label('Repuesto')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('warehouse_name')
                            ->label('Almacén origen')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('issued_quantity')
                            ->label('Cantidad emitida')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('returned_quantity')
                            ->label('Cantidad devuelta')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('total_cost_value')
                            ->label('Costo total (emitido × snapshot)')
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),

                // ── Devolver ──────────────────────────────────────────────────
                Action::make('return_part')
                    ->label('Devolver')
                    ->tooltip('Devolver al almacén el repuesto no utilizado')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('warning')
                    ->modalHeading('Devolver repuesto al almacén')
                    ->visible(fn (WorkOrderPart $record): bool => $record->status === WorkOrderPartStatus::Issued)
                    ->fillForm(fn (WorkOrderPart $record): array => [
                        'remaining_info' => "Máximo a devolver: {$record->remainingToReturn()} "
                            ."(emitido: {$record->issued_quantity}, devuelto: {$record->returned_quantity})",
                    ])
                    ->schema([
                        Placeholder::make('remaining_info')
                            ->label('Disponible para devolver')
                            ->content(fn (Get $get): string => $get('remaining_info') ?? '—'),
                        TextInput::make('return_quantity')
                            ->label('Cantidad a devolver')
                            ->numeric()
                            ->required()
                            ->minValue(0.0001),
                    ])
                    ->action(function (WorkOrderPart $record, array $data, WorkOrderInventoryService $service): void {
                        $returnQty = (float) $data['return_quantity'];

                        if ($returnQty > $record->remainingToReturn()) {
                            Notification::make()
                                ->title("No puede devolver {$returnQty}. Máximo disponible: {$record->remainingToReturn()}.")
                                ->danger()
                                ->send();

                            return;
                        }

                        $service->returnPartFromWorkOrder($record, $returnQty, auth()->user());

                        Notification::make()
                            ->title('Repuesto devuelto correctamente')
                            ->success()
                            ->send();
                    }),

                // ── Editar ────────────────────────────────────────────────────
                EditAction::make()
                    ->tooltip('Editar este repuesto solicitado')
                    ->visible(fn (WorkOrderPart $record): bool => $record->status === WorkOrderPartStatus::Requested)
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['total_cost'] = isset($data['quantity'], $data['unit_cost'])
                            ? round((float) $data['quantity'] * (float) $data['unit_cost'], 2)
                            : null;

                        return $data;
                    }),

                // ── Eliminar ──────────────────────────────────────────────────
                DeleteAction::make()
                    ->tooltip('Eliminar esta solicitud de repuesto')
                    ->visible(fn (WorkOrderPart $record): bool => ! in_array(
                        $record->status,
                        [WorkOrderPartStatus::Issued, WorkOrderPartStatus::Returned],
                        true
                    ))
                    ->before(function (WorkOrderPart $record, WorkOrderInventoryService $service): void {
                        if ($record->status === WorkOrderPartStatus::Reserved) {
                            $service->cancelPart($record, auth()->user());
                        }
                    }),
            ]);
    }
}
