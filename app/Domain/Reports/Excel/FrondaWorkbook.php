<?php

namespace App\Domain\Reports\Excel;

use App\Models\Tenant;
use Filament\Facades\Filament;
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
 * El libro de Excel con la cara de fronda.app: todos los que salen del sistema llevan el
 * mismo encabezado —la franja petróleo con «fronda.app» y la empresa, el título en verde y
 * la fecha— y las tablas con el mismo estilo. Así, un Excel abierto en cualquier equipo
 * se reconoce de un vistazo como salido del sistema y no de una hoja hecha a mano.
 *
 * Los colores son los de la marca (`FrondaPalette`): verde y petróleo del logo.
 */
final class FrondaWorkbook
{
    public const GREEN = '1A7E42';

    public const PETROL = '00384C';

    /** Verde muy claro: las filas alternas y la línea de la fecha. */
    public const MIST = 'EAF4EE';

    public const MONEY = '"$ "#,##0';

    public const HOURS = '#,##0.##';

    public const INTEGER = '#,##0';

    private Writer $writer;

    private Options $options;

    private int $sheetIndex = -1;

    /** @var array{0: string, 1: string}|null el logo de la empresa, que va arriba a la izquierda */
    private ?array $logo = null;

    /** @var array<string, true> */
    private array $usedNames = [];

    private function __construct(private readonly string $path)
    {
        $this->options = new Options;
        $this->writer = new Writer($this->options);
        $this->writer->openToFile($path);
    }

    /**
     * Un libro nuevo con el logo de la empresa —la de la pantalla si no se dice otra— en
     * la esquina superior izquierda de cada hoja.
     */
    public static function create(?Tenant $tenant = null): self
    {
        $book = new self(tempnam(sys_get_temp_dir(), 'fronda').'.xlsx');
        $tenant ??= Filament::getTenant() instanceof Tenant ? Filament::getTenant() : null;
        $book->logo = XlsxLogo::forTenant($tenant);

        return $book;
    }

    /** Una pestaña nueva. Excel no acepta más de 31 caracteres ni algunos signos, ni nombres repetidos. */
    public function sheet(string $name): self
    {
        $sheet = $this->sheetIndex === -1 ? $this->writer->getCurrentSheet() : $this->writer->addNewSheetAndMakeItCurrent();
        $this->sheetIndex++;

        $base = mb_substr(preg_replace('/[\[\]:*?\/\\\\]/u', '', trim($name)) ?: 'Hoja', 0, 28);
        $unique = $base;

        for ($n = 2; isset($this->usedNames[mb_strtoupper($unique)]); $n++) {
            $unique = "{$base} {$n}";
        }

        $this->usedNames[mb_strtoupper($unique)] = true;
        $sheet->setName($unique);

        return $this;
    }

    /** @param  array<int, float>  $widths  ancho por columna, empezando en 1 */
    public function widths(array $widths): self
    {
        foreach ($widths as $column => $width) {
            $this->writer->getCurrentSheet()->setColumnWidth($width, $column);
        }

        return $this;
    }

    /**
     * El encabezado de fronda.app: franja petróleo con la marca y la empresa, el título en
     * verde y una línea con el detalle y la fecha en que se generó.
     */
    public function banner(string $title, ?string $detail, ?string $company, int $width): self
    {
        $width = max(3, $width);
        // Con logo, la columna A de las tres filas queda en blanco para él, y las filas
        // crecen para que quepa.
        $first = $this->logo ? 2 : 1;
        $lead = $this->logo ? [''] : [];
        $blank = fn (int $c, Style $style): Style => $c < $first ? $this->style(border: false) : $style;

        $this->merged([...$lead, 'fronda.app', ...array_fill(0, $width - $first - 1, ''), $company ?? ''], $first, $width - 1, fn (int $c): Style => $blank($c, $this->style(bold: true, fill: self::PETROL, color: 'FFFFFF', size: 10, align: $c === $width ? CellAlignment::RIGHT : CellAlignment::LEFT, border: false)), $this->logo ? 22 : null);
        $this->merged([...$lead, $title, ...array_fill(0, $width - $first, '')], $first, $width, fn (int $c): Style => $blank($c, $this->style(bold: true, fill: self::GREEN, color: 'FFFFFF', size: 14, align: CellAlignment::LEFT, border: false)), $this->logo ? 26 : null);
        $this->merged([...$lead, trim(($detail ? $detail.' · ' : '').'Generado el '.now()->format('d/m/Y h:i a')), ...array_fill(0, $width - $first, '')], $first, $width, fn (int $c): Style => $blank($c, $this->style(fill: self::MIST, color: self::PETROL, size: 9, align: CellAlignment::LEFT, border: false)), $this->logo ? 22 : null);

        return $this->blank();
    }

