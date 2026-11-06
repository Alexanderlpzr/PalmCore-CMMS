<?php

namespace App\Models;

use App\Domain\Safety\Enums\FindingStatus;
use App\Domain\Safety\Enums\ResponsibleArea;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\SstFindingTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La parte de un hallazgo de SST que le toca a un área responsable. La de mantenimiento
 * lleva su reporte de novedad: cuando ese reporte se vuelve OT queda «En ejecución», y
 * cuando la OT se cierra, «Ejecutado».
 */
#[Fillable([
    'tenant_id',
    'sst_finding_id',
    'area',
    'status',
    'started_at',
    'executed_on',
    'executed_by',
    'issue_report_id',
    'notes',
])]
class SstFindingTask extends BaseModel
{
    /** @use HasFactory<SstFindingTaskFactory> */
    use HasFactory;

    public function finding(): BelongsTo
    {
        return $this->belongsTo(SstFinding::class, 'sst_finding_id');
    }

    public function issueReport(): BelongsTo
    {
        return $this->belongsTo(EquipmentIssueReport::class, 'issue_report_id');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    protected function casts(): array
    {
        return [
            'area' => ResponsibleArea::class,
            'status' => FindingStatus::class,
            'started_at' => 'immutable_datetime',
            'executed_on' => 'immutable_date',
        ];
    }
}
