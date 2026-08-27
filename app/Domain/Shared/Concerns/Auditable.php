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

        foreach (config('palmcore.audit.events', ['created', 'updated', 'deleted', 'restored']) as $event) {
            // `restored` solo existe en modelos con borrado lógico. En uno sin él, la
            // llamada estática cae en `__callStatic`, que instancia el modelo — y hacerlo
            // durante su propio arranque revienta con «may not be called while it is being
            // booted», un error que no señala a nada parecido a la causa.
            //
            // El trait daba por sentado que todo auditable extendía BaseModel, que sí trae
            // SoftDeletes. Dejó de ser cierto al auditar los cierres mensuales de planta.
            if ($event === 'restored' && ! method_exists(static::class, 'restored')) {
                continue;
            }

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
