<?php

namespace App\Domain\Alerts\Services;

use App\Domain\Alerts\Data\CreateAlertData;
use App\Domain\Alerts\Enums\AlertStatus;
use App\Events\AlertCreated;
use App\Events\AlertResolved;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;

class AlertService
{
    /**
     * Crea una alerta si no existe una abierta para la misma entidad/categoría.
     * Retorna null si ya existía (idempotente — no es error).
     */
    public function create(CreateAlertData $data): ?Alert
    {
        if ($this->existsOpenAlert($data->tenantId, $data->entityType, $data->entityId, $data->category->value)) {
            return null;
        }

        try {
            $alert = Alert::forceCreate([
                'tenant_id' => $data->tenantId,
                'severity' => $data->severity->value,
                'category' => $data->category->value,
                'title' => $data->title,
                'message' => $data->message,
                'entity_type' => $data->entityType,
                'entity_id' => $data->entityId,
                'status' => AlertStatus::Open->value,
                'metadata' => $data->metadata ?: null,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Race condition: otro job creó la misma alerta entre el check y el insert
            return null;
        }

        $this->bustCriticalCache($data->tenantId);

        event(new AlertCreated($alert, $data->notifiableUserIds));

        return $alert;
    }

    /**
     * Atomically resolves an open alert. Returns true if resolved now, false if already closed.
     */
    public function resolve(Alert $alert, User $user): bool
    {
        $affected = Alert::withoutGlobalScopes()
            ->where('id', $alert->id)
            ->where('status', AlertStatus::Open->value)
            ->whereNull('deleted_at')
            ->update([
                'status' => AlertStatus::Resolved->value,
                'closed_at' => now(),
                'closed_by' => $user->id,
            ]);

        if ($affected > 0) {
            $this->bustCriticalCache($alert->tenant_id);
            event(new AlertResolved($alert->fresh() ?? $alert));
        }

        return $affected > 0;
    }

    /**
     * Atomically dismisses an open alert. Returns true if dismissed now, false if already closed.
     */
    public function dismiss(Alert $alert, User $user): bool
    {
        $affected = Alert::withoutGlobalScopes()
            ->where('id', $alert->id)
            ->where('status', AlertStatus::Open->value)
            ->whereNull('deleted_at')
            ->update([
                'status' => AlertStatus::Dismissed->value,
                'closed_at' => now(),
                'closed_by' => $user->id,
            ]);

        if ($affected > 0) {
            $this->bustCriticalCache($alert->tenant_id);
        }

        return $affected > 0;
    }

    /**
     * Auto-resuelve todas las alertas abiertas de una entidad eliminada.
     * Llamado desde observers de modelo — no dispara evento ni notificación.
     */
    public function autoResolveForEntity(string $tenantId, string $entityType, string $entityId, string $entityName): void
    {
        Alert::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('status', AlertStatus::Open->value)
            ->whereNull('deleted_at')
            ->each(function (Alert $alert) use ($entityName): void {
                $metadata = $alert->metadata ?? [];
                $metadata['auto_resolved'] = 'entity_deleted';
                $metadata['entity_name'] = $entityName;

                $alert->forceFill([
                    'status' => AlertStatus::Resolved->value,
                    'closed_at' => now(),
                    'metadata' => $metadata,
                ])->save();
            });

        $this->bustCriticalCache($tenantId);
    }

    public function existsOpenAlert(string $tenantId, ?string $entityType, ?string $entityId, string $category): bool
    {
        return Alert::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('category', $category)
            ->where('status', AlertStatus::Open->value)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function getOpenCriticalCount(string $tenantId): int
    {
        return Cache::remember("critical_alerts_{$tenantId}", 30, fn () => Alert::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', AlertStatus::Open->value)
            ->where('severity', 'critical')
            ->whereNull('deleted_at')
            ->count());
    }

    private function bustCriticalCache(string $tenantId): void
    {
        Cache::forget("critical_alerts_{$tenantId}");
    }
}
