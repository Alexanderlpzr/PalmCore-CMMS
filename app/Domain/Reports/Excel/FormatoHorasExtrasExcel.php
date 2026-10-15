<?php

namespace App\Domain\Reports\Excel;

use App\Domain\Reports\Services\FormatoHorasExtrasPdfService as Formato;
use App\Models\Employee;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * El formato de horas extras (TH-FOR-002) en Excel: un libro con una hoja por trabajador,
 * como el TH-NOM-P-001 que talento humano llenaba a mano, pero con las horas de la puerta.
 *
 * Cada hoja sale de los mismos datos que el PDF, así que el papel y el Excel no pueden
 * decir cosas distintas. Lleva el control documental del formato (código, versión, fecha),
 * los datos del trabajador, una fila por día con domingos y festivos en rojo y, al lado,
 * las columnas de bonificación que tengan horas, y la calculadora a la derecha. La hoja
 * sigue la plantilla que dio talento humano (la de Adrián Suárez en el libro de octubre):
 * el logo de la empresa en A1:B3, la justificación en tres columnas y los totales por clase
 * al pie. Sin la columna de recargo diurno dominical, por decisión de la planta.
 */
class FormatoHorasExtrasExcel
{
    /** Columnas fijas antes de las bolsas: fecha, inicio, fin del horario, salida. */
    private const LEAD_COLUMNS = 4;

