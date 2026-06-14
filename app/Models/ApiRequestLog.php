<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'token_id',
        'method',
        'path',
        'status_code',
        'duration_ms',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
