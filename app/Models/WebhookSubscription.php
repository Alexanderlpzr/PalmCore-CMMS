<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebhookSubscription extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const DEACTIVATION_THRESHOLD = 5;

    protected $fillable = [
        'tenant_id',
        'url',
        'events',
        'secret',
        'is_active',
        'failure_count',
        'last_triggered_at',
        'last_error',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'failure_count' => 'integer',
            'last_triggered_at' => 'datetime',
            'secret' => 'encrypted',
        ];
    }

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(WebhookDeliveryLog::class);
    }

    public function recentLogs(): HasMany
    {
        return $this->hasMany(WebhookDeliveryLog::class)->latest()->limit(50);
    }
}
