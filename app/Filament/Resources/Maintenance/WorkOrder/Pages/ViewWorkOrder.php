<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Pages;

use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Maintenance\Enums\FailureMode;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Domain\Reports\DTOs\ReportRequest;
use App\Domain\Reports\Enums\ReportType;
use App\Domain\Reports\Services\ReportManager;
use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource;
use App\Models\EquipmentComponent;
use App\Models\WorkOrder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

class ViewWorkOrder extends ViewRecord
{
    use HasBackAction;

    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Cerrar OT: Abierta → Cerrada  (work-orders.close)
            // El flujo se colapsó a «crear y cerrar»: aquí se registra qué se hizo,
            // se clasifica la falla (Tipo I y modo) y se cargan los costos que
            // alimentan el presupuesto y el dashboard. También sirve para cerrar de
            // una las OT heredadas que hayan quedado a medio ciclo.
            Action::make('close')
                ->label('Cerrar OT')
                ->tooltip('Registra el trabajo, la falla y los costos, y cierra la OT')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('success')
                ->modalHeading('Cerrar orden de trabajo')
                ->modalSubmitActionLabel('Cerrar OT')
                ->form([
                    Textarea::make('work_performed')
                        ->label('Trabajo realizado')
                        ->required()
                        ->rows(4),
                    Textarea::make('failure_cause')
                        ->label('Causa de la falla (si aplica)')
                        ->rows(3),
                    Select::make('failure_mode')
                        ->label('Modo de falla')
                        ->helperText('Clasifica la falla para el análisis de Pareto por modo (rodamiento, sello, eléctrico…).')
                        ->options(FailureMode::options())
                        ->searchable()
                        ->native(false)
                        ->visible(fn (): bool => $this->record->work_order_type->registersFailure()),
                    Select::make('diagnosed_stoppage_category')
                        ->label('Tipo I diagnosticado')
                        ->helperText('Reclasifica el paro que esta OT generó. Sin esto queda como «Otro» y ensucia las horas perdidas por causa.')
                        ->options(collect(StoppageCategory::options())
                            ->except(StoppageCategory::Planned->value)
                            ->all())
                        ->default(fn () => $this->record->diagnosed_stoppage_category?->value)
                        ->native(false)
                        ->visible(fn (): bool => $this->record->work_order_type->registersFailure()),
                    Textarea::make('root_cause')
                        ->label('Causa raíz (si aplica)')
                        ->rows(3),
                    TextInput::make('actual_cost_labor')
                        ->label('Costo de mano de obra')
                        ->helperText('Lo que costó la cuadrilla. Alimenta el gasto de presupuesto al cerrar.')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('$')
                        ->default(fn () => $this->record->actual_cost_labor),
                    TextInput::make('actual_cost_parts')
                        ->label('Costo de repuestos')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('$')
                        ->default(fn () => $this->record->actual_cost_parts),
                    TextInput::make('actual_cost_external')
                        ->label('Costo de terceros')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('$')
                        ->default(fn () => $this->record->actual_cost_external),
                ])
                ->visible(fn (): bool => ! $this->record->status->isTerminal()
                    && auth()->user()->can('work-orders.close'))
                ->action(function (array $data, WorkOrderService $service): void {
                    $labor = $data['actual_cost_labor'] !== null ? (float) $data['actual_cost_labor'] : null;
                    $parts = $data['actual_cost_parts'] !== null ? (float) $data['actual_cost_parts'] : null;
                    $external = $data['actual_cost_external'] !== null ? (float) $data['actual_cost_external'] : null;
                    $total = ($labor ?? 0) + ($parts ?? 0) + ($external ?? 0);

                    $data['actual_cost_labor'] = $labor;
                    $data['actual_cost_parts'] = $parts;
                    $data['actual_cost_external'] = $external;
                    $data['actual_cost_total'] = $total > 0 ? $total : null;

                    $this->doTransition($service, WorkOrderStatus::Closed, $data);
                }),

