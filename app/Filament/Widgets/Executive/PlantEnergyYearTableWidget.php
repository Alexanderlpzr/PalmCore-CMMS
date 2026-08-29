<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Services\MonthlyEnergyCorrectionService;
use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Analytics\Support\DashboardPeriod;
use App\Domain\Energy\Services\EnergyMeterReadingService;
use App\Exceptions\BusinessRuleException;
use App\Models\EnergyMeter;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\ProductionCalendarDay;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * El año en una tabla: un mes por fila.
 *
 * Antes iba al revés —parámetros en filas y los doce meses en columnas, como la hoja de
 * cálculo de la planta— y esa forma se conservó a propósito. Dejó de servir en cuanto la
 * pantalla no la pudo mostrar entera: doce columnas obligaban a desplazarse a lo ancho y
 * para leer octubre había que arrastrar la tabla a ciegas. Doce filas por ocho columnas
 * caben, y se lee como un libro de cuentas.
 *
 * Un mes sin dato muestra «—», no cero ni `#DIV/0!`. La hoja ponía cero en los meses que
 * aún no habían ocurrido, y un cero es una afirmación: dice que la planta no consumió.
 *
 * Y la fila del año no promedia ratios: el KWh/RFF anual es el total de kWh partido por el
 * total de fruta. Promediar doce ratios daría el mismo peso a un mes flojo que a uno de
 * plena cosecha.
 */
class PlantEnergyYearTableWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;

    // Los dos traits declaran `__get` y PHP no elige por su cuenta. Gana el de los
    // esquemas, que es el que resuelve `$this->editMonthAction`; el de los filtros solo
    // existe como alias retrocompatible de `$this->filters`, que aquí no se usa —este
    // widget lee `$this->pageFilters` directamente.
    use InteractsWithPageFilters, InteractsWithSchemas {
        InteractsWithSchemas::__get insteadof InteractsWithPageFilters;
    }

    protected string $view = 'filament.widgets.plant-energy-year-table';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * Los meses cuyo detalle diario está desplegado.
     *
     * Antes era uno solo: abrir marzo cerraba agosto, y comparar dos meses obligaba a ir
     * y volver. Es la diferencia real con la agrupación de Equipos, donde varias secciones
     * pueden estar abiertas a la vez.
     *
     * @var list<int>
     */
    public array $openMonths = [];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $plant = $this->selectedPlant();
        $year = $this->selectedYear();

        if ($plant === null) {
            return ['year' => $year, 'rows' => [], 'totals' => [], 'empty' => true, 'canEdit' => false, 'openMonths' => [], 'dailyDetails' => []];
        }

        $rows = app(PlantKpiService::class)->monthlyEnergyRows($plant, $year);

        // El detalle solo de los meses desplegados: se sigue consultando un mes por mes
        // abierto, no los doce.
        $detalles = [];

        foreach ($this->openMonths as $month) {
            $detalles[$month] = $this->dailyDetail($plant, $month);
        }

        return [
            'year' => $year,
            'months' => $this->monthOptions(),
            'rows' => $rows,
            'totals' => $this->yearTotals($rows),
            'empty' => false,
            'canEdit' => $this->canEditEnergy(),
            'openMonths' => $this->openMonths,
            'dailyDetails' => $detalles,
        ];
    }

    /**
     * El total del año.
     *
     * Los ratios se recalculan sobre los totales, no se promedian: es la misma regla que
     * la fila del año tenía antes de dar la vuelta a la tabla, y sigue siendo la correcta.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, ?float>
     */
    private function yearTotals(array $rows): array
    {
        $sum = function (string $field) use ($rows): ?float {
            $known = array_filter(array_column($rows, $field), fn (?float $v): bool => $v !== null);

            return $known === [] ? null : round(array_sum($known), 2);
        };

        $tons = $sum('processed_tons');
        $total = $sum('kwh_total');
        $turbine = $sum('kwh_turbine');

        return [
            'processed_tons' => $tons,
            'kwh_total' => $total,
            'kwh_grid' => $sum('kwh_grid'),
            'kwh_genset' => $sum('kwh_genset'),
            'kwh_turbine' => $turbine,
            'kwh_per_ton' => ($total !== null && $tons > 0) ? round($total / $tons, 2) : null,
            'clean_energy_percentage' => ($turbine !== null && $total > 0)
                ? round($turbine / $total * 100, 2)
                : null,
        ];
    }

    // ── El detalle diario ─────────────────────────────────────────────────────

    /** Despliega o pliega el detalle de un mes, sin tocar los demás. */
    public function toggleMonth(int $month): void
    {
        $this->openMonths = in_array($month, $this->openMonths, strict: true)
            ? array_values(array_diff($this->openMonths, [$month]))
            : [...$this->openMonths, $month];
    }

    /** Pliega los meses desplegados de una vez, para volver a ver el año entero. */
    public function collapseAllMonths(): void
    {
        $this->openMonths = [];
    }

    /**
     * Los días del mes abierto, de solo lectura.
     *
     * Reutiliza tal cual la serie que ya alimenta la pantalla de Energía. Aquí no se
     * corrige: escribir sigue siendo un único camino, la ronda, que es donde vive el aviso
     * del dígito de más. Un segundo camino de escritura fue exactamente lo que dejó tres
     * puertas abiertas en el tope de toneladas.
     *
     * @return array<string, mixed>
     */
    private function dailyDetail(Plant $plant, int $month): array
    {
        $meters = EnergyMeter::query()
            ->where('plant_id', $plant->id)
            ->active()
            ->orderBy('sort_order')
            ->get();

        $serie = app(EnergyMeterReadingService::class)->monthReadings(
            $meters,
            Carbon::create($this->selectedYear(), $month, 1),
        );

        // Un mes pasado siempre trae sus treinta y un días, tenga lecturas o no. Sin esta
        // comprobación, abrir un mes vacío pintaba treinta y una filas de guiones en vez
        // de decir que no hay nada que ver.
        $hayLecturas = collect($serie['days'])->contains(
            fn (array $day): bool => collect($day['cells'])->contains(
                fn (array $cell): bool => $cell['accumulated'] !== null,
            ),
        );

        return ['meters' => $meters, 'has_readings' => $hayLecturas, ...$serie];
    }

    // ── Corregir un mes ───────────────────────────────────────────────────────

    /**
     * Corrige a mano el total de un mes.
     *
     * Ofrece solo las cuatro cifras que se escriben. Las otras tres las calcula el sistema
     * y se mueven solas al guardar: ofrecerlas como campos permitiría que el total dejara
     * de cuadrar con sus partes, que es justo lo que esta planilla existe para impedir.
     */
    public function editMonthAction(): Action
    {
        return Action::make('editMonth')
            ->label('Corregir un mes')
            ->icon('heroicon-o-pencil-square')
            ->modalHeading('Corregir un mes de '.$this->selectedYear())
            ->modalDescription('Se eligen el mes y sus cuatro cifras. Los tres indicadores derivados se recalculan solos al guardar.')
            ->modalSubmitActionLabel('Guardar la corrección')
            ->schema([
                Select::make('month')
                    ->label('Mes')
                    ->options(fn (): array => $this->monthOptions())
                    ->default((int) now()->month)
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        foreach ($this->currentValues((int) $state) as $campo => $valor) {
                            $set($campo, $valor);
                        }
                    }),

                Text::make(fn (Get $get): string => $this->editWarning((int) $get('month'))
                    ?? 'Este mes no tiene lecturas diarias detrás: corregirlo aquí es la única forma de arreglarlo.'),

                TextInput::make('processed_tons')
                    ->label('RFF/MES — fruta procesada (toneladas)')
                    ->helperText('En toneladas, no en kilos. Un mes de esta planta son unas 5.000–6.800 t.')
                    ->numeric()
                    ->minValue(0)
                    ->visible(fn (): bool => $this->canEditTons()),
                TextInput::make('kwh_grid')
                    ->label('KWh red pública')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('kwh_genset')
                    ->label('KWh planta eléctrica')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('kwh_turbine')
                    ->label('KWh turbina')
                    ->helperText('Déjalo vacío si no se sabe. Vacío y cero no son lo mismo: cero afirma que la turbina no generó nada.')
                    ->numeric()
                    ->minValue(0),

                Text::make('KWh TOTAL, KWh/RFF y ENERGÍA LIMPIA no se teclean: el sistema los calcula con estas cuatro cifras.'),
            ])
            ->fillForm(fn (): array => ['month' => (int) now()->month] + $this->currentValues((int) now()->month))
            ->action(function (array $data): void {
                $plant = $this->selectedPlant();

                if ($plant === null) {
                    return;
                }

                $month = (int) $data['month'];

                try {
                    app(MonthlyEnergyCorrectionService::class)
                        ->apply($plant, $this->selectedYear(), $month, $data);
                } catch (BusinessRuleException $e) {
                    Notification::make()->title($e->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()
                    ->title($this->monthName($month).' corregido')
                    ->body('Los indicadores derivados se recalcularon solos.')
                    ->success()
                    ->send();
            })
            ->visible(fn (): bool => $this->canEditEnergy());
    }

    /**
     * Devuelve el mes a lo que dicen los contadores.
     *
     * Sin esta acción, corregir un mes con lecturas diarias detrás sería una puerta de un
     * solo sentido: quedaría fijado a mano para siempre y dejaría de reflejar en silencio
     * lo que el operario sigue anotando cada día.
     */
    public function recalculateMonthAction(): Action
    {
        return Action::make('recalculateMonth')
            ->label('Deshacer una corrección')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->modalHeading('Volver a lo que dicen los contadores')
            ->modalDescription('Se descarta la corrección manual y el mes vuelve a lo que suman sus lecturas diarias.')
            ->modalSubmitActionLabel('Deshacer')
            ->schema([
                Select::make('month')
                    ->label('Mes corregido a mano')
                    ->options(fn (): array => $this->recalculableMonthOptions())
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data): void {
                $plant = $this->selectedPlant();

                if ($plant === null) {
                    return;
                }

                $month = (int) $data['month'];

                app(MonthlyEnergyCorrectionService::class)
                    ->recalculateFromReadings($plant, $this->selectedYear(), $month);

                Notification::make()
                    ->title($this->monthName($month).' recalculado')
                    ->body('Vuelve a seguir las lecturas diarias de los contadores.')
                    ->success()
                    ->send();
            })
            // Solo cuando hay algo que deshacer: un mes fijado a mano que además tiene
            // lecturas diarias a las que volver.
            ->visible(fn (): bool => $this->canEditEnergy() && $this->recalculableMonths() !== []);
    }

    /** @return array<int, string> */
    private function monthOptions(): array
    {
        $opciones = [];

        for ($m = 1; $m <= 12; $m++) {
            $opciones[$m] = ucfirst(Carbon::create($this->selectedYear(), $m, 1)->translatedFormat('F'));
        }

        return $opciones;
    }

    /** @return array<int, string> */
    private function recalculableMonthOptions(): array
    {
        $nombres = $this->monthOptions();

        return array_reduce(
            $this->recalculableMonths(),
            fn (array $carry, int $m): array => $carry + [$m => $nombres[$m]],
            [],
        );
    }

    /** @return array<string, float|null> */
    private function currentValues(int $month): array
    {
        $plant = $this->selectedPlant();

        $kpi = $plant === null ? null : PlantMonthlyKpi::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->where('year', $this->selectedYear())
            ->where('month', $month)
            ->first();

        return [
            // El cero se ofrece vacío: un mes sin cargar no debe parecer un mes sin fruta.
            'processed_tons' => $kpi?->processed_tons ?: null,
            'kwh_grid' => $kpi?->kwh_grid,
            'kwh_genset' => $kpi?->kwh_genset,
            'kwh_turbine' => $kpi?->kwh_turbine,
        ];
    }

    /** El aviso cuando el mes que se va a corregir sí tiene lecturas diarias detrás. */
    private function editWarning(int $month): ?string
    {
        $plant = $this->selectedPlant();

        if ($plant === null) {
            return null;
        }

        if (! app(MonthlyEnergyCorrectionService::class)->hasDailyReadings($plant, $this->selectedYear(), $month)) {
            return null;
        }

        return 'Este mes tiene lecturas diarias de contador detrás. Si lo corriges aquí, dejará '
            .'de seguirlas y quedará fijado a mano. Para arreglar una lectura suelta es mejor '
            .'corregirla en la pantalla «Energía».';
    }

    private function monthName(int $month): string
    {
        return Carbon::create($this->selectedYear(), $month, 1)->translatedFormat('F Y');
    }

    /**
     * Los meses a los que de verdad se puede volver.
     *
     * Exige las dos condiciones: estar fijado a mano **y** tener lecturas diarias detrás.
     * Ofrecerlo sobre un mes importado del Excel —que no tiene ninguna— era un botón que
     * borraba el mes: limpiaba las marcas y recalculaba sobre cero lecturas.
     *
     * @return list<int>
     */
    private function recalculableMonths(): array
    {
        $plant = $this->selectedPlant();

        if ($plant === null) {
            return [];
        }

        $service = app(MonthlyEnergyCorrectionService::class);

        $manuales = PlantMonthlyKpi::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->where('year', $this->selectedYear())
            ->where(fn ($q) => $q->where('energy_is_imported', true)->orWhere('processed_tons_is_manual', true))
            ->pluck('month')
            ->all();

        return array_values(array_filter(
            $manuales,
            fn (int $month): bool => $service->hasDailyReadings($plant, $this->selectedYear(), $month),
        ));
    }

    private function selectedYear(): int
    {
        $filters = $this->pageFilters;

        if (($filters['preset'] ?? null) === 'range') {
            return (int) ($filters['range_year'] ?? now()->year);
        }

        if (isset($filters['year']) && $filters['year'] !== null) {
            return (int) $filters['year'];
        }

        // «Últimos 12 meses» no tiene año propio: se muestra el del final de la ventana,
        // que es el año en el que la planta está trabajando.
        [, $to] = DashboardPeriod::snapshotWindow($filters);

        return (int) $to->year;
    }

    private function selectedPlant(): ?Plant
    {
        $plantId = $this->pageFilters['plant_id'] ?? null;

        return $plantId !== null
            ? Plant::find($plantId)
            : Plant::orderBy('name')->first();
    }

    private function canEditEnergy(): bool
    {
        return auth()->user()?->can('create', EnergyMeter::class) ?? false;
    }

    /**
     * La fruta es dato de producción, no de energía: se rige por su propio permiso aunque
     * se corrija desde esta pantalla.
     */
    private function canEditTons(): bool
    {
        return auth()->user()?->can('create', ProductionCalendarDay::class) ?? false;
    }
}
