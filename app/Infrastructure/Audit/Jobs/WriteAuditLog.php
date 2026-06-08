<?php

namespace App\Infrastructure\Audit\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WriteAuditLog implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $modelClass,
        public readonly string $modelKey,
        public readonly string $event,
        public readonly ?array $oldValues,
        public readonly ?array $newValues,
        public readonly ?string $userId,
        public readonly ?string $tenantId,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
    ) {
        $this->onQueue(config('palmcore.audit.queue', 'audit'));
    }

    public function handle(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'id' => (string) Str::uuid7(),
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'event' => $this->event,
            'auditable_type' => $this->modelClass,
            'auditable_id' => $this->modelKey,
            'old_values' => $this->oldValues ? json_encode($this->oldValues) : null,
            'new_values' => $this->newValues ? json_encode($this->newValues) : null,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'created_at' => now(),
        ]);
    }
}
