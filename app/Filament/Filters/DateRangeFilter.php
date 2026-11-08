<?php

namespace App\Filament\Filters;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Filament\Concerns\HasPeriodFilterForm;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Filtrar una tabla por período: un mes, un año, un corte de nómina o un rango libre, con
 * atajos y los dos extremos opcionales.
 *
 * Las páginas de Indicadores tienen su selector de período desde hace tiempo
 * ({@see HasPeriodFilterForm}), pero ese trabaja en meses y vive
 * en el formulario de filtros de un Dashboard, así que ninguna tabla del producto podía
 * usarlo. El resultado era que en Paros —donde la pregunta habitual es «qué pasó la
 * semana pasada»— había que ordenar por fecha y desplazarse.
 *
 * Desde 2026-11-08 elige también cualquier mes o año —no solo «este» y «el pasado»— y, en
 * las tablas de talento humano, cualquier corte de nómina (del 27 al 26): lo pidió la
 * empresa para filtrar y descargar el Excel de cualquier período en todas las tablas.
 *
 * Los dos extremos son opcionales a propósito. «Desde el 1 de agosto» sin fecha final es
 * la consulta más frecuente al revisar lo que lleva el mes, y obligar a poner las dos
 * fechas convierte una pregunta en dos.
 *
 * Se compara con `whereDate` y no con el timestamp completo: quien escribe «hasta el 30»
 * quiere incluir el día 30 entero, no cortarlo a las 00:00. Es el error que hace que
 * falte justo el último día.
 */
class DateRangeFilter
{
    /** Cuántos meses —y cortes— hacia atrás ofrece el selector. */
    private const MONTHS_BACK = 36;

    /**
     * Los atajos, y qué ventana escribe cada uno.
     *
     * `startOfMonth()` va **antes** de restar, siempre. Restarle un mes a un 31 de agosto
     * cae en el 31 de junio, que no existe, y Carbon lo desborda al 1 de julio: «Mes
     * pasado» acabaría trayendo julio y agosto mezclados. Solo falla un día de cada
     * treinta y uno, que es justo lo que hace que nadie lo note.
     *
     * @return array<string, array{label: string, desde: Carbon, hasta: Carbon}>
     */
    private static function atajos(): array
    {
        $hoy = Carbon::today();
        $mes = $hoy->copy()->startOfMonth();

        return [
            'este_mes' => [
                'label' => 'Este mes',
                'desde' => $mes->copy(),
                'hasta' => $mes->copy()->endOfMonth(),
            ],
            'mes_pasado' => [
                'label' => 'Mes pasado',
                'desde' => $mes->copy()->subMonth(),
                'hasta' => $mes->copy()->subMonth()->endOfMonth(),
            ],
            'ultimos_7' => [
                'label' => 'Últimos 7 días',
                'desde' => $hoy->copy()->subDays(6),
                'hasta' => $hoy->copy(),
            ],
            'este_anio' => [
                'label' => 'Este año',
                'desde' => $hoy->copy()->startOfYear(),
                'hasta' => $hoy->copy()->endOfYear(),
            ],
        ];
    }

    /**
     * Lo que ofrece el selector «Mes, año o corte», agrupado: los cortes de nómina (solo con
     * día de corte), los meses y los años. Las claves dicen qué son —`corte:2026-11`,
     * `mes:2026-11`, `anio:2026`— y {@see periodWindow()} las convierte en fechas.
     *
     * @return array<string, array<string, string>>
     */
    public static function periodOptions(?int $cutoffDay = null): array
    {
        $month = CarbonImmutable::today()->startOfMonth();
        $options = [];

        if ($cutoffDay) {
            // El corte en curso puede ser el del mes que viene: con corte 26, el 27 de
            // noviembre ya corre el de diciembre.
            $current = PayrollRun::hoursWindowContaining(CarbonImmutable::today(), $cutoffDay);
            $cutoffMonth = $current[1]->startOfMonth();

            for ($i = 0; $i < self::MONTHS_BACK; $i++) {
                $m = $cutoffMonth->subMonthsNoOverflow($i);
                [$desde, $hasta] = self::periodWindow('corte:'.$m->format('Y-m'), $cutoffDay);
                $options['Cortes de nómina']['corte:'.$m->format('Y-m')] = 'Corte de '.self::monthName($m).' ('.$desde->format('d/m').' al '.$hasta->format('d/m').')';
            }
        }

        for ($i = 0; $i < self::MONTHS_BACK; $i++) {
            $m = $month->subMonthsNoOverflow($i);
            $options['Meses']['mes:'.$m->format('Y-m')] = ucfirst(self::monthName($m));
        }

        for ($year = $month->year; $year > $month->year - (int) ceil(self::MONTHS_BACK / 12); $year--) {
            $options['Años']['anio:'.$year] = 'Año '.$year;
        }

        return $options;
    }

