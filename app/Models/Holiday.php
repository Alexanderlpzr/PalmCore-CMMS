<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un festivo del calendario de la empresa.
 *
 * Se capturan, no se calculan: la Ley Emiliani corre buena parte de los festivos
 * colombianos al lunes siguiente y los de Pascua se mueven con ella. Una función que los
 * derive falla un año cualquiera, y ese error se paga a más del doble de la hora
 * ordinaria en toda la planta.
 */
#[Fillable([
    'tenant_id',
    'holiday_date',
    'name',
    'is_national',
])]
class Holiday extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'hr_holidays';

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeInYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('holiday_date', $year);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * ¿Esta fecha se paga con recargo dominical?
     *
     * Domingo y festivo son el mismo recargo, y por eso la pregunta es una sola. El libro
     * de la extractora los distingue al capturar —marca DOMINGO o FESTIVO— pero los paga
     * igual, y esa distinción visual no la lee ninguna fórmula.
     */
    public static function isSurchargedDay(CarbonInterface $date, string $tenantId): bool
    {
        if ($date->isSunday()) {
            return true;
        }

        return static::query()
            ->forTenant($tenantId)
            ->whereDate('holiday_date', $date)
            ->exists();
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_national' => 'boolean',
        ];
    }
}
