<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Immutable evidence that an automation rule acted on a specific entity.
 * UNIQUE (rule_id, entity_type, entity_id, action_taken) enforced at DB level.
 */
class AutomationRuleExecution extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'rule_id',
        'entity_type',
        'entity_id',
        'action_taken',
        'metadata',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'rule_id');
    }
}
