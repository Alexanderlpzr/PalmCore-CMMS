<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\EquipmentSparePartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un repuesto que lleva este equipo. Solo el dato, para saber qué pedir.
 *
 * Distinto de {@see EquipmentComponent} (pieza con horas trabajadas y vida útil
 * que dispara preventivos) y de {@see SparePart} (inventario del almacén, con
 * existencias y punto de reorden).
 */
#[Fillable([
    'tenant_id',
    'equipment_id',
    'name',
    'part_number',
    'unit_cost',
    'notes',
])]
class EquipmentSparePart extends BaseModel
{
    /** @use HasFactory<EquipmentSparePartFactory> */
    use HasFactory;

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
        ];
    }
}