    /**
     * Las fechas de una opción del selector: el corte (del día siguiente al corte del mes
     * anterior al día de corte), el mes calendario o el año.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public static function periodWindow(?string $period, ?int $cutoffDay = null): ?array
    {
        if (! $period || ! str_contains($period, ':')) {
            return null;
        }

        [$kind, $value] = explode(':', $period, 2);

        return match ($kind) {
            'corte' => $cutoffDay ? PayrollRun::hoursWindowFor(CarbonImmutable::parse($value.'-01'), $cutoffDay) : null,
            'mes' => [CarbonImmutable::parse($value.'-01'), CarbonImmutable::parse($value.'-01')->endOfMonth()->startOfDay()],
            'anio' => [CarbonImmutable::create((int) $value, 1, 1), CarbonImmutable::create((int) $value, 12, 31)],
            default => null,
        };
    }

    /**
     * La ventana que de verdad se va a consultar, resuelta en un solo sitio.
     *
     * Las fechas mandan cuando están puestas; si no, el mes, año o corte elegido, y si
     * tampoco, el atajo. Que esto viva aquí y no en el `afterStateUpdated` del botón no es
     * un detalle: mientras el significado del atajo estuvo en un callback de la interfaz,
     * el filtro no se podía ejercer desde un test ni desde una URL — el estado llegaba con
     * `atajo` puesto y sin fechas, y no filtraba nada en silencio.
     *
     * El botón y el selector siguen escribiendo las fechas, pero eso es para que el usuario
     * las vea y las pueda corregir, no para decidir qué se muestra.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: ?string, 1: ?string}
     */
    public static function window(array $data, ?int $cutoffDay = null): array
    {
        $atajo = self::atajos()[$data['atajo'] ?? ''] ?? ['desde' => null, 'hasta' => null];
        $period = self::periodWindow($data['periodo'] ?? null, $cutoffDay);

        return [
            ($data['desde'] ?? null) ?: ($period[0] ?? null)?->toDateString() ?: $atajo['desde']?->toDateString(),
            ($data['hasta'] ?? null) ?: ($period[1] ?? null)?->toDateString() ?: $atajo['hasta']?->toDateString(),
        ];
    }

