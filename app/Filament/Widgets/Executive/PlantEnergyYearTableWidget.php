<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Services\MonthlyEnergyCorrectionService;
use App\Domain\Analytics\Support\DashboardPeriod;
use App\Exceptions\BusinessRuleException;
use App\Models\EnergyMeter;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\ProductionCalendarDay;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * El año entero en una tabla: parámetros en filas, meses en columnas.
 *
 * Es la forma en que la planta lee sus indicadores desde antes de que existiera el
 * sistema, y por eso la tabla se conserva con las mismas etiquetas de su planilla —
 * RFF/MES, KWh/RFF, KWh TOTAL, ENERGÍA LIMPIA—. Cambiarlas obligaría a traducir mentalmente
 * cada vez.
 *
 * Con dos diferencias deliberadas respecto de la hoja:
 *
 *   - Un mes sin dato muestra «—», no `#DIV/0!` ni un cero. La hoja pone cero en los
 *     meses que aún no han ocurrido, y un cero es una afirmación: dice que la planta no
 *     consumió. El guion no afirma nada, que es la verdad.
 *   - El total de la fila no suma los meses vacíos, así que la columna «Año» es el
 *     acumulado real de lo que se sabe, no de lo que se dejó en blanco.
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
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $plant = $this->selectedPlant();
        $year = $this->selectedYear();

        if ($plant === null) {
            return ['year' => $year, 'months' => [], 'rows' => [], 'empty' => true];
        }

        $kpis = PlantMonthlyKpi::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->where('year', $year)
            ->get()
            ->keyBy('month');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = mb_strtoupper(
                Carbon::create($year, $m, 1)->translatedFormat('F')
            );
        }

        return [
            'year' => $year,
            'months' => $months,
            'rows' => $this->buildRows($kpis),
            'empty' => false,
            'canEdit' => $this->canEditEnergy(),
            // La vuelta atrás solo tiene sentido donde hay a qué volver: un mes fijado a
            // mano que además tiene lecturas diarias detrás.
            'manualMonths' => $kpis
                ->filter(fn (PlantMonthlyKpi $k): bool => $k->energy_is_imported || $k->processed_tons_is_manual)
                ->keys()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, PlantMonthlyKpi>  $kpis
     * @return list<array{label: string, values: array<int, ?string>, total: ?string, strong: bool}>
     */
    private function buildRows($kpis): array
    {
        // Cada fila declara de dónde sale su número y cómo se totaliza el año. Los
        // promedios y los ratios NO se suman: el KWh/RFF del año es el total de kWh
        // partido por el total de fruta, no la media de doce ratios mensuales — que
        // daría más peso a un mes flojo que a uno de plena cosecha.
        $sum = fn (string $field): ?float => $this->sumOf($kpis, $field);

        $tons = $sum('processed_tons');
        $total = $sum('kwh_total');
        $turbine = $sum('kwh_turbine');

        return [
            $this->row('RFF/MES (t)', $kpis, 'processed_tons', 0, $tons),
            $this->row('KWh/RFF', $kpis, 'kwh_per_ton', 2,
                ($total !== null && $tons > 0) ? round($total / $tons, 2) : null, strong: true),
            $this->row('KWh TOTAL', $kpis, 'kwh_total', 0, $total, strong: true),
            $this->row('KWh RED PÚBLICA', $kpis, 'kwh_grid', 0, $sum('kwh_grid')),
            $this->row('KWh PLANTA ELÉCTRICA', $kpis, 'kwh_genset', 0, $sum('kwh_genset')),
            $this->row('KWh TURBINA', $kpis, 'kwh_turbine', 0, $turbine),
            $this->row('ENERGÍA LIMPIA (%)', $kpis, 'clean_energy_percentage', 2,
                ($turbine !== null && $total > 0) ? round($turbine / $total * 100, 2) : null),
        ];
    }

    /**
     * @param  Collection<int, PlantMonthlyKpi>  $kpis
     * @return array{label: string, values: array<int, ?string>, total: ?string, strong: bool}
     */
    private function row(string $label, $kpis, string $field, int $decimals, ?float $total, bool $strong = false): array
    {
        $values = [];

        for ($m = 1; $m <= 12; $m++) {
            $raw = $kpis->get($m)?->{$field};

            $values[$m] = $raw === null ? null : $this->format((float) $raw, $decimals);
        }

        return [
            'label' => $label,
            'values' => $values,
            'total' => $total === null ? null : $this->format($total, $decimals),
            'strong' => $strong,
        ];
    }

    /** @param Collection<int, PlantMonthlyKpi> $kpis */
    private function sumOf($kpis, string $field): ?float
    {
        $known = $kpis->pluck($field)->filter(fn ($v): bool => $v !== null);

        return $known->isEmpty() ? null : round((float) $known->sum(), 2);
    }

    private function format(float $value, int $decimals): string
    {
        return number_format($value, $decimals, ',', '.');
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

    // ── Corregir un mes ───────────────────────────────────────────────────────

    /**
     * Corrige a mano el total de un mes.
     *
     * Ofrece solo las cuatro cifras que se escriben. Las otras tres las calcula Postgres
     * y se mueven solas al guardar: ofrecerlas como campos permitiría que el total dejara
     * de cuadrar con sus partes, que es justo lo que esta planilla existe para impedir.
     */
    public function editMonthAction(): Action
    {
        return Action::make('editMonth')
            ->label('Corregir mes')
            ->icon('heroicon-o-pencil-square')
            ->modalHeading(fn (array $arguments): string => 'Corregir '.$this->monthName((int) ($arguments['month'] ?? 1)))
            ->modalDescription(fn (array $arguments): ?string => $this->editWarning((int) ($arguments['month'] ?? 1)))
            ->modalSubmitActionLabel('Guardar la corrección')
            ->fillForm(fn (array $arguments): array => $this->currentValues((int) ($arguments['month'] ?? 1)))
            ->schema([
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
                Text::make('KWh TOTAL, KWh/RFF y ENERGÍA LIMPIA no se teclean: el sistema los recalcula con estas cuatro cifras al guardar.'),
            ])
            ->action(function (array $arguments, array $data): void {
                $plant = $this->selectedPlant();

                if ($plant === null) {
                    return;
                }

                try {
                    app(MonthlyEnergyCorrectionService::class)
                        ->apply($plant, $this->selectedYear(), (int) $arguments['month'], $data);
                } catch (BusinessRuleException $e) {
                    Notification::make()->title($e->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()
                    ->title($this->monthName((int) $arguments['month']).' corregido')
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
            ->label('Recalcular desde las lecturas')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Recalcular '.$this->monthName((int) ($arguments['month'] ?? 1)))
            ->modalDescription('Se descarta la corrección manual y el mes vuelve a lo que suman las lecturas diarias de los contadores.')
            ->action(function (array $arguments): void {
                $plant = $this->selectedPlant();

                if ($plant === null) {
                    return;
                }

                app(MonthlyEnergyCorrectionService::class)
                    ->recalculateFromReadings($plant, $this->selectedYear(), (int) $arguments['month']);

                Notification::make()
                    ->title($this->monthName((int) $arguments['month']).' recalculado')
                    ->success()
                    ->send();
            })
            ->visible(fn (): bool => $this->canEditEnergy());
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
