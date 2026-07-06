<?php

namespace App\Models;

use App\Domain\Assets\Enums\DocumentType;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\EquipmentDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'equipment_id',
    'tenant_id',
    'document_type',
    'title',
    'description',
    'file_path',
    'file_name',
    'file_size',
    'mime_type',
    'version',
    'expires_at',
    'is_active',
    'uploaded_by',
])]
class EquipmentDocument extends BaseModel
{
    /** @use HasFactory<EquipmentDocumentFactory> */
    use HasFactory;

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

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isFuture() && now()->diffInDays($this->expires_at, false) <= $days;
    }

    public function humanFileSize(): string
    {
        if ($this->file_size === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 1).' '.$units[$unit];
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'expires_at' => 'date',
            'is_active' => 'boolean',
            'file_size' => 'integer',
        ];
    }
}
