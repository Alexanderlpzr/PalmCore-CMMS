<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\EquipmentPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'equipment_id',
    'tenant_id',
    'file_path',
    'file_name',
    'file_size',
    'mime_type',
    'caption',
    'is_primary',
    'sort_order',
    'uploaded_by',
])]
class EquipmentPhoto extends BaseModel
{
    /** @use HasFactory<EquipmentPhotoFactory> */
    use HasFactory;

    // ── Model Events ─────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        // Ensure only one photo per equipment can be primary
        static::saving(function (self $photo): void {
            if ($photo->is_primary) {
                self::where('equipment_id', $photo->equipment_id)
                    ->where('id', '!=', $photo->getKey())
                    ->update(['is_primary' => false]);
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'file_size'  => 'integer',
        ];
    }
}
