<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\EquipmentQrCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'equipment_id',
    'tenant_id',
    'qr_token',
    'qr_image_path',
    'is_active',
    'generated_at',
    'last_scanned_at',
    'scan_count',
])]
class EquipmentQrCode extends BaseModel
{
    /** @use HasFactory<EquipmentQrCodeFactory> */
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

    public function issueReports(): HasMany
    {
        return $this->hasMany(EquipmentIssueReport::class, 'qr_code_id');
    }

    // ── URL Helpers ───────────────────────────────────────────────────────────

    public function publicUrl(): string
    {
        return route('equipment.qr.show', ['token' => $this->qr_token]);
    }

    public function imageUrl(): ?string
    {
        if (! $this->qr_image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->qr_image_path);
    }

    // ── Analytics ─────────────────────────────────────────────────────────────

    /** Atomically increment scan counter and record timestamp. */
    public function recordScan(): void
    {
        $this->increment('scan_count');
        $this->update(['last_scanned_at' => now()]);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'generated_at' => 'datetime',
            'last_scanned_at' => 'datetime',
            'scan_count' => 'integer',
        ];
    }
}
