<?php

namespace App\Domain\Commercial\Services;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Models\OilTank;
use App\Models\OilTankReading;
use App\Models\SalesDispatch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La medición diaria de los tanques de aceite y lo que sale de ella.
 *
 * Cada medición es el nivel al cierre del día, después de los cargues. Por eso la
 * producción no se digita: es lo que subieron los tanques desde la medición anterior más
 * el aceite que salió en ese tiempo (peso de salida de los despachos de CPO).
 *
 *     producción = total de hoy − total de la medición anterior + despachado entre las dos
 *
 * Si entre dos mediciones pasaron varios días, la producción es la de todo ese tramo y se
 * anota en el día de la medición. Solo se calcula cuando las dos mediciones tienen los
 * mismos tanques: con un tanque sin medir, el total no es comparable.
 */
class OilTankService
{
    /** Acidez por encima de esto se marca: el límite de calidad del Excel. */
    public const ACIDITY_LIMIT = 5.0;

    /** Una medición de más días que esto ya no sirve para decir cuánto hay para vender. */
    public const STALE_AFTER_DAYS = 2;

    /**
     * Guarda las mediciones de un día. Sin nivel, la del tanque se borra.
     *
     * @param  array<string, array{level?: mixed, acidity?: mixed}>  $levels  tanque → nivel y acidez
     */
    public function record(string $tenantId, CarbonImmutable $date, array $levels, ?User $user): void
    {
        if ($date->isAfter(CarbonImmutable::now('America/Bogota')->endOfDay())) {
            throw new \DomainException('No se mide un día que no ha llegado.');
        }

        $tanks = OilTank::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id')->flip();

        DB::transaction(function () use ($tenantId, $date, $levels, $user, $tanks): void {
            foreach ($levels as $tankId => $values) {
                if (! isset($tanks[$tankId])) {
                    continue;
                }

                $level = AnnualPlanService::number($values['level'] ?? null);
                $acidity = AnnualPlanService::number($values['acidity'] ?? null);
                $existing = OilTankReading::withoutGlobalScopes()->where('oil_tank_id', $tankId)->whereDate('reading_date', $date->toDateString())->first();

                if ($level === null) {
                    $existing?->forceDelete();

                    continue;
                }

                if ($level < 0 || ($acidity !== null && ($acidity < 0 || $acidity > 100))) {
                    throw new \DomainException('El nivel no puede ser negativo y la acidez va entre 0 y 100.');
                }

                $values = ['level_tons' => $level, 'acidity_percent' => $acidity, 'recorded_by' => $user?->id];

                if ($existing !== null) {
                    $existing->forceFill([...$values, 'deleted_at' => null])->save();
                } else {
                    OilTankReading::withoutGlobalScopes()->create([...$values, 'tenant_id' => $tenantId, 'oil_tank_id' => $tankId, 'reading_date' => $date->toDateString()]);
                }
            }
        });
    }

    /**
     * Los días con medición entre dos fechas, del más antiguo al más reciente.
     *
     * @return list<array{date: CarbonImmutable, tanks: array<string, array{level: float, acidity: ?float}>, total: float, acidity: ?float, dispatched: float, production: ?float, days: ?int}>
     */
    public function days(string $tenantId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        // La medición anterior al rango, para calcular la producción del primer día.
        $previousDate = OilTankReading::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereDate('reading_date', '<', $from->toDateString())
            ->max('reading_date');

        $readings = OilTankReading::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereDate('reading_date', '>=', $previousDate ?? $from->toDateString())
            ->whereDate('reading_date', '<=', $to->toDateString())
            ->orderBy('reading_date')
            ->get()
            ->groupBy(fn (OilTankReading $reading): string => $reading->reading_date->toDateString());

        $dispatched = $this->dispatchedByDay($tenantId, CarbonImmutable::parse($previousDate ?? $from)->addDay(), $to);

        $days = [];
        $previous = null;

        foreach ($readings as $date => $dayReadings) {
            $day = $this->summarize(CarbonImmutable::parse($date), $dayReadings);

            if ($previous !== null) {
                $sameTanks = array_keys($previous['tanks']) == array_keys($day['tanks']);
                $out = $this->sumBetween($dispatched, $previous['date'], $day['date']);
                $day['dispatched'] = round($out, 2);
                $day['production'] = $sameTanks ? round($day['total'] - $previous['total'] + $out, 2) : null;
                $day['days'] = (int) $previous['date']->diffInDays($day['date']);
            }

            if ($day['date']->greaterThanOrEqualTo($from)) {
                $days[] = $day;
            }

            $previous = $day;
        }

        return $days;
    }

