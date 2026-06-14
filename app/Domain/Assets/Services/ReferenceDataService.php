<?php

namespace App\Domain\Assets\Services;

use App\Models\Area;
use App\Models\EquipmentCategory;
use App\Models\Plant;
use Illuminate\Support\Facades\Cache;

class ReferenceDataService
{
    private const TTL = 3600;

    public static function plants(string $tenantId): array
    {
        return Cache::remember("reference:plants:{$tenantId}", self::TTL, fn () => Plant::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray()
        );
    }

    public static function areas(string $plantId): array
    {
        return Cache::remember("reference:areas:{$plantId}", self::TTL, fn () => Area::withoutGlobalScopes()
            ->where('plant_id', $plantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray()
        );
    }

    /** All areas for a tenant — used by hidden auto-populated selects. */
    public static function allAreas(string $tenantId): array
    {
        return Cache::remember("reference:all_areas:{$tenantId}", self::TTL, fn () => Area::withoutGlobalScopes()
            ->whereHas('plant', fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray()
        );
    }

    public static function categories(string $tenantId): array
    {
        return Cache::remember("reference:categories:{$tenantId}", self::TTL, fn () => EquipmentCategory::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray()
        );
    }

    public static function forgetPlants(string $tenantId): void
    {
        Cache::forget("reference:plants:{$tenantId}");
    }

    public static function forgetAreas(string $plantId, string $tenantId): void
    {
        Cache::forget("reference:areas:{$plantId}");
        Cache::forget("reference:all_areas:{$tenantId}");
    }

    public static function forgetCategories(string $tenantId): void
    {
        Cache::forget("reference:categories:{$tenantId}");
    }
}
