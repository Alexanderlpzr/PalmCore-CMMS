<?php

namespace App\Domain\HumanResources\Services;

use Carbon\CarbonImmutable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Lee el libro de horas extras de la extractora (TH-NOM-P-001): una hoja por trabajador
 * con el formato TH-FOR-002.
 *
 * Las hojas se copiaron a mano durante años y no hay dos iguales: diecinueve diseños de
 * columnas, «RDN» por «RND», «BONO RN» dos veces en la misma hoja, fechas escritas como
 * texto con el día de la semana corrido desde el 16 de octubre. Por eso las columnas se
 * buscan por su encabezado y no por su letra, y la fecha se toma del número y el mes del
 * texto, nunca del nombre del día.
 *
 * Solo lee las hojas visibles: las ocultas son de trabajadores y periodos pasados.
 *
 * Lo que no puede leer bien no lo adivina: lo anota en los avisos de `readWithIssues()`
 * —una hoja sin títulos, una columna que falta, una hora o una fecha que no entiende—
 * para que talento humano lo corrija en el libro antes de importarlo.
 */
final class OvertimeWorkbookReader
{
    private const SUMMARY_SHEETS = ['BONIFICACIONES', 'TOTAL HORAS'];

    /** Encabezado (sin tildes, en mayúsculas) → qué es y a qué bolsa va. */
    private const HEADERS = [
        'HORA INICIO' => ['time', 'start'],
        'FIN HORARIO LABORAL' => ['time', 'scheduledEnd'],
        'HORA SALIDA' => ['time', 'exit'],
        'HORAS EXTRAS DIURNAS' => ['legal', 'overtime_day'],
        'HORAS EXTRAS NOCTURNAS' => ['legal', 'overtime_night'],
        'HORAS EXTRAS DOMINICAL DIURNA' => ['legal', 'overtime_sunday_day'],
        'HORAS EXTRAS DOMINICAL NOCTURNA' => ['legal', 'overtime_sunday_night'],
        'HORA DOMINICAL' => ['legal', 'sunday_surcharge'],
        'RECARGO DIURNO DOMI' => ['legal', 'sunday_surcharge'],
        'RECARGO DIURNO DO' => ['legal', 'sunday_surcharge'],
        'RECARGO DIURNO DOMINICAL' => ['legal', 'sunday_surcharge'],
        'RECARGO NOCTURNO' => ['legal', 'night_surcharge'],
        'RECARGO NOCTURNO DOMINICAL' => ['legal', 'night_sunday_surcharge'],
        'BONO HED' => ['bonus', 'overtime_day'],
        'BONO HEN' => ['bonus', 'overtime_night'],
        'BONO HEDD' => ['bonus', 'overtime_sunday_day'],
        'BONO HEDN' => ['bonus', 'overtime_sunday_night'],
        'BONO RN' => ['bonus', 'night_surcharge'],
        'BONO RND' => ['bonus', 'night_sunday_surcharge'],
        'BONO RDN' => ['bonus', 'night_sunday_surcharge'],
        'JUSTIFICACION' => ['text', 'notes'],
    ];

