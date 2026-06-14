<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Enums\ActivityType;
use App\Domain\Shared\Enums\LocationSource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityLocation extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'activity_type',
        'activity_id',
        'latitude',
        'longitude',
        'accuracy',
        'source',
        'is_low_accuracy',
        'captured_at',
    ];

    protected $casts = [
        'activity_type' => ActivityType::class,
        'source' => LocationSource::class,
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'is_low_accuracy' => 'boolean',
        'captured_at' => 'datetime',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }
}
