<?php

namespace App\Console\Commands;

use App\Domain\Analytics\Services\PlantKpiService;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Vuelve a cerrar meses ya guardados, para que el cierre congelado vuelva a
 * coincidir con lo que dicen los paros y el calendario de producción.
 *
 * Existe porque un cierre se congela una vez y los datos siguen llegando después:
 * paros que se registran tarde, días de calendario que se terminan de llenar o
 * —como pasó con los diez meses del histórico— una carga completa posterior al
 * cierre. El trabajo agendado mantiene al día el mes en curso y el anterior; de
 * ahí hacia atrás recalcular es una decisión, y por eso es un comando y no algo
 * automático: volver a mover un mes que gerencia ya revisó se hace a pulso.
 *
 * Las toneladas corregidas a mano y la energía que vino de la hoja histórica
 * están protegidas dentro de {@see PlantKpiService::snapshotMonth()}, así que
 * recalcular no se lleva por delante las cifras que no tienen lecturas diarias
 * detrás.
 */
class SnapshotPlantKpis extends Command
{
    protected $signature = 'plant-kpis:snapshot
        {--from= : Primer mes a recalcular (YYYY-MM)}
        {--to= : Último mes a recalcular (YYYY-MM; por defecto, el mes en curso)}
        {--all : Recalcula todos los meses que ya tienen cierre guardado}
        {--dry-run : Enseña qué cambiaría sin escribir nada}';

    protected $description = 'Recalcula el cierre mensual de indicadores de planta para un rango de meses.';

    /** Las cifras que se comparan antes y después, con el nombre que usa la planta. */
    private const FIGURES = [
        'programmed_hours' => 'HPREN',
        'lost_hours' => 'perdidas',
        'processed_tons' => 'toneladas',
        'failure_count' => 'fallas',
        'mtbf_hours' => 'MTBF',
        'efficiency_percentage' => 'eficiencia',
    ];

    public function handle(PlantKpiService $service): int
    {
        $plants = Plant::withoutGlobalScopes()->get();

        if ($plants->isEmpty()) {
            $this->components->error('No hay plantas.');

            return self::FAILURE;
        }

        $months = $this->resolveMonths($plants);

        if ($months === null) {
            return self::FAILURE;
        }

        if ($months === []) {
            $this->components->warn('No hay meses que recalcular.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->components->info(sprintf(
            '%s %d mes(es), de %s a %s, en %d planta(s).',
            $dryRun ? 'Simulando' : 'Recalculando',
            count($months),
            $months[0]->format('m/Y'),
            end($months)->format('m/Y'),
            $plants->count(),
        ));

        $changed = 0;

        foreach ($plants as $plant) {
            foreach ($months as $month) {
                $before = $this->storedFigures($plant, $month);

                $after = $dryRun
                    ? $this->projectedFigures($service, $plant, $month)
                    : $this->appliedFigures($service, $plant, $month);

                if ($before === $after) {
                    continue;
                }

                $changed++;
                $this->reportChange($month, $before, $after);
            }
        }

        $this->newLine();
        $this->components->twoColumnDetail(
            $dryRun ? 'Meses que cambiarían' : 'Meses corregidos',
            $changed === 0 ? 'ninguno' : "<fg=green>{$changed}</>",
        );

        return self::SUCCESS;
    }

    /**
     * Los meses a recorrer, del más antiguo al más reciente.
     *
     * @param  Collection<int, Plant>  $plants
     * @return list<Carbon>|null null cuando las opciones no se entienden
     */
    private function resolveMonths(Collection $plants): ?array
    {
        if ($this->option('all')) {
            return PlantMonthlyKpi::withoutGlobalScopes()
                ->whereIn('plant_id', $plants->modelKeys())
                ->select('year', 'month')
                ->distinct()
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(fn (PlantMonthlyKpi $k): Carbon => Carbon::create($k->year, $k->month, 1)->startOfMonth())
                ->all();
        }

        if ($this->option('from') === null) {
            $this->components->error('Indique --from=YYYY-MM o use --all.');

            return null;
        }

        $from = $this->parseMonth((string) $this->option('from'));
        $to = $this->option('to') !== null
            ? $this->parseMonth((string) $this->option('to'))
            : Carbon::now()->startOfMonth();

        if ($from === null || $to === null) {
            $this->components->error('Los meses se escriben YYYY-MM, por ejemplo 2026-09.');

            return null;
        }

        if ($from->gt($to)) {
            $this->components->error('El mes inicial es posterior al final.');

            return null;
        }

        $months = [];

        for ($m = $from->copy(); $m->lte($to); $m->addMonthNoOverflow()) {
            $months[] = $m->copy();
        }

        return $months;
    }

