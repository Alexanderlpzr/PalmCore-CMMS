<?php

namespace App\Filament\Pages;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\LaborCostReport;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Tenant;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Cuánto cuesta el día de la extractora y cuánto va el corte: jornales, recargos, horas
 * extras y alimentación, repartidos por área y por trabajador, en tortas y tablas.
 *
 * Lleva sueldos, así que lo ve quien ve salarios y horas: talento humano.
 */
class CostoDePersonal extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static string|UnitEnum|null $navigationGroup = 'Talento Humano · Día a día';

    protected static ?string $navigationLabel = 'Costo de personal';

    protected static ?string $title = 'Costo de personal';

    /** Justo después del tablero. */
    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'costo-de-personal';

    protected string $view = 'filament.pages.costo-de-personal';

    /** El día que se mira; por omisión, hoy en la planta. */
    #[Url(as: 'dia')]
    public ?string $fecha = null;

    /** Qué muestran las tortas y las tablas: el día o lo que va del corte. */
    #[Url]
    public string $vista = 'dia';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->can('viewAnySalary', Employee::class) ?? false)
            && ($user?->can('viewAny', AttendanceDay::class) ?? false);
    }

    public function mount(): void
    {
        $this->normalize();
    }

    public function updated(): void
    {
        $this->normalize();
    }

    public function cambiarDia(int $dias): void
    {
        $this->fecha = $this->day()->addDays($dias)->toDateString();
        $this->normalize();
    }

    public function irAHoy(): void
    {
        $this->fecha = $this->today()->toDateString();
    }

    public function day(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->fecha);
    }

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now($this->tenant()->plantTimezone())->toDateString());
    }

    public function isToday(): bool
    {
        return $this->day()->equalTo($this->today());
    }

    /**
     * El corte de horas que contiene el día —del 27 al 26 en la extractora—, cortado en el
     * día que se mira: el acumulado es lo que va, no lo que falta.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    #[Computed]
    public function cutoffWindow(): array
    {
        $cutoffDay = (int) app(PayrollParameterService::class)->valueOrDefault(PayrollParameter::HoursCutoffDay, $this->day(), $this->tenant()->id);
        [$from, $to] = PayrollRun::hoursWindowContaining($this->day(), $cutoffDay);

        return [$from, $this->day(), $to];
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function dayReport(): array
    {
        return app(LaborCostReport::class)->forRange($this->tenant(), $this->day(), $this->day());
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function cutoffReport(): array
    {
        [$from, $until] = $this->cutoffWindow;

        return app(LaborCostReport::class)->forRange($this->tenant(), $from, $until);
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        return $this->vista === 'corte' ? $this->cutoffReport : $this->dayReport;
    }

    /**
     * Las cuatro tortas de la vista elegida.
     *
     * @return array<string, array{heading: string, description: ?string, slices: list<array{label: string, value: float, color: string}>}>
     */
    public function charts(): array
    {
        $report = $this->report();
        $areas = $report['areas'];
        $byArea = fn (callable $value): array => array_values(array_map(
            fn (array $area): array => ['label' => $area['label'], 'value' => (float) $value($area), 'color' => $area['color']],
            $areas,
        ));
        $periodo = $this->vista === 'corte' ? 'lo que va del corte' : 'el día';
        $deLoQue = $this->vista === 'corte' ? 'de lo que va del corte' : 'del día';

        return [
            'por_area' => [
                'heading' => 'Costo por área',
                'description' => 'Jornales, recargos, extras y alimentación '.$deLoQue.'.',
                'slices' => $byArea(fn (array $area): float => $area['total']),
            ],
            'por_concepto' => [
                'heading' => 'En qué se va',
                'description' => 'El costo '.$deLoQue.' por concepto.',
                'slices' => array_map(
                    fn (string $key): array => ['label' => LaborCostReport::CONCEPTS[$key], 'value' => (float) $report['totals'][$key], 'color' => LaborCostReport::CONCEPT_COLORS[$key]],
                    array_keys(LaborCostReport::CONCEPTS),
                ),
            ],
            'extras_por_area' => [
                'heading' => 'Horas extras y recargos por área',
                'description' => 'Lo que se pagó por encima del jornal en '.$periodo.'.',
                'slices' => $byArea(fn (array $area): float => $area['overtime'] + $area['surcharges']),
            ],
            'alimentacion_por_area' => [
                'heading' => 'Alimentación por área',
                'description' => 'El auxilio por las comidas '.$deLoQue.'.',
                'slices' => $byArea(fn (array $area): float => $area['meals']),
            ],
        ];
    }

    private function normalize(): void
    {
        $today = $this->today();

        try {
            $day = $this->fecha ? CarbonImmutable::parse($this->fecha) : $today;
        } catch (\Throwable) {
            $day = $today;
        }

        // El futuro no cuesta todavía.
        $this->fecha = $day->greaterThan($today) ? $today->toDateString() : $day->toDateString();
        $this->vista = in_array($this->vista, ['dia', 'corte'], true) ? $this->vista : 'dia';

        unset($this->cutoffWindow, $this->dayReport, $this->cutoffReport);
    }

    private function tenant(): Tenant
    {
        /** @var Tenant */
        return Filament::getTenant();
    }
}
