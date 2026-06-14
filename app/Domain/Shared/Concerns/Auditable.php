<?php

namespace App\Domain\Shared\Concerns;

use App\Infrastructure\Audit\Jobs\WriteAuditLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        if (! config('palmcore.audit.enabled', true)) {
            return;
        }

        foreach (config('palmcore.audit.events', ['created', 'updated', 'deleted']) as $event) {
            static::$event(function (self $model) use ($event): void {
                WriteAuditLog::dispatch(
                    modelClass: get_class($model),
                    modelKey: (string) $model->getKey(),
                    event: $event,
                    oldValues: $event !== 'created' ? $model->getOriginal() : null,
                    newValues: $event !== 'deleted' ? $model->getAttributes() : null,
                    userId: auth()->id(),
                    tenantId: $model->getAttributes()['tenant_id'] ?? null,
                    ipAddress: request()?->ip(),
                    userAgent: request()?->userAgent(),
                )->afterResponse();
            });
        }
    }
}
