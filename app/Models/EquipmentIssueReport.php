<?php

namespace App\Models;

use App\Domain\Assets\Enums\IssueSeverity;
use App\Domain\Maintenance\Enums\IssueReportStatus;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\EquipmentIssueReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'equipment_id',
    'tenant_id',
    'qr_code_id',
    'description',
    'severity',
    'reporter_name',
    'reporter_phone',
    'reporter_user_id',
    'status',
    'acknowledged_at',
    'acknowledged_by',
    'admin_notes',
    'maintenance_request_id',
])]
class EquipmentIssueReport extends BaseModel
{
    /** @use HasFactory<EquipmentIssueReportFactory> */
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

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(EquipmentQrCode::class, 'qr_code_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function maintenanceRequest(): HasOne
    {
        return $this->hasOne(MaintenanceRequest::class, 'issue_report_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', IssueReportStatus::Open->value);
    }

    public function scopeBySeverity(Builder $query, IssueSeverity $severity): Builder
    {
        return $query->where('severity', $severity->value);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function acknowledge(User $user): void
    {
        $this->update([
            'status'          => IssueReportStatus::Acknowledged,
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
        ]);
    }

    public function markConvertedToMr(): void
    {
        $this->update(['status' => IssueReportStatus::ConvertedToMR]);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'severity'        => IssueSeverity::class,
            'status'          => IssueReportStatus::class,
            'acknowledged_at' => 'datetime',
        ];
    }
}
