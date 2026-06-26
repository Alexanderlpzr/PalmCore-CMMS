<?php

namespace App\Infrastructure\Audit\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WriteAuditLog implements ShouldQueue
{
    use Queueable;

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'personal_access_token',
        'secret',
        'api_key',
        'api_secret',
        'webhook_secret',
        'client_secret',
        'private_key',
        'remember_token',
    ];

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
        DB::table('audit_logs')->insert([
            'id' => (string) Str::uuid7(),
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'event' => $this->event,
            'auditable_type' => $this->modelClass,
            'auditable_id' => $this->modelKey,
            'old_values' => $this->oldValues !== null ? json_encode($this->maskSensitiveFields($this->oldValues)) : null,
            'new_values' => $this->newValues !== null ? json_encode($this->maskSensitiveFields($this->newValues)) : null,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $values */
    private function maskSensitiveFields(array $values): array
    {
        foreach (self::SENSITIVE_KEYS as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '***';
            }
        }

        return $values;
    }
}