    public function __construct(private readonly Formato $formato) {}

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  bool  $showValues  la calculadora con salario y valores
     */
    public function download(Collection $employees, CarbonInterface $from, CarbonInterface $to, bool $showValues): StreamedResponse
    {
        $path = $this->write($employees, $from, $to, $showValues);

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
            @unlink($path);
        }, $this->filename($employees, $to));
    }

    public function filename(Collection $employees, CarbonInterface $to): string
    {
        $who = $employees->count() === 1 ? $employees->first()->document_number : 'personal';

        return sprintf('formato-horas-extras-%s-%s.xlsx', $who, CarbonImmutable::instance($to)->format('Y-m'));
    }

    /**
     * Escribe el libro en un archivo temporal y devuelve su ruta.
     *
     * @param  Collection<int, Employee>  $employees
     */
    public function write(Collection $employees, CarbonInterface $from, CarbonInterface $to, bool $showValues): string
    {
        $path = tempnam(sys_get_temp_dir(), 'formato').'.xlsx';
        $options = new Options;
        $writer = new Writer($options);
        $writer->openToFile($path);
        $usedNames = [];
        $tenant = $employees->isNotEmpty() ? Tenant::withoutGlobalScopes()->find($employees->first()->tenant_id) : null;
        // El logo de la empresa va arriba a la izquierda, en A1:B3, como en la hoja del libro.
        $logo = XlsxLogo::forTenant($tenant);

        foreach ($employees->values() as $index => $employee) {
            $sheet = $index === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($this->sheetName($employee, $usedNames));

            $data = $this->formato->data($employee, $from, $to);
            $layout = $this->layout($data);

            $sheet->setColumnWidth(34, 1);
            $sheet->setColumnWidthForRange(13, 2, self::LEAD_COLUMNS);
            $sheet->setColumnWidthForRange(14, self::LEAD_COLUMNS + 1, $layout['justification'] - 1);
            $sheet->setColumnWidthForRange(12, $layout['justification'], $layout['last']);

            if ($showValues) {
                $calc = $layout['last'] + 2;
                $sheet->setColumnWidth(3, $layout['last'] + 1);
                $sheet->setColumnWidth(34, $calc);
                $sheet->setColumnWidthForRange(13, $calc + 1, $calc + 6);
            }

            foreach ($this->rows($data, $tenant, $showValues, $layout, $logo !== null) as $n => [$cells, $merges]) {
                $row = new Row($cells);

                // Las tres filas del encabezado, altas para que quepa el logo.
                if ($n < 3) {
                    $row->setHeight(26);
                }

                $writer->addRow($row);

                foreach ($merges as [$fromColumn, $toColumn]) {
                    $row = $sheet->getWrittenRowCount();
                    $options->mergeCells($fromColumn - 1, $row, $toColumn - 1, $row, $index);
                }
            }

            // El encabezado de la plantilla: el logo en A1:B3 y el título fundido en alto.
            $options->mergeCells(0, 1, 1, 3, $index);
            $options->mergeCells(2, 1, $layout['last'] - 2, 3, $index);
        }

        if ($employees->isEmpty()) {
            $writer->addRow(Row::fromValues(['No hay trabajadores con horas en el período.']));
        }

        $writer->close();

        if ($logo) {
            XlsxLogo::stamp($path, $logo, heightPx: 84);
        }

        return $path;
    }

    /**
     * Dónde va cada cosa en la hoja, como en la plantilla (hoja de Adrián Suárez del libro
     * de octubre): fecha y horas, las bolsas, la justificación en tres columnas, los bonos
     * con horas y, a la derecha, la calculadora.
     *
     * @param  array<string, mixed>  $data
     * @return array{buckets: list<string>, justification: int, bonusStart: int, last: int}
     */
    private function layout(array $data): array
    {
        $buckets = array_keys($data['columns']);
        $justification = self::LEAD_COLUMNS + count($buckets) + 1;
        $bonusStart = $justification + 3;

        return [
            'buckets' => $buckets,
            'justification' => $justification,
            'bonusStart' => $bonusStart,
            'last' => max($bonusStart - 1 + count($data['bonusColumns']), $justification + 2),
        ];
    }

    /**
     * Las filas de una hoja, cada una con las celdas que se funden.
     *
     * @param  array<string, mixed>  $data
     * @param  array{buckets: list<string>, justification: int, bonusStart: int, last: int}  $layout
     * @return list<array{0: list<Cell>, 1: list<array{0: int, 1: int}>}>
     */
    private function rows(array $data, ?Tenant $tenant, bool $showValues, array $layout, bool $withLogo = false): array
    {
        $employee = $data['employee'];
        $lastColumn = $layout['last'];
        $justification = $layout['justification'];
        $bonusStart = $layout['bonusStart'];
        $visible = $layout['buckets'];
        $justMerge = [$justification, $justification + 2];
        $rows = [];
        $line = fn (array $values, array $merges = [], ?callable $style = null): array => [
            array_map(fn ($value, int $i): Cell => Cell::fromValue($value, ($style ?? fn () => $this->style())($i + 1)), $values, array_keys($values)),
            $merges,
        ];
        $pad = fn (array $values): array => array_pad($values, $lastColumn, '');

        // ── Encabezado con el control documental ─────────────────────────────
        $title = fn (int $column): Style => match (true) {
            $column === $lastColumn => $this->style(bold: true, fill: FrondaWorkbook::MIST, color: FrondaWorkbook::PETROL, align: CellAlignment::LEFT),
            $column <= 2 && $withLogo => $this->style(),
            $column <= 2 => $this->style(bold: true, fill: FrondaWorkbook::PETROL, color: 'FFFFFF', size: 9),
            default => $this->style(bold: true, fill: FrondaWorkbook::GREEN, color: 'FFFFFF', size: 13),
        };
        $rows[] = $line($pad([$withLogo ? '' : mb_strtoupper($tenant?->name ?? ''), '', 'FORMATO DE HORAS EXTRAS', ...array_fill(0, $lastColumn - 4, ''), 'CÓDIGO: '.Formato::FORM_CODE]), [], $title);
        $rows[] = $line($pad([...array_fill(0, $lastColumn - 1, ''), 'VERSIÓN: '.Formato::FORM_VERSION]), [], $title);
        $rows[] = $line($pad([...array_fill(0, $lastColumn - 1, ''), 'FECHA: '.Formato::FORM_DATE]), [], $title);
        $rows[] = [[], []];

        // ── El trabajador ────────────────────────────────────────────────────
        $who = fn (int $column): Style => in_array($column, [1, $lastColumn - 1], true) ? $this->style(bold: true, fill: FrondaWorkbook::MIST, color: FrondaWorkbook::PETROL, align: CellAlignment::LEFT) : $this->style();
        $rows[] = $line($pad(['NOMBRE TRABAJADOR', mb_strtoupper($employee->fullName()), ...array_fill(0, $lastColumn - 4, ''), 'IDENTIFICACIÓN', $employee->document_number]), [[2, $lastColumn - 2]], $who);

        foreach ([
            'CARGO' => mb_strtoupper($employee->position ?? ''),
            'JEFE INMEDIATO' => mb_strtoupper($data['supervisor'] ?? ''),
            'PERÍODO' => 'Horas del '.$data['from']->format('d/m/Y').' al '.$data['to']->format('d/m/Y').' — nómina de '.$data['periodStart']->locale('es')->translatedFormat('F \d\e Y'),
        ] as $label => $value) {
            $rows[] = $line($pad([$label, $value]), [[2, $lastColumn]], fn (int $c): Style => $c === 1 ? $this->style(bold: true, fill: FrondaWorkbook::MIST, color: FrondaWorkbook::PETROL, align: CellAlignment::LEFT) : $this->style());
        }

        $rows[] = [[], []];

        // ── La cuadrícula ────────────────────────────────────────────────────
        $header = [
            'FECHA', 'HORA INICIO', 'FIN HORARIO LABORAL', 'HORA SALIDA',
            ...array_map(fn (string $bucket): string => Formato::COLUMN_TITLES[$bucket], $visible),
            'JUSTIFICACIÓN', '', '',
            ...array_map(fn (string $bucket): string => 'BONO '.Formato::COLUMNS[$bucket], $data['bonusColumns']),
        ];
        $gridHeader = count($rows);
        $rows[] = $line($pad($header), [$justMerge], fn (int $c): Style => $this->style(bold: true, fill: $c >= $bonusStart ? FrondaWorkbook::PETROL : FrondaWorkbook::GREEN, color: 'FFFFFF', size: 8));

        // En 12 horas, igual que el Excel de horas confirmadas: «06:00 pm».
        $hour = fn ($moment): string => $moment ? $moment->format('h:i a') : '';
        $hours = fn (float $value): float|string => $value > 0 ? round($value, 2) : '';

        foreach ($data['rows'] as $day) {
            $values = $pad([
                $day['date']->locale('es')->translatedFormat('l, j \d\e F \d\e Y'),
                $hour($day['entry']), $hour($day['scheduledEnd']), $hour($day['exit']),
                ...array_map(fn (string $bucket) => $hours($day['legal'][$bucket]), $visible),
                $day['notes'], '', '',
                ...array_map(fn (string $bucket) => $hours($day['bonus'][$bucket]), $data['bonusColumns']),
            ]);

            $rows[] = $line($values, [$justMerge], fn (int $c): Style => match (true) {
                $c === 1 => $this->style(color: $day['surcharged'] ? 'D00000' : null),
                $c === $justification => $this->style(align: CellAlignment::LEFT),
                $c >= $bonusStart => $this->style(fill: FrondaWorkbook::MIST),
                default => $this->style(),
            });
        }

        $rows[] = $line($pad([
            'TOTAL HORAS', '', '', '',
            ...array_map(fn (string $bucket) => $hours($data['legalTotals'][$bucket]), $visible),
            '', '', '',
            ...array_map(fn (string $bucket) => $hours($data['bonusTotals'][$bucket]), $data['bonusColumns']),
        ]), [[1, 4], $justMerge], fn (int $c): Style => $this->style(bold: true, fill: 'D5EBDD'));

        // ── Los totales por clase, como al pie de la plantilla ───────────────
        $rows[] = [[], []];
        $total = fn (string $bucket): float => round((float) ($data['legalTotals'][$bucket] ?? 0) + (float) ($data['bonusTotals'][$bucket] ?? 0), 2);
        $labelStyle = fn (int $c): Style => in_array($c, [1, 5], true)
            ? $this->style(bold: true, fill: FrondaWorkbook::MIST, color: FrondaWorkbook::PETROL, align: CellAlignment::LEFT)
            : $this->style(bold: true);

        foreach ([
            ['Total horas extras diurnas', 'overtime_day', 'Total horas extras nocturnas', 'overtime_night'],
            ['Total horas extras dominicales diurnas', 'overtime_sunday_day', 'Total horas extras dominicales nocturnas', 'overtime_sunday_night'],
            ['Recargo nocturno', 'night_surcharge', 'Recargo nocturno dominical', 'night_sunday_surcharge'],
        ] as [$leftLabel, $leftBucket, $rightLabel, $rightBucket]) {
            $rows[] = $line(
                [$leftLabel, $total($leftBucket), '', '', $rightLabel, ...array_fill(0, max(0, $justification - 6), ''), $total($rightBucket)],
                [[2, 4], [5, $justification - 1]],
                $labelStyle,
            );
        }

        // ── La calculadora, a la derecha de los días, como en la plantilla ───
        if ($showValues) {
            $money = fn (float $value): float => round($value, 0);
            $calc = [];
            $calc[] = [
                ['SALARIO (COP)', $money($data['salary']), 'VALOR HORA (COP)', $money($data['hourValue']), 'Salario ÷ '.number_format($data['divisor'], 0, ',', '.').' horas'],
                fn (int $c): Style => match ($c) {
                    1, 3 => $this->style(bold: true, fill: FrondaWorkbook::MIST, color: FrondaWorkbook::PETROL),
                    2, 4 => $this->style(bold: true, format: FrondaWorkbook::MONEY),
                    default => $this->style(size: 8),
                },
            ];
            $calc[] = [
                ['Concepto', 'Factor', 'Valor hora (COP)', 'Horas', 'Valor horas extras y recargos (COP)', 'Horas bono', 'Bonificación (COP)'],
                fn (): Style => $this->style(bold: true, fill: FrondaWorkbook::GREEN, color: 'FFFFFF', size: 8),
            ];

            foreach ($data['calculator'] as $row) {
                $calc[] = [
                    [
                        $row['label'].' — '.$row['concept'], $row['factor'], $money($row['rate']),
                        round($row['legalHours'], 2), $money($row['legalAmount']),
                        round($row['bonusHours'], 2), $money($row['bonusAmount']),
                    ],
                    fn (int $c): Style => match ($c) {
                        1 => $this->style(align: CellAlignment::LEFT),
                        3, 5, 7 => $this->style(format: FrondaWorkbook::MONEY),
                        default => $this->style(),
                    },
                ];
            }

            $calc[] = [
                [
                    'TOTAL', '', '',
                    round(array_sum(array_column($data['calculator'], 'legalHours')), 2), $money($data['legalAmount']),
                    round(array_sum(array_column($data['calculator'], 'bonusHours')), 2), $money($data['bonusAmount']),
                ],
                fn (int $c): Style => $this->style(bold: true, fill: 'D5EBDD', format: in_array($c, [5, 7], true) ? FrondaWorkbook::MONEY : null),
            ];

            // Cada línea de la calculadora se pega a la derecha de una fila de la cuadrícula,
            // desde la del encabezado, con una columna de aire en medio.
            foreach ($calc as $i => [$values, $style]) {
                $target = $gridHeader + $i;

                if (! isset($rows[$target])) {
                    break;
                }

                [$cells, $merges] = $rows[$target];
                $cells = array_pad($cells, $lastColumn + 1, Cell::fromValue(''));

                foreach (array_values($values) as $k => $value) {
                    $cells[] = Cell::fromValue($value, $style($k + 1));
                }

                $rows[$target] = [$cells, $merges];
            }
        }

        // ── Firmas ───────────────────────────────────────────────────────────
        $rows[] = [[], []];
        $rows[] = [[], []];
        $rows[] = $line([
            'Vo. Bo. Jefe inmediato'.($data['supervisor'] ? ' — '.$data['supervisor'] : ''), '', '', '', '',
            $employee->fullName().' — Trabajador', '', '', '', '', 'Talento humano',
        ], [], fn (): Style => (new Style)->setFontSize(9)->setBorder(new Border(new BorderPart(Border::TOP, '000000', Border::WIDTH_THIN, Border::STYLE_SOLID))));

        // Que quede escrito de dónde salió.
        $rows[] = [[], []];
        $rows[] = $line(['Generado con fronda.app el '.now()->format('d/m/Y h:i a')], [], fn (): Style => (new Style)->setFontSize(8)->setFontColor(FrondaWorkbook::GREEN));

        return $rows;
    }

    /** El estilo de una celda del formato: con bordes, centrada y con ajuste de texto. */
    private function style(bool $bold = false, ?string $fill = null, ?string $color = null, int $size = 9, string $align = CellAlignment::CENTER, ?string $format = null): Style
    {
        $part = fn (string $name): BorderPart => new BorderPart($name, '000000', Border::WIDTH_THIN, Border::STYLE_SOLID);
        $style = (new Style)
            ->setFontSize($size)
            ->setCellAlignment($align)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText()
            ->setBorder(new Border($part(Border::TOP), $part(Border::BOTTOM), $part(Border::LEFT), $part(Border::RIGHT)));

        if ($bold) {
            $style->setFontBold();
        }

        if ($fill) {
            $style->setBackgroundColor($fill);
        }

        if ($color) {
            $style->setFontColor($color);
        }

        if ($format) {
            $style->setFormat($format);
        }

        return $style;
    }

    /**
     * El nombre de la pestaña: nombre y primer apellido, como en el libro. Excel no acepta
     * más de 31 caracteres ni algunos signos, y no puede repetirse.
     *
     * @param  array<string, true>  $used
     */
    private function sheetName(Employee $employee, array &$used): string
    {
        $base = mb_strtoupper(trim(strtok((string) $employee->first_name, ' ').' '.strtok((string) $employee->last_name, ' ')));
        $base = mb_substr(preg_replace('/[\[\]:*?\/\\\\]/u', '', $base) ?: 'TRABAJADOR', 0, 28);
        $name = $base;

        for ($n = 2; isset($used[$name]); $n++) {
            $name = "{$base} {$n}";
        }

        $used[$name] = true;

        return $name;
    }
}
