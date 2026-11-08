<?php

namespace App\Filament\Pages;

use App\Domain\HumanResources\Services\HoursFactorReport;
use App\Domain\Reports\Excel\FrondaWorkbook;
use App\Models\PayrollRun;
use App\Models\Tenant;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

/**
 * El indicador «factor de horas» de la extractora (TH-INF-F-002), hecho de las horas del
 * reloj en vez de tecleado: por grupo, lo ordinario, los recargos y las horas extras
 * —incluidas las que se pagan como bonificación— y la comparación con el periodo anterior.
 *
 * Dos lecturas: «A la fecha», lo que va del corte hasta un día, como el «semana 1, 2…» que
 * talento humano saca a mitad de mes; y «Por nómina», la nómina ya liquidada.
 *
 * Lo ve quien ve la nómina: talento humano.
 */
class FactorDeHoras extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Talento Humano · Cierre del corte';

    protected static ?string $navigationLabel = 'Factor de horas';

    protected static ?string $title = 'Factor de horas';

    /** Justo después de «Nóminas»: es su lectura. */
    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'factor-de-horas';

    protected string $view = 'filament.pages.factor-de-horas';

    /** «fecha»: lo que va del corte; «nomina»: una nómina liquidada. */
    #[Url(as: 'vista')]
    public string $mode = 'fecha';

    /** Hasta qué día se suma el corte, en la dirección para poder compartirla. */
    #[Url(as: 'hasta')]
    public ?string $until = null;

    /** La nómina que se mira, en la dirección para poder compartirla. */
    #[Url(as: 'nomina')]
    public ?string $runId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', PayrollRun::class) ?? false;
    }

    public function mount(): void
    {
        if (! in_array($this->mode, ['fecha', 'nomina'], true)) {
            $this->mode = 'fecha';
        }

        $this->until = $this->untilDate()->toDateString();

        if ($this->runId === null || ! $this->runs()->has($this->runId)) {
            $this->runId = $this->runs()->keys()->first();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('excel')
                ->label('Descargar Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->action(fn (): ?StreamedResponse => $this->excel()),
        ];
    }

    /**
     * El indicador que se está mirando —el corte hasta el día elegido o la nómina— en Excel,
     * con los renglones del TH-INF-F-002 y el período anterior al lado para comparar.
     */
    public function excel(): ?StreamedResponse
    {
        $report = $this->report();

        if (! $report) {
            return null;
        }

        $current = HoursFactorReport::excelRows($report['current']);
        $previous = $report['previous'] ? HoursFactorReport::excelRows($report['previous']) : null;
        $previousOf = fn (string $section, int $i, string $field): ?float => $previous ? (float) $previous[$section][$i][$field] : null;

        $section = function (string $key, string $title) use ($current, $previousOf): array {
            $rows = [[$title, '', '', '', '']];

            foreach ($current[$key] as $i => $row) {
                $rows[] = [$row['label'], (float) $row['hours'], (float) $row['amount'], $previousOf($key, $i, 'hours'), $previousOf($key, $i, 'amount')];
            }

            return $rows;
        };

        $rows = [
            ...$section('ordinary', 'Horas ordinarias'),
            ...$section('withSurcharges', 'Horas con recargos'),
            ['Total nómina de personal de producción y mantenimiento', $current['payrollTotal']['hours'], $current['payrollTotal']['amount'], $previous['payrollTotal']['hours'] ?? null, $previous['payrollTotal']['amount'] ?? null],
            ...$section('overtime', 'Horas sin recargos'),
            ['Total horas extras de producción y mantenimiento sin recargos', $current['overtimeTotal']['hours'], $current['overtimeTotal']['amount'], $previous['overtimeTotal']['hours'] ?? null, $previous['overtimeTotal']['amount'] ?? null],
        ];

        $formats = [2 => FrondaWorkbook::HOURS, 3 => FrondaWorkbook::MONEY, 4 => FrondaWorkbook::HOURS, 5 => FrondaWorkbook::MONEY];

        return FrondaWorkbook::create()
            ->sheet('Factor de horas')
            ->widths([1 => 70, 2 => 12, 3 => 18, 4 => 12, 5 => 18])
            ->banner(
                'Indicador factor de horas · '.$report['title'],
                'Del '.$report['from']->format('d/m/Y').' al '.$report['to']->format('d/m/Y').($report['estimated'] ? ' (estimado: hay horas sin confirmar)' : '').($report['previousLabel'] ? ' · comparado con '.$report['previousLabel'] : ''),
                Filament::getTenant()?->name,
                5,
            )
            ->table(['Detalle de horas', 'Horas', 'Valor', 'Horas antes', 'Valor antes'], $rows, $formats)
            ->footer()
            ->download('FACTOR-DE-HORAS-'.$report['from']->format('Ymd').'-'.$report['to']->format('Ymd').'.xlsx');
    }

    /** El día de la planta. */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now($this->tenant()->plantTimezone())->toDateString());
    }

    /** El día hasta el que se suma; uno inválido o futuro vuelve a hoy. */
    public function untilDate(): CarbonImmutable
    {
        try {
            $day = CarbonImmutable::parse((string) $this->until);
        } catch (Throwable) {
            return $this->today();
        }

        return $this->until === null || $day->greaterThan($this->today()) ? $this->today() : $day;
    }

    /**
     * Las nóminas ya liquidadas, de la más reciente a la más antigua.
     *
     * @return Collection<string, string>
     */
    public function runs(): Collection
    {
        return PayrollRun::query()
            ->whereNotNull('calculated_at')
            ->orderByDesc('period_start')
            ->get()
            ->mapWithKeys(fn (PayrollRun $run): array => [$run->getKey() => $run->name]);
    }

    /**
     * El informe que se mira y el del periodo anterior, para comparar.
     *
     * @return array{key: string, title: string, from: CarbonImmutable, to: CarbonImmutable, estimated: bool, current: array<string, mixed>, previousLabel: ?string, previous: ?array<string, mixed>}|null
     */
    public function report(): ?array
    {
        $service = app(HoursFactorReport::class);

        if ($this->mode === 'fecha') {
            $current = $service->toDate($this->tenant(), $this->untilDate());
            $previous = $service->sameStretchBefore($this->tenant(), $current['from'], $current['until']);

            return [
                'key' => 'fecha-'.$current['until']->toDateString(),
                'title' => 'Corte al '.$current['until']->format('d/m/Y'),
                'from' => $current['from'],
                'to' => $current['until'],
                'estimated' => $current['estimated'],
                'current' => $current,
                'previousLabel' => 'Corte anterior al '.$previous['until']->format('d/m'),
                'previous' => $previous,
            ];
        }

        $run = $this->runId ? PayrollRun::query()->find($this->runId) : null;

        if (! $run) {
            return null;
        }

        $previousRun = $service->previousRun($run);

        return [
            'key' => 'nomina-'.$run->getKey(),
            'title' => $run->name,
            'from' => CarbonImmutable::instance($run->hoursFrom()),
            'to' => CarbonImmutable::instance($run->hoursTo()),
            'estimated' => false,
            'current' => $service->forRun($run),
            'previousLabel' => $previousRun?->name,
            'previous' => $previousRun ? $service->forRun($previousRun) : null,
        ];
    }

    /** Cuánto se guarda el indicador de los tableros antes de volver a calcularlo. */
    public const DASHBOARD_INDICATOR_MINUTES = 5;

    /**
     * El indicador de los tableros (TH-INF-F-002) de lo que va del corte hasta hoy, o null
     * para quien no ve la nómina: trae sueldos.
     *
     * Se guarda unos minutos por empresa y por día: recalcular el corte entero cuesta
     * segundos en el servidor y los tableros se abren a cada rato. Lo que cambie en las
     * horas aparece, a lo sumo, en `DASHBOARD_INDICATOR_MINUTES` minutos.
     *
     * @return array<string, mixed>|null
     */
    public static function dashboardIndicator(Tenant $tenant): ?array
    {
        if (! static::canAccess()) {
            return null;
        }

        $today = CarbonImmutable::now($tenant->plantTimezone())->toDateString();

        // Las fechas se guardan como texto: la caché de producción no deserializa objetos
        // (`cache.serializable_classes`), y un CarbonImmutable volvía como objeto incompleto.
        $indicator = Cache::remember(
            "indicador-factor-de-horas:v2:{$tenant->getKey()}:{$today}",
            now()->addMinutes(self::DASHBOARD_INDICATOR_MINUTES),
            function () use ($tenant, $today): array {
                $report = app(HoursFactorReport::class)->toDate($tenant, CarbonImmutable::parse($today));

                return HoursFactorReport::excelRows($report) + [
                    'from' => CarbonImmutable::instance($report['from'])->toIso8601String(),
                    'until' => CarbonImmutable::instance($report['until'])->toIso8601String(),
                    'estimated' => $report['estimated'],
                    'calculatedAt' => CarbonImmutable::now($tenant->plantTimezone())->toIso8601String(),
                ];
            },
        );

        // Cada fecha vuelve con el desfase con que se guardó, sin pasarla a otra zona.
        return [
            'from' => CarbonImmutable::parse($indicator['from']),
            'until' => CarbonImmutable::parse($indicator['until']),
            'calculatedAt' => CarbonImmutable::parse($indicator['calculatedAt']),
            'url' => static::getUrl(['vista' => 'fecha', 'hasta' => $today], tenant: $tenant),
        ] + $indicator;
    }

    private function tenant(): Tenant
    {
        /** @var Tenant */
        return Filament::getTenant();
    }
}
