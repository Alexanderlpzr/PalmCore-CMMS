<?php

namespace App\Filament\Filters;

use App\Domain\Analytics\Support\DashboardPeriod;
use App\Filament\Concerns\HasPeriodFilterForm;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * La barra «Periodo · Año · Mes» de las tablas de talento humano, a la vista encima de la
 * tabla como la de los tableros de mantenimiento ({@see HasPeriodFilterForm}): se
 * elige el corte de nómina (del 27 al 26), un mes, un año o un rango de fechas, y el año y
 * el mes aparecen según lo elegido.
 *
 * Pedida el 2026-11-08 al ver la de mantenimiento: el filtro anterior vivía dentro del botón
 * «Filtros» y no se veía.
 */
class PayrollPeriodFilter
{
    /**
     * @param  ?string  $column  la columna de fecha que filtra; sin ella solo elige la ventana
     *                           con que la página calcula (las que listan trabajadores)
     * @param  bool  $allowAll  ofrece «Todo», y lo trae puesto: para las bandejas, donde
     *                          esconder lo viejo hace creer que no hay pendientes
     */
    public static function make(?string $column = null, string $name = 'periodo', ?int $cutoffDay = null, bool $allowAll = false): Filter
    {
        $current = self::currentCutoffMonth($cutoffDay);

        return Filter::make($name)
            ->schema([
                Select::make('preset')
                    ->label('Periodo')
                    ->options(array_filter([
                        'todo' => $allowAll ? 'Todo' : null,
                        'corte' => $cutoffDay ? 'Corte de nómina' : null,
                        'mes' => 'Un mes',
                        'anio' => 'Año completo',
                        'rango' => 'Rango de fechas',
                    ]))
                    ->default($allowAll ? 'todo' : ($cutoffDay ? 'corte' : 'mes'))
                    ->selectablePlaceholder(false)
                    ->live(),

                Select::make('anio')
                    ->label('Año')
                    ->options(self::yearOptions($current))
                    ->default($current->year)
                    ->selectablePlaceholder(false)
                    ->live()
                    ->visible(fn (Get $get): bool => in_array($get('preset'), ['corte', 'mes', 'anio'], true)),

                Select::make('mes')
                    ->label(fn (Get $get): string => $get('preset') === 'corte' ? 'Mes del corte' : 'Mes')
                    ->options(DashboardPeriod::monthOptions())
                    ->default($current->month)
                    ->selectablePlaceholder(false)
                    ->live()
                    ->helperText(function (Get $get) use ($cutoffDay): ?string {
                        if ($get('preset') !== 'corte' || ! $cutoffDay) {
                            return null;
                        }

                        $window = self::window(['preset' => 'corte', 'anio' => $get('anio'), 'mes' => $get('mes')], $cutoffDay);

                        return $window[0] ? 'Del '.CarbonImmutable::parse($window[0])->format('d/m/Y').' al '.CarbonImmutable::parse($window[1])->format('d/m/Y') : null;
                    })
                    ->visible(fn (Get $get): bool => in_array($get('preset'), ['corte', 'mes'], true)),

                DatePicker::make('desde')
                    ->label('Desde')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(fn (Get $get) => $get('hasta') ?: null)
                    ->live()
                    ->visible(fn (Get $get): bool => $get('preset') === 'rango'),

                DatePicker::make('hasta')
                    ->label('Hasta')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->minDate(fn (Get $get) => $get('desde') ?: null)
                    ->live()
                    ->visible(fn (Get $get): bool => $get('preset') === 'rango'),
            ])
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
            ->indicateUsing(fn (array $data): array => array_filter([self::label($data, $cutoffDay)]));
    }

    /**
     * Las fechas de lo elegido, o null en los extremos que no se acotan («Todo», o un rango
     * a medias).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: ?string, 1: ?string}
     */
    public static function window(array $data, ?int $cutoffDay = null): array
    {
        $year = (int) ($data['anio'] ?? 0) ?: (int) now()->year;
        $month = (int) ($data['mes'] ?? 0) ?: (int) now()->month;
        $first = CarbonImmutable::create($year, $month, 1);

        $window = match ($data['preset'] ?? null) {
            'corte' => $cutoffDay ? PayrollRun::hoursWindowFor($first, $cutoffDay) : null,
            'mes' => [$first, $first->endOfMonth()->startOfDay()],
            'anio' => [CarbonImmutable::create($year, 1, 1), CarbonImmutable::create($year, 12, 31)],
            'rango' => [
                filled($data['desde'] ?? null) ? CarbonImmutable::parse($data['desde']) : null,
                filled($data['hasta'] ?? null) ? CarbonImmutable::parse($data['hasta']) : null,
            ],
            default => null,
        };

        return $window ? [$window[0]?->toDateString(), $window[1]?->toDateString()] : [null, null];
    }

    /** Lo elegido dicho en palabras, para el indicador de la tabla y el título del Excel. */
    public static function label(array $data, ?int $cutoffDay = null): ?string
    {
        [$desde, $hasta] = self::window($data, $cutoffDay);
        $year = (int) ($data['anio'] ?? 0) ?: (int) now()->year;
        $month = DashboardPeriod::monthOptions()[(int) ($data['mes'] ?? 0)] ?? null;
        $fecha = fn (?string $d): string => CarbonImmutable::parse((string) $d)->format('d/m/Y');

        return match (true) {
            ($data['preset'] ?? null) === 'corte' && $desde !== null => 'Corte de '.mb_strtolower((string) $month).' '.$year.' (del '.$fecha($desde).' al '.$fecha($hasta).')',
            ($data['preset'] ?? null) === 'mes' => ucfirst((string) $month).' '.$year,
            ($data['preset'] ?? null) === 'anio' => 'Año '.$year,
            $desde !== null && $hasta !== null => 'Del '.$fecha($desde).' al '.$fecha($hasta),
            $desde !== null => 'Desde el '.$fecha($desde),
            $hasta !== null => 'Hasta el '.$fecha($hasta),
            default => null,
        };
    }

    /**
     * El mes del corte en curso: con corte 26, desde el 27 de noviembre ya corre el de
     * diciembre. Sin corte, el mes de hoy.
     */
    private static function currentCutoffMonth(?int $cutoffDay): CarbonImmutable
    {
        $today = CarbonImmutable::today();

        return $cutoffDay ? PayrollRun::hoursWindowContaining($today, $cutoffDay)[1]->startOfMonth() : $today->startOfMonth();
    }

    /** @return array<int, string> del año del corte en curso hacia atrás, cinco años */
    private static function yearOptions(CarbonImmutable $current): array
    {
        return collect(range(max($current->year, (int) now()->year), (int) now()->year - 4))
            ->mapWithKeys(fn (int $year): array => [$year => (string) $year])
            ->all();
    }
}
