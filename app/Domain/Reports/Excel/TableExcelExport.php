<?php

namespace App\Domain\Reports\Excel;

use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Indicator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * «Descargar Excel» para cualquier tabla: lo que se ve en pantalla —las columnas visibles,
 * con los filtros, la búsqueda y el orden puestos— en un libro con el encabezado de
 * fronda.app, y debajo del título los filtros aplicados, para que el archivo diga de qué
 * período es.
 *
 * Pedido el 2026-11-08 para todas las tablas de la aplicación: elegir un mes, un año, un
 * corte o un rango y bajarlo. Las tablas que ya tienen su propio Excel con formato de la
 * empresa (horas extras, alimentación, desprendibles) lo conservan.
 */
final class TableExcelExport
{
    /** Más filas que esto no caben en una descarga sin cola: se avisa y se corta. */
    public const MAX_ROWS = 20000;

    /**
     * @param  string  $title  el título del libro y del nombre del archivo
     */
    public static function action(string $title): Action
    {
        return Action::make('excelTabla')
            ->label('Descargar Excel')
            ->icon(Heroicon::OutlinedTableCells)
            ->color('gray')
            ->action(fn (HasTable $livewire): ?StreamedResponse => (new self)->download($livewire, $title));
    }

    public function download(HasTable $livewire, string $title): ?StreamedResponse
    {
        $libro = $this->build($livewire, $title);

        return $libro?->download(Str::upper(Str::slug($title, '-')).'-'.now()->format('Ymd-Hi').'.xlsx');
    }

    /** El libro con lo que muestra la tabla, o null si la tabla no tiene consulta. */
    public function build(HasTable $livewire, string $title): ?FrondaWorkbook
    {
        $query = $livewire->getFilteredSortedTableQuery();

        if (! $query) {
            return null;
        }

        $table = $livewire->getTable();
        $columns = array_values(array_filter(
            $table->getVisibleColumns(),
            fn (Column $column): bool => $column instanceof TextColumn || $column instanceof IconColumn,
        ));

        $records = $query->limit(self::MAX_ROWS + 1)->get();
        $truncated = $records->count() > self::MAX_ROWS;
        $records = $records->take(self::MAX_ROWS);

        $headers = array_map(fn (Column $column): string => $this->text($column->getLabel()), $columns);
        $rows = $records->map(fn (Model $record): array => array_map(fn (Column $column) => $this->cell($column, $record), $columns));

        $filters = collect($table->getFilters())
            ->flatMap(fn ($filter): array => $filter->getIndicators())
            ->map(fn (Indicator $indicator): string => $this->text($indicator->getLabel()))
            ->filter()
            ->implode(' · ');

        $detail = trim(($filters !== '' ? $filters : 'Sin filtros').' · '.$records->count().' '.($records->count() === 1 ? 'fila' : 'filas').($truncated ? ' (se cortó en '.number_format(self::MAX_ROWS, 0, ',', '.').': filtre más para bajar el resto)' : ''));

        return FrondaWorkbook::create()
            ->sheet(Str::limit($title, 31, ''))
            ->widths(array_combine(range(1, max(1, count($headers))), array_map(fn (string $h): int => max(12, min(40, mb_strlen($h) + 4)), $headers ?: [''])))
            ->banner($title, $detail, Filament::getTenant()?->name, max(3, count($headers)))
            ->table($headers, $rows)
            ->footer();
    }

    /** El valor de la celda como se lee en pantalla: el formato de la columna, sin HTML. */
    private function cell(Column $column, Model $record): mixed
    {
        $column->record($record);
        $state = $column->getState();

        if ($column instanceof IconColumn) {
            return is_bool($state) ? ($state ? 'Sí' : 'No') : $this->text($state);
        }

        if (is_iterable($state) && ! is_string($state)) {
            return collect($state)->map(fn ($item): string => $this->text($column->formatState($item)))->filter()->implode(', ');
        }

        if ($state === null || $state === '') {
            return '';
        }

        // Las cifras sin formato de pantalla quedan como número, para poder sumarlas en Excel.
        if (is_int($state) || is_float($state)) {
            $formatted = $this->text($column->formatState($state));

            return is_numeric(str_replace(['.', ','], ['', '.'], $formatted)) && ! str_contains($formatted, '$') ? $state : $formatted;
        }

        return $this->text($column->formatState($state));
    }

    private function text(mixed $value): string
    {
        return match (true) {
            $value instanceof Htmlable => trim(html_entity_decode(strip_tags($value->toHtml()), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            $value instanceof UnitEnum => method_exists($value, 'label') ? (string) $value->label() : (string) ($value->value ?? $value->name),
            $value instanceof CarbonInterface => $value->format('d/m/Y'),
            is_bool($value) => $value ? 'Sí' : 'No',
            is_scalar($value) => trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            default => '',
        };
    }
}
