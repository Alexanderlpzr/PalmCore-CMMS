<?php

namespace App\Services;

use App\Domain\Shared\Enums\ActivityType;
use App\Domain\Shared\Enums\LocationSource;
use App\Models\ActivityLocation;
use App\Models\User;

class ActivityLocationService
{
    /**
     * Record a GPS snapshot for a completed action.
     *
     * @param  array{latitude: float, longitude: float, accuracy: float, source?: string, gps_timestamp?: string}  $gps
     */
    public function record(
        string $tenantId,
        User $user,
        ActivityType $type,
        string $activityId,
        array $gps,
    ): ActivityLocation {
        $accuracy = (float) $gps['accuracy'];

        return ActivityLocation::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'activity_type' => $type,
            'activity_id' => $activityId,
            'latitude' => (float) $gps['latitude'],
            'longitude' => (float) $gps['longitude'],
            'accuracy' => $accuracy,
            'source' => LocationSource::tryFrom($gps['source'] ?? '') ?? LocationSource::Unknown,
            'is_low_accuracy' => $accuracy > 100,
            'captured_at' => isset($gps['gps_timestamp']) ? $gps['gps_timestamp'] : now(),
        ]);
    }
}
