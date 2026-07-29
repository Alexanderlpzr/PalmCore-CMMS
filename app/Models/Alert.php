<?php

namespace App\Models;

use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertStatus;
use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends BaseModel
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'closed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * severity/category/status se resuelven con tryFrom(), no con el cast nativo
     * de enum: la columna es un string plano sin constraint en la BD, así que un
     * valor viejo o inválido no puede tirar la página entera con un ValueError —
     * se degrada a null y la UI lo muestra como «Desconocido» en vez de un 500.
     */
    protected function severity(): Attribute
    {
        return Attribute::make(get: fn (?string $value): ?AlertSeverity => $value !== null ? AlertSeverity::tryFrom($value) : null);
    }

    protected function category(): Attribute
    {
        return Attribute::make(get: fn (?string $value): ?AlertCategory => $value !== null ? AlertCategory::tryFrom($value) : null);
    }

    protected function status(): Attribute
    {
        return Attribute::make(get: fn (?string $value): ?AlertStatus => $value !== null ? AlertStatus::tryFrom($value) : null);
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
