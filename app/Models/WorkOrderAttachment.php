<?php

namespace App\Models;

use App\Domain\Maintenance\Enums\WorkOrderAttachmentType;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\WorkOrderAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'tenant_id',
    'work_order_id',
    'attachment_type',
    'file_path',
    'file_name',
    'file_size',
    'mime_type',
    'caption',
    'uploaded_by',
])]
class WorkOrderAttachment extends BaseModel
{
    /** @use HasFactory<WorkOrderAttachmentFactory> */
    use HasFactory;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
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

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'attachment_type' => WorkOrderAttachmentType::class,
        ];
    }
}
