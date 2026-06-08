<?php

namespace App\Models;

use App\Domain\Maintenance\Enums\MaintenanceRequestPriority;
use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Enums\MaintenanceRequestType;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\MaintenanceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tenant_id',
    'request_number',
    'issue_report_id',
    'equipment_id',
    'request_type',
    'priority',
    'status',
    'title',
    'description',
    'requested_due_date',
    'rejection_reason',
    'created_by',
    'assigned_reviewer',
    'approved_by',
    'rejected_by',
    'submitted_at',
    'reviewed_at',
    'approved_at',
    'rejected_at',
    'work_order_id',
])]
class MaintenanceRequest extends BaseModel
{
    /** @use HasFactory<MaintenanceRequestFactory> */
    use HasFactory;
    // ── Relationships ─────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function issueReport(): BelongsTo
    {
        return $this->belongsTo(EquipmentIssueReport::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_reviewer');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function workOrder(): HasOne
    {
        return $this->hasOne(WorkOrder::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(MaintenanceRequestComment::class)->orderBy('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MaintenanceRequestAttachment::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            MaintenanceRequestStatus::Cancelled->value,
            MaintenanceRequestStatus::Converted->value,
        ]);
    }

    public function scopeByStatus(Builder $query, MaintenanceRequestStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function canTransitionTo(MaintenanceRequestStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'request_type'       => MaintenanceRequestType::class,
            'priority'           => MaintenanceRequestPriority::class,
            'status'             => MaintenanceRequestStatus::class,
            'requested_due_date' => 'date',
            'submitted_at'       => 'datetime',
            'reviewed_at'        => 'datetime',
            'approved_at'        => 'datetime',
            'rejected_at'        => 'datetime',
        ];
    }
}
