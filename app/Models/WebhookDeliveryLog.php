<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WebhookDeliveryLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'webhook_subscription_id',
        'event_id',
        'event_name',
        'http_status',
        'duration_ms',
        'response_size',
        'status',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'duration_ms' => 'integer',
            'response_size' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }
}
