<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentHistory extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'equipment_component_id',
        'user_id',
        'type',
        'description',
        'worked_hours_at_event',
        'occurred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
            'worked_hours_at_event' => 'float',
        ];
    }

    public function equipmentComponent(): BelongsTo
    {
        return $this->belongsTo(EquipmentComponent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