    private const MONTHS = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
        'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
        'noviembre' => 11, 'diciembre' => 12,
    ];

    /** Las bolsas, en el orden del formato. */
    public const BUCKETS = [
        'overtime_day', 'overtime_night', 'overtime_sunday_day', 'overtime_sunday_night',
        'sunday_surcharge', 'night_surcharge', 'night_sunday_surcharge',
    ];

    /** Las columnas sin las que un día no se puede cargar, o se carga con la jornada. */
    private const TIME_COLUMNS = [
        'start' => 'HORA INICIO',
        'exit' => 'HORA SALIDA',
        'scheduledEnd' => 'FIN HORARIO LABORAL',
    ];

    /**
     * @return list<array{sheet: string, name: string, document: string, supervisor: ?string, salary: ?float, bonusTotal: ?float, days: list<array{row: int, date: CarbonImmutable, start: ?string, scheduledEnd: ?string, exit: ?string, legal: array<string, float>, bonus: array<string, float>, notes: string}>}>
     */
    public function read(string $path): array
    {
        return $this->readWithIssues($path)['sheets'];
    }

    /**
     * Las hojas y, aparte, lo que no se pudo leer bien de ellas, como frases para
     * talento humano: «Hoja ERNESTO, fila 22: no se entiende la hora «6:0p»…».
     *
     * @return array{sheets: list<array{sheet: string, name: string, document: string, supervisor: ?string, salary: ?float, bonusTotal: ?float, days: list<array{row: int, date: CarbonImmutable, start: ?string, scheduledEnd: ?string, exit: ?string, legal: array<string, float>, bonus: array<string, float>, notes: string}>}>, issues: list<string>}
     */
    public function readWithIssues(string $path): array
    {
        // Con las filas vacías, cada fila conserva su número de Excel: los avisos dicen
        // «fila 22» y es la 22 que talento humano ve en el libro.
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);
        $reader->open($path);
        $sheets = [];
        $issues = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            if (! $sheet->isVisible() || in_array(self::normalize($sheet->getName()), self::SUMMARY_SHEETS, true)) {
                continue;
            }

            $rows = [];

            foreach ($sheet->getRowIterator() as $index => $row) {
                $cells = [];

                foreach ($row->getCells() as $column => $cell) {
                    $cells[$column + 1] = $this->value($cell);
                }

                $rows[$index] = $cells;
            }

            if ($parsed = $this->parseSheet($sheet->getName(), $rows, $issues)) {
                $sheets[] = $parsed;
            } else {
                $issues[] = "Hoja {$sheet->getName()}: no se leyó, le falta la fila de títulos con «FECHA» en la columna A.";
            }
        }

        $reader->close();

        return ['sheets' => $sheets, 'issues' => $issues];
    }

    /**
     * La hora escrita a mano, en 24 horas: «6:00pm» son las 18:00, «7:00 am» las 07:00,
     * «5:00pm}» las 17:00. Acepta también la hora como fracción del día o como fecha.
     */
    public static function parseTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        if (is_float($value) || is_int($value)) {
            $minutes = (int) round(fmod((float) $value, 1) * 1440);

            return $value > 0 ? sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60) : null;
        }

        $text = mb_strtolower(trim((string) $value));

        if (preg_match('/(\d{1,2})(?::(\d{2}))?\s*([ap])\.?\s*m/u', $text, $m)) {
            $hour = (int) $m[1] % 12 + ($m[3] === 'p' ? 12 : 0);

            return sprintf('%02d:%02d', $hour, (int) ($m[2] ?? 0));
        }

        if (preg_match('/^(\d{1,2}):(\d{2})/', $text, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return null;
    }

    /**
     * La fecha de la fila: una fecha de verdad, o el número y el mes del texto
     * («domingo,19  de octubre de 2026»). Sin año en el texto, el de la fila anterior.
     */
    public static function parseDate(mixed $value, ?CarbonImmutable $previous): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        $text = mb_strtolower(trim((string) $value));

        if (! preg_match('/(\d{1,2})\s*de\s*([a-záéíóú]+)/u', $text, $m) || ! isset(self::MONTHS[$m[2]])) {
            return null;
        }

        $year = preg_match('/(\d{4})/', $text, $y) ? (int) $y[1] : ($previous?->year ?? (int) date('Y'));

        return CarbonImmutable::create($year, self::MONTHS[$m[2]], (int) $m[1]);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  list<string>  $issues
     */
    private function parseSheet(string $name, array $rows, array &$issues): ?array
    {
        $headerRow = collect($rows)->search(fn (array $cells): bool => self::normalize((string) ($cells[1] ?? '')) === 'FECHA');

        if ($headerRow === false) {
            return null;
        }

        $columns = [];

        foreach ($rows[$headerRow] as $column => $label) {
            $key = self::normalize((string) $label);

            if (isset(self::HEADERS[$key])) {
                $columns[$column] = self::HEADERS[$key];
            }
        }

        $timeFields = array_column(array_filter($columns, fn (array $c): bool => $c[0] === 'time'), 1);

        foreach (self::TIME_COLUMNS as $field => $label) {
            if (! in_array($field, $timeFields, true)) {
                $issues[] = "Hoja {$name}: no tiene la columna «{$label}»".($field === 'scheduledEnd'
                    ? '; sus días se cuentan con la jornada de cada día.'
                    : '; sus días no se pueden cargar.');
            }
        }

        $seenDates = [];

        $days = [];
        $previous = null;

        for ($r = $headerRow + 1; $r <= $headerRow + 45 && isset($rows[$r]); $r++) {
            $cells = $rows[$r];

            if (str_starts_with(self::normalize((string) ($cells[1] ?? '')), 'TOTAL')) {
                break;
            }

            // Solo las filas que dicen su fecha. La de los totales —una SUM por columna— no
            // la dice, y tampoco la fila suelta que algunas hojas arrastran al final.
            $date = self::parseDate($cells[1] ?? null, $previous);
            $day = ['date' => $date, 'start' => null, 'scheduledEnd' => null, 'exit' => null, 'notes' => '',
                'legal' => array_fill_keys(self::BUCKETS, 0.0), 'bonus' => array_fill_keys(self::BUCKETS, 0.0)];

            $rowIssues = [];

            foreach ($columns as $column => [$kind, $field]) {
                $value = $cells[$column] ?? null;

                match ($kind) {
                    'time' => $day[$field] = self::parseTime($value),
                    'text' => $day['notes'] = trim((string) ($value ?? '')),
                    default => $day[$kind][$field] += is_numeric($value) ? (float) $value : 0.0,
                };

                if ($kind === 'time' && ($problem = self::timeProblem($value, $day[$field]))) {
                    $rowIssues[] = sprintf($problem, self::TIME_COLUMNS[$field]);
                }
            }

            if ($date === null) {
                // La fila de totales no tiene horas de entrada ni de salida: esa no avisa.
                if ($day['start'] || $day['exit']) {
                    $issues[] = "Hoja {$name}, fila {$r}: no se entiende la fecha «".trim((string) ($cells[1] ?? '')).'»; la fila no se leyó.';
                }

                continue;
            }

            foreach ($rowIssues as $issue) {
                $issues[] = "Hoja {$name}, fila {$r}: {$issue}";
            }

            if ((bool) $day['start'] !== (bool) $day['exit']) {
                $issues[] = "Hoja {$name}, fila {$r}: el {$date->format('d/m/Y')} tiene ".($day['start'] ? 'hora de inicio pero no de salida' : 'hora de salida pero no de inicio').'; ese día no se carga.';
            }

            if ($day['start'] && $day['exit']) {
                if (isset($seenDates[$date->toDateString()])) {
                    $issues[] = "Hoja {$name}, fila {$r}: el {$date->format('d/m/Y')} ya está en la fila {$seenDates[$date->toDateString()]}.";
                }

                $seenDates[$date->toDateString()] ??= $r;
            }

            $day['row'] = $r;
            $previous = $date;
            $hasHours = array_sum($day['legal']) + array_sum($day['bonus']) > 0;

            if ($hasHours || ($day['start'] && $day['exit'])) {
                $days[] = $day;
            }
        }

        return [
            'sheet' => $name,
            'name' => $this->labelValue($rows, 'NOMBRE') ?? $name,
            'document' => preg_replace('/\D/', '', (string) $this->labelValue($rows, 'IDENTIFICACI')),
            'supervisor' => ($jefe = $this->labelValue($rows, 'JEFE INMEDIATO')) !== null ? trim((string) $jefe) : null,
            'salary' => is_numeric($salary = $this->labelValue($rows, 'SALARIO', exact: true)) ? (float) $salary : null,
            'bonusTotal' => is_numeric($bonus = $this->labelValue($rows, 'TOTAL BONO', exact: true)) ? (float) $bonus : null,
            'days' => $days,
        ];
    }

    /**
     * Qué tiene de malo una hora del libro, con «%s» donde va la columna; null si nada.
     * La que no se entiende no entra; la que no dice a. m. ni p. m. y podría ser las dos
     * se toma en 24 horas, y talento humano debe saberlo.
     */
    private static function timeProblem(mixed $value, ?string $parsed): ?string
    {
        // Las horas con formato de hora de Excel no tienen nada que adivinar.
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $text = trim($value);

        if ($parsed === null) {
            return "no se entiende la hora «{$text}» de «%s».";
        }

        $hour = (int) substr($parsed, 0, 2);

        if (! preg_match('/[ap]\.?\s*m/iu', $text) && $hour >= 1 && $hour <= 12) {
            return "la hora «{$text}» de «%s» no dice a. m. ni p. m.; se tomó como {$parsed}.";
        }

        return null;
    }

    /**
     * El valor que va al lado de una etiqueta: «NOMBRE TRABAJADOR» → el nombre,
     * «SALARIO» → el salario, «TOTAL BONO» → el total.
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function labelValue(array $rows, string $label, bool $exact = false): mixed
    {
        foreach ($rows as $cells) {
            foreach ($cells as $column => $value) {
                $text = self::normalize((string) $value);
                $matches = $exact ? $text === $label : str_starts_with($text, $label);

                if (! $matches) {
                    continue;
                }

                foreach ($cells as $next => $candidate) {
                    if ($next > $column && $candidate !== null && trim((string) $candidate) !== '') {
                        return is_string($candidate) ? trim($candidate) : $candidate;
                    }
                }
            }
        }

        return null;
    }

    private function value(Cell $cell): mixed
    {
        return $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell->getValue();
    }

    /** Mayúsculas, sin tildes y con un solo espacio: así se comparan los encabezados. */
    private static function normalize(string $text): string
    {
        $text = strtr(mb_strtoupper(trim($text)), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }
}
