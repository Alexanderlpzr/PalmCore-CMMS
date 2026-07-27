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
use Filament\Support\RawJs;

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
                    ...$this->costFields(),
                ])
                ->visible(fn (): bool => ! $this->record->status->isTerminal()
                    && auth()->user()->can('work-orders.close'))
                ->action(function (array $data, WorkOrderService $service): void {
                    $labor = WorkOrderService::normalizeCost($data['actual_cost_labor'] ?? null);
                    $parts = WorkOrderService::normalizeCost($data['actual_cost_parts'] ?? null);
                    $consumables = WorkOrderService::normalizeCost($data['actual_cost_consumables'] ?? null);
                    $total = ($labor ?? 0) + ($parts ?? 0) + ($consumables ?? 0);

                    $data['actual_cost_labor'] = $labor;
                    $data['actual_cost_parts'] = $parts;
                    $data['actual_cost_consumables'] = $consumables;
                    $data['actual_cost_total'] = $total > 0 ? round($total, 2) : null;

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

            // Ajuste manual del costo (work-orders.update — admin/supervisor, no
            // técnico). Desglosado en mano de obra / repuestos / consumibles.
            // Disponible en cualquier estado, porque el costo se suele conocer al
            // terminar.
            Action::make('edit_costs')
                ->label('Editar Costo')
                ->tooltip('Ajusta manualmente el costo de esta OT')
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()->can('work-orders.update'))
                ->modalHeading('Editar Costo')
                ->form($this->costFields())
                ->action(function (array $data, WorkOrderService $service): void {
                    $service->updateCosts($this->record, $data);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Costo actualizado')
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

    /**
     * Los tres campos de costo (mano de obra / repuestos / consumibles) más un
     * total de solo lectura que se recalcula solo — se comparten entre el modal
     * de cierre y «Editar Costo» para no mantener dos copias del mismo desglose.
     *
     * @return array<int, TextInput>
     */
    private function costFields(): array
    {
        $recompute = function (Get $get, Set $set): void {
            $total = (float) ($get('actual_cost_labor') ?? 0)
                + (float) ($get('actual_cost_parts') ?? 0)
                + (float) ($get('actual_cost_consumables') ?? 0);

            $set('cost_total_preview', round($total, 2));
        };

        return [
            TextInput::make('actual_cost_labor')
                ->label('Mano de obra')
                ->numeric()
                ->minValue(0)
                ->prefix('$')
                // Formatea con separador de miles mientras se escribe (COP suele
                // teclearse "150.000" o "150,000") y lo limpia antes de guardar —
                // sin esto un separador tecleado rompe el cast numérico y el valor
                // llega mal (o en 0) al servidor sin ningún aviso.
                ->mask(RawJs::make('$money($input)'))
                ->stripCharacters(',')
                ->default(fn (): ?float => $this->record->actual_cost_labor)
                ->live(onBlur: true)
                ->afterStateUpdated($recompute),
            TextInput::make('actual_cost_parts')
                ->label('Repuesto')
                ->numeric()
                ->minValue(0)
                ->prefix('$')
                ->mask(RawJs::make('$money($input)'))
                ->stripCharacters(',')
                ->default(fn (): ?float => $this->record->actual_cost_parts)
                ->live(onBlur: true)
                ->afterStateUpdated($recompute),
            TextInput::make('actual_cost_consumables')
                ->label('Consumible')
                ->helperText('Lubricantes, filtros, empaques — lo que se consume y no es repuesto ni mano de obra.')
                ->numeric()
                ->minValue(0)
                ->prefix('$')
                ->mask(RawJs::make('$money($input)'))
                ->stripCharacters(',')
                ->default(fn (): ?float => $this->record->actual_cost_consumables)
                ->live(onBlur: true)
                ->afterStateUpdated($recompute),
            TextInput::make('cost_total_preview')
                ->label('Total')
                ->helperText('Se calcula solo, sumando los tres. Alimenta el gasto de presupuesto al cerrar.')
                ->numeric()
                ->prefix('$')
                ->disabled()
                ->dehydrated(false)
                ->default(fn (): float => $this->record->totalActualCost()),
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
