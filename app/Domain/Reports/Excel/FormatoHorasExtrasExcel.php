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
 * las columnas de bonificación que tengan horas. El logo no va: la librería que escribe el
 * libro no inserta imágenes, y el nombre de la empresa ocupa su lugar.
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

        foreach ($employees->values() as $index => $employee) {
            $sheet = $index === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($this->sheetName($employee, $usedNames));

            $data = $this->formato->data($employee, $from, $to);
            $tenant = Tenant::withoutGlobalScopes()->find($employee->tenant_id);
            // Fecha, horas, las siete bolsas, la justificación y los bonos con horas.
            $lastColumn = self::LEAD_COLUMNS + count(Formato::COLUMNS) + 1 + count($data['bonusColumns']);

            $sheet->setColumnWidth(34, 1);
            $sheet->setColumnWidthForRange(10, 2, 4);
            $sheet->setColumnWidthForRange(13, 5, self::LEAD_COLUMNS + count(Formato::COLUMNS));
            $sheet->setColumnWidth(30, self::LEAD_COLUMNS + count(Formato::COLUMNS) + 1);

            foreach ($this->rows($data, $tenant, $showValues, $lastColumn) as [$cells, $merges]) {
                $writer->addRow(new Row($cells));

                foreach ($merges as [$fromColumn, $toColumn]) {
                    $row = $sheet->getWrittenRowCount();
                    $options->mergeCells($fromColumn - 1, $row, $toColumn - 1, $row, $index);
                }
            }

            // El encabezado ocupa tres filas: la empresa y el título van fundidos en alto.
            $options->mergeCells(0, 1, 0, 3, $index);
            $options->mergeCells(1, 1, $lastColumn - 2, 3, $index);
        }

        if ($employees->isEmpty()) {
            $writer->addRow(Row::fromValues(['No hay trabajadores con horas en el periodo.']));
        }

        $writer->close();

        return $path;
    }

    /**
     * Las filas de una hoja, cada una con las celdas que se funden.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{0: list<Cell>, 1: list<array{0: int, 1: int}>}>
     */
    private function rows(array $data, ?Tenant $tenant, bool $showValues, int $lastColumn): array
    {
        $employee = $data['employee'];
        $rows = [];
        $line = fn (array $values, array $merges = [], ?callable $style = null): array => [
            array_map(fn ($value, int $i): Cell => Cell::fromValue($value, ($style ?? fn () => $this->style())($i + 1)), $values, array_keys($values)),
            $merges,
        ];
        $pad = fn (array $values): array => array_pad($values, $lastColumn, '');

        // ── Encabezado con el control documental ─────────────────────────────
        $title = fn (int $column): Style => $column === $lastColumn ? $this->style(bold: true, align: CellAlignment::LEFT) : $this->style(bold: true, size: $column === 1 ? 9 : 13);
        $rows[] = $line($pad([mb_strtoupper($tenant?->name ?? ''), 'FORMATO DE HORAS EXTRAS', ...array_fill(0, $lastColumn - 3, ''), 'CÓDIGO: '.Formato::FORM_CODE]), [], $title);
        $rows[] = $line($pad([...array_fill(0, $lastColumn - 1, ''), 'VERSIÓN: '.Formato::FORM_VERSION]), [], $title);
        $rows[] = $line($pad([...array_fill(0, $lastColumn - 1, ''), 'FECHA: '.Formato::FORM_DATE]), [], $title);
        $rows[] = [[], []];

        // ── El trabajador ────────────────────────────────────────────────────
        $who = fn (int $column): Style => in_array($column, [1, $lastColumn - 1], true) ? $this->style(bold: true, fill: 'E6E6E6', align: CellAlignment::LEFT) : $this->style();
        $rows[] = $line($pad(['NOMBRE TRABAJADOR', mb_strtoupper($employee->fullName()), ...array_fill(0, $lastColumn - 4, ''), 'IDENTIFICACIÓN', $employee->document_number]), [[2, $lastColumn - 2]], $who);

        foreach ([
            'CARGO' => mb_strtoupper($employee->position ?? ''),
            'JEFE INMEDIATO' => mb_strtoupper($data['supervisor'] ?? ''),
            'PERIODO' => 'Horas del '.$data['from']->format('d/m/Y').' al '.$data['to']->format('d/m/Y').' — nómina de '.$data['periodStart']->locale('es')->translatedFormat('F \d\e Y'),
        ] as $label => $value) {
            $rows[] = $line($pad([$label, $value]), [[2, $lastColumn]], fn (int $c): Style => $c === 1 ? $this->style(bold: true, fill: 'E6E6E6', align: CellAlignment::LEFT) : $this->style());
        }

        $rows[] = [[], []];

        // ── La cuadrícula ────────────────────────────────────────────────────
        $bonusStart = self::LEAD_COLUMNS + count(Formato::COLUMNS) + 2;
        $header = ['FECHA', 'HORA INICIO', 'FIN HORARIO LABORAL', 'HORA SALIDA', ...array_values(Formato::COLUMN_TITLES), 'JUSTIFICACIÓN'];

        foreach ($data['bonusColumns'] as $bucket) {
            $header[] = 'BONO '.Formato::COLUMNS[$bucket];
        }

        $rows[] = $line($header, [], fn (int $c): Style => $this->style(bold: true, fill: $c >= $bonusStart ? 'F2E2BF' : 'D9D9D9', size: 8));

        $hour = fn ($moment): string => $moment ? $moment->format('g:ia') : '';
        $hours = fn (float $value): float|string => $value > 0 ? round($value, 2) : '';

        foreach ($data['rows'] as $day) {
            $values = [
                $day['date']->locale('es')->translatedFormat('l, j \d\e F \d\e Y'),
                $hour($day['entry']), $hour($day['scheduledEnd']), $hour($day['exit']),
                ...array_map($hours, array_values($day['legal'])),
                $day['notes'],
                ...array_map(fn (string $bucket) => $hours($day['bonus'][$bucket]), $data['bonusColumns']),
            ];

            $rows[] = $line($values, [], fn (int $c): Style => match (true) {
                $c === 1 => $this->style(color: $day['surcharged'] ? 'D00000' : null),
                $c >= $bonusStart => $this->style(fill: 'FBF4E4'),
                default => $this->style(),
            });
        }

        $rows[] = $line([
            'TOTAL HORAS', '', '', '',
            ...array_map($hours, array_values($data['legalTotals'])),
            '',
            ...array_map(fn (string $bucket) => $hours($data['bonusTotals'][$bucket]), $data['bonusColumns']),
        ], [[1, 4]], fn (int $c): Style => $this->style(bold: true, fill: 'EFEFEF'));

        // ── La calculadora ───────────────────────────────────────────────────
        if ($showValues) {
            $rows[] = [[], []];
            $money = fn (float $value): float => round($value, 0);
            $rows[] = $line([
                'Valor hora: $ '.number_format($data['salary'], 0, ',', '.').' ÷ '.number_format($data['divisor'], 0, ',', '.'),
                'Factor', 'Valor hora', 'Horas', 'Horas extras y recargos', 'Horas bono', 'Bonificación',
            ], [], fn (): Style => $this->style(bold: true, fill: 'D9D9D9', size: 8));

            foreach ($data['calculator'] as $row) {
                $rows[] = $line([
                    $row['label'].' — '.$row['concept'], $row['factor'], $money($row['rate']),
                    $hours($row['legalHours']), $row['legalAmount'] > 0 ? $money($row['legalAmount']) : '',
                    $hours($row['bonusHours']), $row['bonusAmount'] > 0 ? $money($row['bonusAmount']) : '',
                ]);
            }

            $rows[] = $line(['TOTAL', '', '', '', $money($data['legalAmount']), '', $money($data['bonusAmount'])], [], fn (): Style => $this->style(bold: true, fill: 'EFEFEF'));
        }

        // ── Firmas ───────────────────────────────────────────────────────────
        $rows[] = [[], []];
        $rows[] = [[], []];
        $rows[] = $line([
            'Vo. Bo. Jefe inmediato'.($data['supervisor'] ? ' — '.$data['supervisor'] : ''), '', '', '', '',
            $employee->fullName().' — Trabajador', '', '', '', '', 'Talento humano',
        ], [], fn (): Style => (new Style)->setFontSize(9)->setBorder(new Border(new BorderPart(Border::TOP, '000000', Border::WIDTH_THIN, Border::STYLE_SOLID))));

        return $rows;
    }

    /** El estilo de una celda del formato: con bordes, centrada y con ajuste de texto. */
    private function style(bool $bold = false, ?string $fill = null, ?string $color = null, int $size = 9, string $align = CellAlignment::CENTER): Style
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
