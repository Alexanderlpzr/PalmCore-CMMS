<?php

namespace App\Domain\Commercial\Services\Concerns;

use App\Domain\Commercial\Enums\Market;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Leer el libro de la Directora Administrativa: las hojas por nombre, las tablas por su
 * encabezado, y las cifras, fechas y códigos tal como los deja Excel (fórmulas ya
 * calculadas, «8845.0» por «8845», fechas como número de serie).
 */
trait ReadsCommercialWorkbook
{
    /**
     * Las hojas visibles del libro, por nombre normalizado, con sus celdas por letra.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function sheets(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];

            foreach ($sheet->getRowIterator() as $index => $row) {
                foreach ($row->getCells() as $column => $cell) {
                    $value = $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell->getValue();

                    if ($value !== null && $value !== '') {
                        $rows[$index][self::letter($column)] = $value;
                    }
                }
            }

            $sheets[self::normalize($sheet->getName())] = $rows;
        }

        $reader->close();

        return $sheets;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $sheets
     * @return array<int, array<string, mixed>>|null
     */
    protected function sheet(array $sheets, string $startsWith): ?array
    {
        foreach ($sheets as $name => $rows) {
            if (str_starts_with($name, $startsWith)) {
                return $rows;
            }
        }

        return null;
    }

    /**
     * Las filas debajo del encabezado cuya columna A dice $header, a partir del título
     * $after si la hoja tiene varias tablas.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function table(array $rows, string $header, ?string $after = null): array
    {
        $started = $after === null;
        $inTable = false;
        $table = [];

        foreach ($rows as $cells) {
            $first = is_string($cells['A'] ?? null) ? self::normalize($cells['A']) : '';

            // El título de la tabla, no una nota que la nombre («…y VENTA PALMISTE (venta comercial…»).
            if (! $started) {
                $started = str_starts_with($first, $after);

                continue;
            }

            if (! $inTable) {
                $inTable = $first === $header;

                continue;
            }

            if (str_starts_with($first, 'total') || str_starts_with($first, 'resumen') || str_starts_with($first, 'como usar') || str_starts_with($first, 'notas')) {
                break;
            }

            $table[] = $cells;
        }

        return $table;
    }

    protected static function letter(int $index): string
    {
        $letter = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $letter = chr(65 + (($index - 1) % 26)).$letter;
        }

        return $letter;
    }

    protected static function normalize(string $text): string
    {
        return Str::of($text)->ascii()->lower()->squish()->toString();
    }

    protected static function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if (is_numeric($value) && $value > 20_000 && $value < 80_000) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $value);
        }

        if (is_string($value) && preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', trim($value), $parts)) {
            return CarbonImmutable::createFromDate((int) $parts[3], (int) $parts[2], (int) $parts[1])->startOfDay();
        }

        return null;
    }

    protected static function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    protected static function positive(mixed $value): ?float
    {
        $number = self::number($value);

        return $number !== null && $number > 0 ? round($number, 2) : null;
    }

    protected static function nonZero(mixed $value): ?float
    {
        $number = self::number($value);

        return $number !== null && abs($number) >= 0.005 ? round($number, 2) : null;
    }

    /** Un número de documento: «8845», no «8845.0». */
    protected static function code(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        // Una fecha escrita donde iba un número: se guarda tal cual se ve.
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        $text = trim(trim((string) $value), ',;');

        return $text === '' ? null : preg_replace('/^(\d+)\.0+$/', '$1', $text);
    }

    protected static function plate(mixed $value): ?string
    {
        $code = self::code($value);

        return $code === null ? null : Str::upper(str_replace([' ', '-'], '', $code));
    }

    protected static function text(mixed $value): ?string
    {
        $text = is_scalar($value) ? Str::squish((string) $value) : '';

        return $text === '' ? null : $text;
    }

    protected static function market(mixed $value): ?Market
    {
        return match (true) {
            ! is_string($value) => null,
            str_starts_with(self::normalize($value), 'export') => Market::Exportacion,
            str_starts_with(self::normalize($value), 'nacional') => Market::Nacional,
            default => null,
        };
    }
}
