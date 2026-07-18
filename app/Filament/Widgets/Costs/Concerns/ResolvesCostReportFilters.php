<?php

namespace App\Filament\Widgets\Costs\Concerns;

use App\Models\Plant;
use Filament\Facades\Filament;

/**
 * Los tres widgets de gasto leen el mismo filtro de la página —planta, año y
 * mes— con los mismos valores por defecto. Esto evita repetir esa resolución
 * (y que se desincronice entre widgets) en cada uno.
 */
trait ResolvesCostReportFilters
{
    protected function tenantId(): string
    {
        return Filament::getTenant()->id;
    }

    protected function resolvePlantId(): ?string
    {
        $plantId = $this->pageFilters['plant_id'] ?? null;

        if ($plantId !== null) {
            return $plantId;
        }

        return Plant::where('tenant_id', $this->tenantId())
            ->orderBy('name')
            ->value('id');
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function resolvePeriod(): array
    {
        $year = (int) ($this->pageFilters['year'] ?? now()->year);
        $month = (int) ($this->pageFilters['month'] ?? now()->month);

        return [$year, $month];
    }
}
