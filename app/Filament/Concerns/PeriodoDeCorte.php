<?php

namespace App\Filament\Concerns;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Filament\Filters\DateRangeFilter;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Tables\Filters\Filter;

/**
 * El filtro «Período» de las tablas de talento humano: el corte en curso puesto (del 27 al
 * 26), y a mano cualquier otro corte, un mes, un año o un rango libre —el mismo
 * {@see DateRangeFilter} del resto de la aplicación, con los cortes de nómina—.
 *
 * Antes cada página ofrecía solo los seis últimos cortes, con su propio desplegable.
 */
trait PeriodoDeCorte
{
    /** El nombre del filtro en el estado de la tabla. */
    protected static string $periodFilterName = 'periodo';

    /**
     * @param  ?string  $column  la columna de fecha que filtra; sin ella solo elige la ventana
     *                           con que la página calcula (las que listan trabajadores)
     */
    protected function periodFilter(?string $column = null): Filter
    {
        return DateRangeFilter::make(
            column: $column,
            label: 'Fecha',
            name: static::$periodFilterName,
            cutoffDay: $this->cutoffDay(),
            // Sin día de corte la ventana de horas es el mes calendario.
            defaultPeriod: ($this->cutoffDay() > 0 ? 'corte:' : 'mes:').$this->currentCutoff()[1]->format('Y-m'),
        );
    }

    /**
     * La ventana elegida en el filtro. Un extremo vacío se completa con el corte en curso:
     * estas páginas calculan horas y comidas de un período, y sin período no hay cuenta.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function window(): array
    {
        [$from, $to] = $this->chosenWindow();
        [$currentFrom, $currentTo] = $this->currentCutoff();

        return [
            $from ? CarbonImmutable::parse($from) : $currentFrom,
            $to ? CarbonImmutable::parse($to) : $currentTo,
        ];
    }

    /**
     * Lo que dice el filtro tal cual, con sus extremos vacíos si los hay.
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function chosenWindow(): array
    {
        return DateRangeFilter::window((array) ($this->getTableFilterState(static::$periodFilterName) ?? []), $this->cutoffDay());
    }

    protected function cutoffDay(): int
    {
        return (int) app(PayrollParameterService::class)->valueOrDefault(
            PayrollParameter::HoursCutoffDay,
            CarbonImmutable::today(),
            Filament::getTenant()?->getKey() ?? '',
        );
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} el corte que corre hoy */
    protected function currentCutoff(): array
    {
        return PayrollRun::hoursWindowContaining(CarbonImmutable::today(), $this->cutoffDay());
    }
}
