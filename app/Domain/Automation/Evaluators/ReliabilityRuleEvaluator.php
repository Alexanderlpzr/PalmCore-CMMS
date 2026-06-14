<?php

namespace App\Domain\Automation\Evaluators;

use App\Domain\Alerts\Data\CreateAlertData;
use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Services\AlertService;
use App\Domain\Automation\Enums\AutomationMode;
use App\Domain\Maintenance\Enums\MaintenanceRequestPriority;
use App\Domain\Maintenance\Enums\MaintenanceRequestType;
use App\Domain\Maintenance\Services\MaintenanceRequestService;
use App\Domain\Notifications\MtbfAlertNotification;
use App\Models\AutomationRule;
use App\Models\AutomationRuleExecution;
use App\Models\EquipmentKpi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReliabilityRuleEvaluator
{
    public function __construct(
        private readonly MaintenanceRequestService $mrService,
        private readonly AlertService $alertService,
    ) {}

    public function evaluate(AutomationRule $rule): void
    {
        $thresholdHours = (float) ($rule->configuration['threshold_hours'] ?? 500);

        $kpis = EquipmentKpi::withoutGlobalScopes()
            ->where('tenant_id', $rule->tenant_id)
            ->where('is_stale', false)
            ->where('mtbf_hours', '>', 0)
            ->where('mtbf_hours', '<', $thresholdHours)
            ->where('failure_count', '>', 0)
            ->whereHas('equipment', fn ($q) => $q
                ->withoutGlobalScopes()
                ->where('is_active', true)
                ->whereNull('deleted_at')  // R3
            )
            ->with('equipment')
            ->get();

        foreach ($kpis as $kpi) {
            $this->processLowMtbf($rule, $kpi, $thresholdHours);
        }
    }

    private function processLowMtbf(AutomationRule $rule, EquipmentKpi $kpi, float $threshold): void
    {
        // Monthly deduplication — re-fires each month if still below threshold
        $windowKey = now()->format('Y-m');
        $action = $rule->mode === AutomationMode::Automatic
            ? "created_mr_{$windowKey}"
            : "notified_mtbf_{$windowKey}";

        $alreadyActed = AutomationRuleExecution::where([
            'rule_id' => $rule->id,
            'entity_type' => 'equipment',
            'entity_id' => $kpi->equipment_id,
            'action_taken' => $action,
        ])->exists();

        if ($alreadyActed) {
            return;
        }

        $this->alertService->create(new CreateAlertData(
            tenantId: $rule->tenant_id,
            severity: AlertSeverity::Critical,
            category: AlertCategory::Reliability,
            title: "MTBF crítico: {$kpi->equipment->name}",
            message: sprintf('MTBF actual: %.1f h — umbral configurado: %.1f h.', $kpi->mtbf_hours, $threshold),
            entityType: 'equipment',
            entityId: $kpi->equipment_id,
            metadata: ['mtbf_hours' => $kpi->mtbf_hours, 'threshold' => $threshold],
        ));

        if ($rule->mode === AutomationMode::NotifyOnly) {
            $this->notifyAdmins($rule->tenant_id, $kpi, $threshold);
            $this->record($rule, 'equipment', $kpi->equipment_id, "notified_mtbf_{$windowKey}", [
                'mtbf_hours' => $kpi->mtbf_hours,
                'threshold' => $threshold,
            ]);

            return;
        }

        // Automatic: create urgent maintenance request
        $actor = $this->fallbackUser($rule->tenant_id);
        if ($actor === null) {
            return;
        }

        $equipment = $kpi->equipment;

        $mr = DB::transaction(fn () => $this->mrService->create([
            'tenant_id' => $rule->tenant_id,
            'equipment_id' => $kpi->equipment_id,
            'request_type' => MaintenanceRequestType::Predictive->value,
            'priority' => MaintenanceRequestPriority::High->value,
            'title' => 'MTBF crítico: '.$equipment->name,
            'description' => sprintf(
                'MTBF actual: %.1f h (umbral: %.1f h). Generado automáticamente.',
                $kpi->mtbf_hours,
                $threshold,
            ),
        ], $actor));

        $this->notifyAdmins($rule->tenant_id, $kpi, $threshold);
        $this->record($rule, 'equipment', $kpi->equipment_id, "created_mr_{$windowKey}", [
            'mr_id' => $mr->id,
            'mtbf_hours' => $kpi->mtbf_hours,
            'threshold' => $threshold,
        ]);
    }

    private function notifyAdmins(string $tenantId, EquipmentKpi $kpi, float $threshold): void
    {
        $admins = User::withoutGlobalScopes()
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Admin')
                ->orWhere('name', 'like', '%supervis%')
                ->orWhere('name', 'like', '%mantenimiento%'))
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new MtbfAlertNotification($kpi->equipment, $kpi->mtbf_hours, $threshold));
        }
    }

    private function record(AutomationRule $rule, string $entityType, string $entityId, string $action, array $metadata = []): void
    {
        AutomationRuleExecution::firstOrCreate(
            [
                'rule_id' => $rule->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action_taken' => $action,
            ],
            [
                'metadata' => $metadata ?: null,
                'executed_at' => now(),
            ],
        );
    }

    private function fallbackUser(string $tenantId): ?User
    {
        return User::withoutGlobalScopes()
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->where('is_active', true)
            ->orderBy('created_at')
            ->first();
    }
}