    /**
     * Una tabla: encabezado verde y filas con borde, alternando un verde muy claro.
     *
     * @param  list<string>  $headers
     * @param  iterable<array<int|string, mixed>>  $rows
     * @param  array<int, string>  $formats  formato numérico por columna (base 1)
     */
    public function table(array $headers, iterable $rows, array $formats = []): self
    {
        $this->row($headers, fn (): Style => $this->style(bold: true, fill: self::GREEN, color: 'FFFFFF', size: 9));
        $n = 0;

        foreach ($rows as $row) {
            $zebra = $n++ % 2 === 1;
            $this->row(array_values($row), fn (int $c): Style => $this->style(
                fill: $zebra ? self::MIST : null,
                align: is_numeric(array_values($row)[$c - 1] ?? null) ? CellAlignment::RIGHT : CellAlignment::LEFT,
                format: $formats[$c] ?? null,
            ));
        }

        return $this;
    }

    /** Una fila de totales: negrita sobre verde claro. @param  array<int, string>  $formats */
    public function totals(array $values, array $formats = []): self
    {
        return $this->row($values, fn (int $c): Style => $this->style(bold: true, fill: 'D5EBDD', align: is_numeric($values[$c - 1] ?? null) ? CellAlignment::RIGHT : CellAlignment::LEFT, format: $formats[$c] ?? null));
    }

    /** Una fila cualquiera, con el estilo que diga la función por columna. */
    public function row(array $values, ?callable $style = null, ?float $height = null): self
    {
        $style ??= fn (): Style => $this->style();
        $cells = [];

        foreach (array_values($values) as $i => $value) {
            $cells[] = Cell::fromValue(is_bool($value) ? ($value ? 'Sí' : 'No') : $value, $style($i + 1));
        }

        $row = new Row($cells);

        if ($height) {
            $row->setHeight($height);
        }

        $this->writer->addRow($row);

        return $this;
    }

    /** Una fila con celdas fundidas de la columna `$from` a la `$to` (base 1). */
    public function merged(array $values, int $from, int $to, ?callable $style = null, ?float $height = null): self
    {
        $this->row($values, $style, $height);

        if ($to > $from) {
            $row = $this->writer->getCurrentSheet()->getWrittenRowCount();
            $this->options->mergeCells($from - 1, $row, $to - 1, $row, $this->sheetIndex);
        }

        return $this;
    }

    public function blank(): self
    {
        $this->writer->addRow(new Row([]));

        return $this;
    }

    /** El pie: que quede escrito de dónde salió. */
    public function footer(): self
    {
        $this->blank();

        return $this->row(['Generado con fronda.app'], fn (): Style => $this->style(color: self::GREEN, size: 8, align: CellAlignment::LEFT, border: false));
    }

    /** El estilo de una celda: borde fino, texto ajustado. */
    public function style(
        bool $bold = false,
        ?string $fill = null,
        ?string $color = null,
        int $size = 9,
        string $align = CellAlignment::LEFT,
        ?string $format = null,
        bool $border = true,
    ): Style {
        $style = (new Style)
            ->setFontSize($size)
            ->setFontName('Calibri')
            ->setCellAlignment($align)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText();

        if ($border) {
            $part = fn (string $side): BorderPart => new BorderPart($side, 'C9D6CE', Border::WIDTH_THIN, Border::STYLE_SOLID);
            $style->setBorder(new Border($part(Border::TOP), $part(Border::BOTTOM), $part(Border::LEFT), $part(Border::RIGHT)));
        }

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

    /** Cierra el libro y devuelve la ruta del archivo. */
    public function close(): string
    {
        $this->writer->close();

        if ($this->logo) {
            XlsxLogo::stamp($this->path, $this->logo);
        }

        return $this->path;
    }

    public function download(string $filename): StreamedResponse
    {
        $path = $this->close();

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
            @unlink($path);
        }, $filename);
    }
}
