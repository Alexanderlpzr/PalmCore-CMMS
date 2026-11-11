<?php

namespace App\Observers;

use App\Domain\Alerts\Data\CreateAlertData;
use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Services\AlertService;
use App\Domain\Assets\Enums\IssueSeverity;
use App\Domain\Maintenance\Enums\IssueReportStatus;
use App\Domain\Notifications\InboxNotification;
use App\Domain\Notifications\NotificationKind;
use App\Domain\Notifications\Recipients;
use App\Domain\Safety\Enums\FindingOrigin;
use App\Domain\Safety\Services\SafetyFindingService;
use App\Filament\Resources\Maintenance\IssueReport\IssueReportResource;
use App\Models\Equipment;
use App\Models\EquipmentIssueReport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Throwable;

class EquipmentIssueReportObserver
{
    public function created(EquipmentIssueReport $equipmentIssueReport): void
    {
        Cache::forget("home:{$equipmentIssueReport->tenant_id}:attention");

        $this->raiseAlert($equipmentIssueReport);
        $this->notifyMaintenance($equipmentIssueReport);
    }

    /**
     * A la campana de mantenimiento y de la administración: hasta ahora un reporte de
     * novedad solo entraba al Centro de Alertas y nadie se enteraba si no lo abría. Los de
     * SST no: el hallazgo ya les avisó, con más detalle.
     */
    private function notifyMaintenance(EquipmentIssueReport $report): void
    {
        if (in_array($report->reporter_position, FindingOrigin::reporterPositions(), true)) {
            return;
        }

        $recipients = Recipients::withRoles($report->tenant_id, ['mantenimiento', 'administrador-general'])
            ->reject(fn (User $user): bool => $user->getKey() === $report->reporter_user_id);

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            $url = IssueReportResource::getUrl('view', ['record' => $report], panel: 'admin', tenant: Tenant::query()->find($report->tenant_id));
        } catch (Throwable) {
            $url = null;
        }

        $equipment = Equipment::withoutGlobalScopes()->find($report->equipment_id);

        Notification::send($recipients, new InboxNotification(
            kind: NotificationKind::IssueReport,
            title: 'Reporte de novedad: '.($equipment?->name ?? 'equipo'),
            body: sprintf('%s reportó: %s', $report->reporter_name ?: 'Alguien', str($report->description ?? 'sin descripción')->limit(160)),
            status: match ($report->severity) {
                IssueSeverity::Critical, IssueSeverity::High => 'danger',
                IssueSeverity::Medium => 'warning',
                default => 'info',
            },
            url: $url,
            tenantId: $report->tenant_id,
            actionLabel: 'Ver reporte',
        ));
    }

    public function updated(EquipmentIssueReport $equipmentIssueReport): void
    {
        if ($equipmentIssueReport->wasChanged('status')) {
            Cache::forget("home:{$equipmentIssueReport->tenant_id}:attention");

            // Ya lo atendió alguien: la alerta deja de tener sentido. Sin esto el
            // Centro de Alertas se llena de avisos de reportes ya resueltos y deja
            // de servir para ver lo que falta.
            if ($equipmentIssueReport->status !== IssueReportStatus::Open) {
                app(AlertService::class)->autoResolveForEntity(
                    $equipmentIssueReport->tenant_id,
                    EquipmentIssueReport::class,
                    $equipmentIssueReport->id,
                    'Reporte de novedad',
                );
            }

            // Si viene de un hallazgo de SST, su parte de mantenimiento se mueve con él:
            // OT creada → «En ejecución»; OT cerrada → «Ejecutado».
            app(SafetyFindingService::class)->syncFromIssueReport($equipmentIssueReport);
        }
    }

    public function deleted(EquipmentIssueReport $equipmentIssueReport): void
    {
        Cache::forget("home:{$equipmentIssueReport->tenant_id}:attention");

        app(AlertService::class)->autoResolveForEntity(
            $equipmentIssueReport->tenant_id,
            EquipmentIssueReport::class,
            $equipmentIssueReport->id,
            'Reporte de novedad',
        );
    }

    /**
     * Un reporte nuevo entra al Centro de Alertas para que no dependa de que
     * alguien entre a la pantalla de reportes a mirarlo.
     */
    private function raiseAlert(EquipmentIssueReport $report): void
    {
        $equipment = $report->equipment;

        app(AlertService::class)->create(new CreateAlertData(
            tenantId: $report->tenant_id,
            severity: $this->alertSeverity($report->severity),
            category: AlertCategory::Maintenance,
            title: 'Reporte de novedad pendiente: '.($equipment?->name ?? 'equipo sin nombre'),
            message: sprintf(
                '%s reportó: %s%s',
                $report->reporter_name ?: 'Alguien',
                str($report->description ?? 'sin descripción')->limit(160),
                $equipment?->code ? " (equipo {$equipment->code})" : '',
            ),
            entityType: EquipmentIssueReport::class,
            entityId: $report->id,
            metadata: [
                'issue_report_id' => $report->id,
                'equipment_id' => $report->equipment_id,
                'equipment_code' => $equipment?->code,
                'severity' => $report->severity?->value,
                'reporter_name' => $report->reporter_name,
            ],
        ));
    }

    private function alertSeverity(?IssueSeverity $severity): AlertSeverity
    {
        return match ($severity) {
            IssueSeverity::Critical, IssueSeverity::High => AlertSeverity::Critical,
            IssueSeverity::Medium => AlertSeverity::Warning,
            default => AlertSeverity::Info,
        };
    }
}