    /**
     * Lo último que se midió y lo que queda para vender: el total de esa medición menos lo
     * que se ha cargado después.
     *
     * @return array{date: CarbonImmutable, tanks: array<string, array{level: float, acidity: ?float}>, total: float, acidity: ?float, dispatched_since: float, available: float, age_days: int, stale: bool}|null
     */
    public function latest(string $tenantId): ?array
    {
        $date = OilTankReading::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->max('reading_date');

        if ($date === null) {
            return null;
        }

        $date = CarbonImmutable::parse($date);
        $day = $this->summarize($date, OilTankReading::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->whereDate('reading_date', $date->toDateString())->get());
        $since = (float) $this->cpoDispatches($tenantId)->whereDate('loaded_on', '>', $date->toDateString())->sum('departure_weight_tons');

        $today = CarbonImmutable::parse(CarbonImmutable::now('America/Bogota')->toDateString());
        $age = (int) $date->diffInDays($today);

        return [
            ...$day,
            'dispatched_since' => round($since, 2),
            'available' => round($day['total'] - $since, 2),
            'age_days' => $age,
            // Con la medición vieja, restarle todo lo despachado desde entonces da un
            // disponible sin sentido (el Excel llegó a mostrar −1.016 tn): hay que medir.
            'stale' => $age > self::STALE_AFTER_DAYS,
        ];
    }

    /** @return array<int, float> mes → aceite producido según los tanques */
    public function monthlyProduction(string $tenantId, int $year): array
    {
        $totals = array_fill(1, 12, 0.0);

        foreach ($this->days($tenantId, CarbonImmutable::create($year, 1, 1), CarbonImmutable::create($year, 12, 31)) as $day) {
            if ($day['production'] !== null) {
                $totals[$day['date']->month] = round($totals[$day['date']->month] + $day['production'], 2);
            }
        }

        return $totals;
    }

    /** @param  Collection<int, OilTankReading>  $readings */
    private function summarize(CarbonImmutable $date, Collection $readings): array
    {
        $tanks = $readings->sortBy('oil_tank_id')->mapWithKeys(fn (OilTankReading $reading): array => [$reading->oil_tank_id => [
            'level' => (float) $reading->level_tons,
            'acidity' => $reading->acidity_percent === null ? null : (float) $reading->acidity_percent,
        ]])->all();

        $total = round(array_sum(array_column($tanks, 'level')), 2);
        $weighted = collect($tanks)->filter(fn (array $tank): bool => $tank['acidity'] !== null && $tank['level'] > 0);
        $weightedTons = $weighted->sum('level');

        return [
            'date' => $date,
            'tanks' => $tanks,
            'total' => $total,
            // La acidez de la mezcla: la de cada tanque pesada por lo que tiene.
            'acidity' => $weightedTons > 0 ? round($weighted->sum(fn (array $tank): float => $tank['level'] * $tank['acidity']) / $weightedTons, 2) : null,
            'dispatched' => 0.0,
            'production' => null,
            'days' => null,
        ];
    }

    /** @return array<string, float> día → toneladas de aceite que salieron (peso de salida) */
    private function dispatchedByDay(string $tenantId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->cpoDispatches($tenantId)
            ->whereDate('loaded_on', '>=', $from->toDateString())
            ->whereDate('loaded_on', '<=', $to->toDateString())
            ->groupBy('loaded_on')
            ->selectRaw('loaded_on, sum(departure_weight_tons) as tons')
            ->pluck('tons', 'loaded_on')
            ->mapWithKeys(fn ($tons, $date): array => [CarbonImmutable::parse($date)->toDateString() => (float) $tons])
            ->all();
    }

    /** Lo despachado después de $after y hasta $until, incluido. */
    private function sumBetween(array $dispatched, CarbonImmutable $after, CarbonImmutable $until): float
    {
        $sum = 0.0;

        foreach ($dispatched as $date => $tons) {
            if ($date > $after->toDateString() && $date <= $until->toDateString()) {
                $sum += $tons;
            }
        }

        return $sum;
    }

    private function cpoDispatches(string $tenantId)
    {
        return SalesDispatch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('product', CommercialProduct::Cpo->value);
    }
}