    /**
     * @param  ?string  $column  la columna de fecha que filtra; sin ella el filtro solo elige
     *                           la ventana, para las tablas que la usan para calcular (como
     *                           las de talento humano, que listan trabajadores)
     * @param  ?int  $cutoffDay  el día de corte de la nómina: ofrece los cortes del 27 al 26
     * @param  ?string  $defaultPeriod  lo que trae puesto, como `corte:2026-11`
     */
    public static function make(
        ?string $column = 'created_at',
        string $label = 'Fecha',
        string $name = 'rango_de_fechas',
        ?int $cutoffDay = null,
        ?string $defaultPeriod = null,
    ): Filter {
        $atajos = self::atajos();
        $default = self::periodWindow($defaultPeriod, $cutoffDay);

        return Filter::make($name)
            ->schema([
                // Un mes, un año o un corte cualquiera, buscable. Escribe las fechas para que
                // se vean; quién manda lo decide {@see window()}.
                Select::make('periodo')
                    ->label($cutoffDay ? 'Mes, año o corte' : 'Mes o año')
                    ->options(self::periodOptions($cutoffDay))
                    ->searchable()
                    ->placeholder('Elegir…')
                    ->default($defaultPeriod)
                    ->columnSpan(2)
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set) use ($cutoffDay): void {
                        $window = self::periodWindow($state, $cutoffDay);

                        $set('atajo', null);
                        $set('desde', $window ? $window[0]->toDateString() : null);
                        $set('hasta', $window ? $window[1]->toDateString() : null);
                    }),

                DatePicker::make('desde')
                    ->label("{$label} desde")
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default($default ? $default[0]->toDateString() : null)
                    ->maxDate(fn (Get $get) => $get('hasta') ?: null)
                    ->live()
                    // Tocar una fecha a mano apaga el atajo y el mes: si no, quedaría un botón
                    // resaltado diciendo «Este mes» mientras las fechas dicen otra cosa.
                    ->afterStateUpdated(function (Set $set): void {
                        $set('atajo', null);
                        $set('periodo', null);
                    }),

                DatePicker::make('hasta')
                    ->label("{$label} hasta")
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default($default ? $default[1]->toDateString() : null)
                    ->minDate(fn (Get $get) => $get('desde') ?: null)
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('atajo', null);
                        $set('periodo', null);
                    }),

                // Botones y no un desplegable: un desplegable son dos clics, uno solo para
                // abrirlo, y los atajos existen para ahorrar clics.
                ToggleButtons::make('atajo')
                    ->hiddenLabel()
                    ->inline()
                    ->grouped()
                    ->options(array_map(fn (array $a): string => $a['label'], $atajos))
                    ->columnSpanFull()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set) use ($atajos): void {
                        if ($state === null) {
                            return;
                        }

                        $set('periodo', null);
                        $set('desde', $atajos[$state]['desde']->toDateString());
                        $set('hasta', $atajos[$state]['hasta']->toDateString());
                    }),
            ])
            // El filtro ocupa la fila entera de la rejilla de filtros: el mes y las dos fechas
            // arriba, los atajos debajo. Encajado en una sola columna —un tercio del ancho—
            // los botones se salían de su celda y se montaban encima del filtro de al lado.
            ->columnSpanFull()
            ->columns(4)
            ->query(function (Builder $query, array $data) use ($column, $cutoffDay): Builder {
                if ($column === null) {
                    return $query;
                }

                [$desde, $hasta] = self::window($data, $cutoffDay);

                return $query
                    ->when($desde, fn (Builder $q, string $d): Builder => $q->whereDate($column, '>=', $d))
                    ->when($hasta, fn (Builder $q, string $h): Builder => $q->whereDate($column, '<=', $h));
            })
            // Sin esto, un filtro de fecha aplicado no se ve en ninguna parte: la tabla
            // muestra menos filas y nada dice por qué. Es el fallo que hace pensar que
            // faltan datos, y pesa más ahora que el filtro se guarda entre visitas.
            ->indicateUsing(function (array $data) use ($label, $atajos, $cutoffDay): array {
                [$desde, $hasta] = self::window($data, $cutoffDay);

                // El atajo o el mes se dicen por su nombre cuando son ellos quienes mandan; en
                // cuanto las fechas dejan de coincidir con su ventana, se dicen las fechas.
                $period = self::periodWindow($data['periodo'] ?? null, $cutoffDay);

                if ($period && $desde === $period[0]->toDateString() && $hasta === $period[1]->toDateString()) {
                    $name = collect(self::periodOptions($cutoffDay))->collapse()->get($data['periodo']);

                    return ["{$label}: ".mb_strtolower((string) $name)];
                }

                $atajo = $atajos[$data['atajo'] ?? ''] ?? null;

                if ($atajo
                    && $desde === $atajo['desde']->toDateString()
                    && $hasta === $atajo['hasta']->toDateString()) {
                    return ["{$label}: ".mb_strtolower($atajo['label'])];
                }

                $indicators = [];

                if ($desde) {
                    $indicators[] = "{$label} desde ".Carbon::parse($desde)->format('d/m/Y');
                }

                if ($hasta) {
                    $indicators[] = "{$label} hasta ".Carbon::parse($hasta)->format('d/m/Y');
                }

                return $indicators;
            });
    }

    /** El día de corte de la nómina de la empresa abierta, o null si no corta. */
    public static function tenantCutoffDay(): ?int
    {
        $day = (int) app(PayrollParameterService::class)->valueOrDefault(
            PayrollParameter::HoursCutoffDay,
            CarbonImmutable::today(),
            (string) Filament::getTenant()?->getKey(),
        );

        return $day > 0 ? $day : null;
    }

    /**
     * El selector de mes, año o corte suelto, para meterlo en un filtro de fechas que ya
     * existe y tiene su propia lógica: escribe sus campos `desde` y `hasta`.
     */
    public static function periodSelect(?int $cutoffDay = null, string $from = 'desde', string $to = 'hasta'): Select
    {
        return Select::make('periodo')
            ->label($cutoffDay ? 'Mes, año o corte' : 'Mes o año')
            ->options(self::periodOptions($cutoffDay))
            ->searchable()
            ->placeholder('Elegir…')
            ->live()
            ->afterStateUpdated(function (?string $state, Set $set) use ($cutoffDay, $from, $to): void {
                $window = self::periodWindow($state, $cutoffDay);

                if ($window) {
                    $set($from, $window[0]->toDateString());
                    $set($to, $window[1]->toDateString());
                }
            });
    }

    private static function monthName(CarbonImmutable $month): string
    {
        return $month->locale('es')->translatedFormat('F Y');
    }
}
