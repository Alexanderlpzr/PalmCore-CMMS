<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'maintenance_request_id',
    'file_path',
    'file_name',
    'file_size',
    'mime_type',
    'caption',
    'uploaded_by',
])]
class MaintenanceRequestAttachment extends BaseModel
{
    // ── Relationships ─────────────────────────────────────────────────────────

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function url(): string
    {
        return file_signed_url(persistent_disk(), $this->file_path) ?? '';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