    private function parseMonth(string $value): ?Carbon
    {
        if (preg_match('/^(\d{4})-(\d{1,2})$/', trim($value), $matches) !== 1) {
            return null;
        }

        $month = (int) $matches[2];

        return $month >= 1 && $month <= 12
            ? Carbon::create((int) $matches[1], $month, 1)->startOfMonth()
            : null;
    }

    /** @return array<string, float|int|null> */
    private function storedFigures(Plant $plant, Carbon $month): array
    {
        $stored = $this->stored($plant, $month);

        return $stored === null ? $this->figures(null) : $this->figures([
            'programmed_hours' => $stored->programmed_hours,
            'lost_hours' => $stored->lost_hours,
            'processed_tons' => $stored->processed_tons,
            'failure_count' => $stored->failure_count,
            'mtbf_hours' => $stored->mtbf_hours,
            'efficiency_percentage' => $stored->efficiency_percentage,
        ]);
    }

    /** @return array<string, float|int|null> */
    private function appliedFigures(PlantKpiService $service, Plant $plant, Carbon $month): array
    {
        $saved = $service->snapshotMonth($plant, $month->year, $month->month)->refresh();

        return $this->figures([
            'programmed_hours' => $saved->programmed_hours,
            'lost_hours' => $saved->lost_hours,
            'processed_tons' => $saved->processed_tons,
            'failure_count' => $saved->failure_count,
            'mtbf_hours' => $saved->mtbf_hours,
            'efficiency_percentage' => $saved->efficiency_percentage,
        ]);
    }

    /**
     * Lo que el cierre guardaría, sin guardarlo.
     *
     * Las toneladas salen de lo ya guardado cuando el mes está marcado como
     * corregido a mano, igual que hace `snapshotMonth()`. Si la simulación leyera
     * las del calendario anunciaría un cambio que al escribir no ocurre, y sobre
     * treinta y tres meses del histórico eso es justo el ruido que haría dudar de
     * si conviene ejecutar o no.
     *
     * @return array<string, float|int|null>
     */
    private function projectedFigures(PlantKpiService $service, Plant $plant, Carbon $month): array
    {
        $metrics = $service->calculate(
            $plant,
            $month->copy()->startOfMonth(),
            $month->copy()->endOfMonth(),
        );

        $stored = $this->stored($plant, $month);

        return $this->figures([
            'programmed_hours' => $metrics['programmed_hours'],
            'lost_hours' => $metrics['lost_hours'],
            'processed_tons' => $stored?->processed_tons_is_manual === true
                ? $stored->processed_tons
                : $metrics['processed_tons'],
            'failure_count' => $metrics['failure_count'],
            'mtbf_hours' => $metrics['mtbf_hours'],
            'efficiency_percentage' => $metrics['efficiency_percentage'],
        ]);
    }

    private function stored(Plant $plant, Carbon $month): ?PlantMonthlyKpi
    {
        return PlantMonthlyKpi::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->where('year', $month->year)
            ->where('month', $month->month)
            ->first();
    }

    /**
     * Normaliza las cifras a comparar.
     *
     * Se redondean porque llegan de columnas `decimal` como texto y de
     * `calculate()` como float: sin esto «60.50» y «60.5» se verían distintos y el
     * comando anunciaría correcciones que no corrigen nada.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, float|int|null>
     */
    private function figures(?array $values): array
    {
        $out = [];

        foreach (array_keys(self::FIGURES) as $key) {
            $raw = $values[$key] ?? null;

            if ($key === 'failure_count') {
                $out[$key] = $values === null ? null : (int) ($raw ?? 0);

                continue;
            }

            $out[$key] = $raw === null ? null : round((float) $raw, 2);
        }

        return $out;
    }

    /**
     * @param  array<string, float|int|null>  $before
     * @param  array<string, float|int|null>  $after
     */
    private function reportChange(Carbon $month, array $before, array $after): void
    {
        $parts = [];

        foreach (self::FIGURES as $key => $label) {
            if ($before[$key] === $after[$key]) {
                continue;
            }

            $parts[] = sprintf('%s %s → %s', $label, $this->fmt($before[$key]), $this->fmt($after[$key]));
        }

        $this->line(sprintf('  %s  %s', $month->format('Y-m'), implode('  ·  ', $parts)));
    }

    private function fmt(float|int|null $value): string
    {
        return $value === null ? '—' : (string) $value;
    }
}