            // Cancel  (work-orders.update)
            Action::make('cancel')
                ->label('Cancelar OT')
                ->tooltip('Cancela la orden de trabajo sin completarla')
                ->icon(Heroicon::OutlinedArchiveBoxXMark)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('¿Cancelar orden de trabajo?')
                ->modalDescription('La OT quedará cancelada. Esta acción no se puede deshacer.')
                ->visible(fn (): bool => ! $this->record->status->isTerminal()
                    && $this->record->status->canTransitionTo(WorkOrderStatus::Cancelled)
                    && auth()->user()->can('work-orders.update'))
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::Cancelled)),

            // Manual cost override (work-orders.update — admin/supervisor/ingeniero,
            // not técnico). The automatic labor/parts calculation depends on data
            // that isn't always complete (técnico hourly_rate, part costs), so this
            // lets whoever's responsible for the OT type the real numbers in
            // directly — available at any status, since costs are usually only
            // known once the work is done.
            Action::make('edit_costs')
                ->label('Editar Costos')
                ->tooltip('Ajusta manualmente los costos de esta OT')
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()->can('work-orders.update'))
                ->fillForm(fn (WorkOrder $record): array => [
                    'estimated_cost' => $record->estimated_cost,
                    'actual_cost_labor' => $record->actual_cost_labor,
                    'actual_cost_parts' => $record->actual_cost_parts,
                    'actual_cost_external' => $record->actual_cost_external,
                ])
                ->modalHeading('Editar Costos')
                ->form([
                    TextInput::make('estimated_cost')
                        ->label('Estimado')
                        ->numeric()
                        ->prefix('$'),
                    TextInput::make('actual_cost_labor')
                        ->label('Mano de obra')
                        ->numeric()
                        ->prefix('$'),
                    TextInput::make('actual_cost_parts')
                        ->label('Repuestos')
                        ->numeric()
                        ->prefix('$')
                        ->live(),
                    Select::make('component_replaced')
                        ->label('Pieza reemplazada')
                        ->helperText('Selecciona una pieza del equipo para sumar su costo registrado a "Repuestos".')
                        ->options(fn (): array => EquipmentComponent::where('equipment_id', $this->record->equipment_id)
                            ->whereNotNull('unit_cost')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (EquipmentComponent $component): array => [
                                $component->id => "{$component->name} — $".number_format((float) $component->unit_cost, 2),
                            ])
                            ->toArray())
                        ->searchable()
                        ->dehydrated(false)
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                            if ($state === null) {
                                return;
                            }

                            $unitCost = EquipmentComponent::find($state)?->unit_cost;

                            if ($unitCost !== null) {
                                $set('actual_cost_parts', round((float) ($get('actual_cost_parts') ?? 0) + (float) $unitCost, 2));
                            }

                            $set('component_replaced', null);
                        }),
                    TextInput::make('actual_cost_external')
                        ->label('Externo')
                        ->numeric()
                        ->prefix('$'),
                ])
                ->action(function (array $data, WorkOrderService $service): void {
                    $service->updateCosts($this->record, $data);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Costos actualizados')
                        ->success()
                        ->send();
                }),

            Action::make('download_pdf')
                ->label('Descargar PDF')
                ->tooltip('Descarga el reporte de esta OT en PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (ReportManager $manager): mixed {
                    /** @var WorkOrder $wo */
                    $wo = $this->record;

                    return $manager->streamDownload(new ReportRequest(
                        type: ReportType::WorkOrder,
                        tenantId: Filament::getTenant()->id,
                        requestedBy: auth()->id(),
                        recordId: $wo->id,
                    ));
                }),

            EditAction::make()
                ->tooltip('Editar los datos de la OT')
                ->visible(fn (): bool => $this->record->isEditable()),
            DeleteAction::make()
                ->tooltip('Eliminar esta orden de trabajo'),
            $this->getBackAction(),
        ];
    }

    private function doTransition(
        WorkOrderService $service,
        WorkOrderStatus $toStatus,
        array $extra = [],
    ): void {
        /** @var WorkOrder $wo */
        $wo = $this->record;

        try {
            $service->transition($wo, $toStatus, auth()->user(), $extra);
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->record->refresh();

        Notification::make()
            ->title('Estado actualizado: '.$toStatus->label())
            ->body($toStatus->isPendingVerification()
                ? 'Tu firma quedó registrada. La OT queda en revisión hasta que el supervisor la verifique.'
                : null)
            ->success()
            ->send();
    }
}
