<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\MaintenancePlanAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'tenant_id',
    'maintenance_plan_id',
    'attachment_label',
    'file_path',
    'file_name',
    'file_size',
    'mime_type',
    'uploaded_by',
])]
class MaintenancePlanAttachment extends BaseModel
{
    /** @use HasFactory<MaintenancePlanAttachmentFactory> */
    use HasFactory;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
