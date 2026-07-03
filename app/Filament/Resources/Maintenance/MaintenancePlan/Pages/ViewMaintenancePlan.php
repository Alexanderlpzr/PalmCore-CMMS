<?php

namespace App\Filament\Resources\Maintenance\MaintenancePlan\Pages;

use App\Domain\Maintenance\Services\MaintenancePlanService;
use App\Domain\Reports\DTOs\ReportRequest;
use App\Domain\Reports\Enums\ReportType;
use App\Domain\Reports\Services\ReportManager;
use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Maintenance\MaintenancePlan\MaintenancePlanResource;
use App\Models\MaintenancePlan;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewMaintenancePlan extends ViewRecord
{
    use HasBackAction;

    protected static string $resource = MaintenancePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('activate')
                ->label('Activar plan')
                ->icon(Heroicon::OutlinedPlay)
                ->color('success')
                ->modalHeading('Activar Plan de Mantenimiento')
                ->form(fn (): array => $this->buildActivationForm())
                ->visible(fn (): bool => ! $this->record->is_active)
                ->action(function (array $data, MaintenancePlanService $service): void {
                    /** @var MaintenancePlan $plan */
                    $plan = $this->record;

                    $firstDueAt = isset($data['first_due_at'])
                        ? Carbon::parse($data['first_due_at'])
                        : null;

                    $firstDueMeter = isset($data['first_due_meter']) && $data['first_due_meter'] !== null
                        ? (float) $data['first_due_meter']
                        : null;

                    $service->activate($plan, $firstDueAt, $firstDueMeter);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Plan activado correctamente')
                        ->success()
                        ->send();
                }),

            Action::make('deactivate')
                ->label('Desactivar')
                ->icon(Heroicon::OutlinedPause)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('¿Desactivar plan?')
                ->modalDescription('El plan dejará de generar vencimientos. Puede reactivarse en cualquier momento.')
                ->visible(fn (): bool => $this->record->is_active)
                ->action(function (): void {
                    $this->record->update(['is_active' => false]);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Plan desactivado')
                        ->warning()
                        ->send();
                }),

            Action::make('download_pdf')
                ->label('Descargar PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (ReportManager $manager): mixed {
                    /** @var MaintenancePlan $plan */
                    $plan = $this->record;

                    return $manager->streamDownload(new ReportRequest(
                        type: ReportType::MaintenancePlan,
                        tenantId: Filament::getTenant()->id,
                        requestedBy: auth()->id(),
                        recordId: $plan->id,
                    ));
                }),

            EditAction::make(),
            DeleteAction::make(),
            $this->getBackAction(),
        ];
    }

    private function buildActivationForm(): array
    {
        /** @var MaintenancePlan $plan */
        $plan = $this->record;

        $fields = [];

        if ($plan->trigger_source->requiresTimeFrequency()) {
            $fields[] = DateTimePicker::make('first_due_at')
                ->label('Primer vencimiento (fecha)')
                ->displayFormat('d/m/Y H:i')
                ->required();
        }

        if ($plan->trigger_source->requiresMeterInterval()) {
            $fields[] = TextInput::make('first_due_meter')
                ->label('Primer vencimiento (horómetro)')
                ->numeric()
                ->minValue(0)
                ->suffix('h')
                ->required();
        }

        return $fields;
    }
}
