<?php

namespace App\Models;

use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertStatus;
use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends BaseModel
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'severity' => AlertSeverity::class,
            'category' => AlertCategory::class,
            'status' => AlertStatus::class,
            'metadata' => 'array',
            'closed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === AlertStatus::Open;
    }
}
