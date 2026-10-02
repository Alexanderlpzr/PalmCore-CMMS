<?php

namespace App\Console\Commands;

use App\Domain\Analytics\Services\PlantKpiService;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vuelve a cerrar meses ya guardados, para que el cierre congelado vuelva a
 * coincidir con lo que dicen los paros, el calendario de producción y los
 * contadores de energía.
 *
 * Existe porque un cierre se congela y los datos siguen llegando después: paros
 * que se registran tarde, días de calendario que se terminan de llenar o —como
 * pasó con los diez meses del histórico— una carga completa posterior al cierre.
 * El trabajo agendado mantiene al día el mes en curso y el anterior; de ahí hacia
 * atrás recalcular es una decisión, y por eso es un comando y no algo automático:
 * volver a mover un mes que gerencia ya revisó se hace a pulso.
 *
 * La simulación ejecuta el cierre de verdad dentro de una transacción que se
 * deshace, con los eventos de modelo apagados. Así compara contra exactamente lo
 * que se escribiría —con las mismas protecciones de toneladas y energía, y con
 * los indicadores que calcula Postgres—, sin repetir esa lógica aquí, y sin dejar
 * en la auditoría correcciones que nunca ocurrieron. La primera versión repetía
 * las guardas en una lista fija de cifras, y la energía de septiembre se corrigió
 * sin que la simulación lo anunciara.
 */
class SnapshotPlantKpis extends Command
{
    protected $signature = 'plant-kpis:snapshot
        {--from= : Primer mes a recalcular (YYYY-MM)}
        {--to= : Último mes a recalcular (YYYY-MM; por defecto, el mes en curso)}
        {--all : Recalcula todos los meses que ya tienen cierre guardado}
        {--dry-run : Enseña qué cambiaría sin escribir nada}';

    protected $description = 'Recalcula el cierre mensual de indicadores de planta para un rango de meses.';

    /** Lo que identifica el mes o lo fecha, no lo que mide: no se compara. */
    private const NOT_FIGURES = [
        'id', 'tenant_id', 'plant_id', 'year', 'month',
        'created_at', 'updated_at', 'calculated_at',
    ];

    /**
     * Cómo se enseña cada cifra, agrupada como la lee la planta. Una columna que
     * cambie y no esté aquí se enseña igual, con su nombre crudo: que el informe
     * nunca vuelva a callarse un cambio porque alguien añadió una columna.
     */
    private const GROUPS = [
        'horas' => [
            'programmed_hours' => 'HPREN',
            'lost_hours' => 'perdidas',
            'effective_hours' => 'efectivas',
            'maintenance_lost_hours' => 'perdidas mtto',
            'cleaning_hours' => 'aseo',
        ],
        'producción' => [
            'processed_tons' => 'toneladas',
        ],
        'fallas' => [
            'failure_count' => 'fallas',
            'mtbf_hours' => 'MTBF',
            'mttr_hours' => 'MTTR',
        ],
        'indicadores' => [
            'efficiency_percentage' => 'eficiencia',
            'productivity_tons_per_hour' => 'productividad',
            'availability_percentage' => 'disponibilidad',
        ],
        'energía' => [
            'kwh_grid' => 'kWh red',
            'kwh_genset' => 'kWh planta',
            'kwh_turbine' => 'kWh turbina',
            'kwh_total' => 'kWh total',
            'kwh_per_ton' => 'kWh/t',
            'clean_energy_percentage' => '% limpia',
        ],
        'planta eléctrica' => [
            'genset_hours' => 'horas',
            'genset_fuel_gallons' => 'galones',
            'energy_switch_count' => 'cambios de energía',
        ],
        'protecciones' => [
            'processed_tons_is_manual' => 'toneladas a mano',
            'energy_is_imported' => 'energía de la hoja',
        ],
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
                $before = $this->figuresOf($plant, $month);

                $after = $dryRun
                    ? $this->simulate($service, $plant, $month)
                    : $this->apply($service, $plant, $month);

                $changes = $this->changes($before, $after);

                if ($changes === []) {
                    continue;
                }

                $changed++;
                $this->report($month, $changes);
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
     * Ejecuta el cierre real y lo deshace.
     *
     * Con los eventos apagados: el cierre es auditable, y sin esto cada mes
     * simulado dejaría en la auditoría una corrección que nunca ocurrió.
     *
     * @return array<string, mixed>|null
     */
    private function simulate(PlantKpiService $service, Plant $plant, Carbon $month): ?array
    {
        DB::beginTransaction();

        try {
            Model::withoutEvents(fn () => $service->snapshotMonth($plant, $month->year, $month->month));

            return $this->figuresOf($plant, $month);
        } finally {
            DB::rollBack();
        }
    }

    /** @return array<string, mixed>|null */
    private function apply(PlantKpiService $service, Plant $plant, Carbon $month): ?array
    {
        $service->snapshotMonth($plant, $month->year, $month->month);

        return $this->figuresOf($plant, $month);
    }

    /**
     * Todas las cifras guardadas del mes, incluidas las que deriva Postgres.
     *
     * @return array<string, mixed>|null null si el mes no tiene cierre
     */
    private function figuresOf(Plant $plant, Carbon $month): ?array
    {
        $kpi = PlantMonthlyKpi::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->where('year', $month->year)
            ->where('month', $month->month)
            ->first();

        return $kpi === null ? null : Arr::except($kpi->attributesToArray(), self::NOT_FIGURES);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @return array<string, array{0: mixed, 1: mixed}> columna => [antes, después]
     */
    private function changes(?array $before, ?array $after): array
    {
        $changes = [];

        foreach (array_unique([...array_keys($before ?? []), ...array_keys($after ?? [])]) as $column) {
            $old = $before[$column] ?? null;
            $new = $after[$column] ?? null;

            if ($old !== $new) {
                $changes[$column] = [$old, $new];
            }
        }

        return $changes;
    }

    /** @param  array<string, array{0: mixed, 1: mixed}>  $changes */
    private function report(Carbon $month, array $changes): void
    {
        $this->line('  <options=bold>'.$month->format('Y-m').'</>');

        $pending = $changes;

        foreach (self::GROUPS as $group => $labels) {
            $parts = [];

            foreach ($labels as $column => $label) {
                if (! array_key_exists($column, $pending)) {
                    continue;
                }

                $parts[] = $this->describe($label, $pending[$column]);
                unset($pending[$column]);
            }

            if ($parts !== []) {
                $this->groupLine($group, $parts);
            }
        }

        if ($pending !== []) {
            $parts = array_map(
                fn (string $column): string => $this->describe($column, $pending[$column]),
                array_keys($pending),
            );

            $this->groupLine('otras', $parts);
        }
    }

    /**
     * Una línea por grupo, con el nombre del grupo en columna.
     *
     * Se rellena por caracteres y no con `%-17s`, que cuenta bytes: «producción»,
     * «energía» y «planta eléctrica» llevan tilde y quedaban corridos un espacio
     * respecto de «horas» y «fallas».
     *
     * @param  list<string>  $parts
     */
    private function groupLine(string $group, array $parts): void
    {
        $this->line('      '.mb_str_pad($group, 17).' '.implode('  ·  ', $parts));
    }

    /** @param  array{0: mixed, 1: mixed}  $change */
    private function describe(string $label, array $change): string
    {
        return sprintf('%s %s → %s', $label, $this->fmt($change[0]), $this->fmt($change[1]));
    }

    private function fmt(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'sí' : 'no',
            is_float($value) => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.'),
            default => (string) $value,
        };
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
}
