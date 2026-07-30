<?php

namespace App\Observers;

use App\Domain\Alerts\Data\CreateAlertData;
use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Services\AlertService;
use App\Domain\Assets\Enums\IssueSeverity;
use App\Domain\Maintenance\Enums\IssueReportStatus;
use App\Models\EquipmentIssueReport;
use Illuminate\Support\Facades\Cache;

class EquipmentIssueReportObserver
{
    public function created(EquipmentIssueReport $equipmentIssueReport): void
    {
        Cache::forget("home:{$equipmentIssueReport->tenant_id}:attention");

        $this->raiseAlert($equipmentIssueReport);
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
