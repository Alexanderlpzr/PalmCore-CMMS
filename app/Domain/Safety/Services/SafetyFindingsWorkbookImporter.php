<?php

namespace App\Domain\Safety\Services;

use App\Domain\Safety\Enums\FindingPriority;
use App\Domain\Safety\Enums\FindingStatus;
use App\Domain\Safety\Enums\ResponsibleArea;
use App\Models\Area;
use App\Models\SstFinding;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use ZipArchive;

/**
 * Trae a Fronda el «Cuadro de hallazgos SG-SST / Ambiental» que SST llevaba en Excel,
 * con sus fotos.
 *
 * Columnas: A ITM, B fecha de reporte, C descripción, D ubicación, E prioridad, F plan de
 * acción, H área responsable, I foto del antes, J foto del después, K fecha de corrección
 * (o «Pendiente»). Lo decidido con la empresa el 2026-11-05:
 *
 *  - Con fecha de corrección entra «Ejecutado» en esa fecha, todas sus áreas hechas.
 *  - «Pendiente» entra pendiente; si es de mantenimiento, con su reporte de novedad.
 *  - Una fila con solo la foto entra sin descripción, a cargo de SST para completarla.
 *  - Los que ya estaban vencidos no disparan el aviso de vencido: ya se sabían.
 *
 * El número del hallazgo es el ITM: importar dos veces no repite ninguno.
 */
class SafetyFindingsWorkbookImporter
{
    /** Palabras de la ubicación → la sección de la planta (sin tildes). */
    private const AREA_KEYWORDS = [
        'tolva' => 'recepcion',
        'esteriliz' => 'esterilizacion',
        'estiriliz' => 'esterilizacion',
        'caldera' => 'cogeneracion',
        'cuarto de maquinas' => 'cogeneracion',
        'prensa' => 'extraccion',
        'palmister' => 'palmisteria',
        'clarifica' => 'clarificacion',
        'esclusa' => 'desfibrado',
    ];

    public function __construct(private SafetyFindingService $findings) {}

    /**
     * @return array{created: int, executed: int, pending: int, photos: int, skipped: int, warnings: list<string>}
     */
    public function import(string $path, string $tenantId, ?User $importedBy): array
    {
        $rows = $this->rows($path);
        $photos = $this->photos($path);
        $areas = Area::withoutGlobalScopes()->where('tenant_id', $tenantId)->get()
            ->mapWithKeys(fn (Area $area): array => [self::normalize($area->name) => $area->id]);
        // Lo que no trae fecha de reporte toma la del cuadro («Fecha:» arriba a la derecha).
        $chartDate = self::date($rows[3]['K'] ?? null) ?? SstFinding::today($tenantId);
        $existing = SstFinding::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tenantId)->pluck('number')->all();

        $summary = ['created' => 0, 'executed' => 0, 'pending' => 0, 'photos' => 0, 'skipped' => 0, 'warnings' => []];

        foreach ($rows as $excelRow => $cells) {
            $number = $cells['A'] ?? null;

            if (! is_numeric($number)) {
                continue;
            }

            $number = (int) $number;
            $description = self::text($cells['C'] ?? null);
            $before = $photos[$excelRow]['I'] ?? null;
            $after = $photos[$excelRow]['J'] ?? null;

            // Ni texto ni foto: la fila numerada que quedó lista para el siguiente.
            if ($description === null && $before === null) {
                continue;
            }

            if (in_array($number, $existing, true)) {
                $summary['skipped']++;

                continue;
            }

            $reportedOn = self::date($cells['B'] ?? null);
            $correctedOn = self::date($cells['K'] ?? null);
            $location = self::text($cells['D'] ?? null);
            $photoOnly = $description === null;

            if ($reportedOn === null) {
                $summary['warnings'][] = "Fila {$excelRow} (ITM {$number}): sin fecha de reporte, se tomó la del cuadro ({$chartDate->format('d/m/Y')}).";
            }

            $finding = DB::transaction(function () use ($tenantId, $importedBy, $number, $description, $location, $cells, $reportedOn, $correctedOn, $photoOnly, $before, $after, $areas, $chartDate): SstFinding {
                $finding = $this->findings->create($tenantId, [
                    'number' => $number,
                    'reported_on' => $reportedOn ?? $chartDate,
                    'description' => $description,
                    'area_id' => $this->areaFor($location, $areas),
                    'location_detail' => $location ? Str::limit($location, 250, '') : null,
                    'priority' => FindingPriority::fromText(self::text($cells['E'] ?? null)),
                    'action_plan' => self::text($cells['F'] ?? null),
                    'areas' => $photoOnly ? [ResponsibleArea::Sst] : ResponsibleArea::fromText(self::text($cells['H'] ?? null)),
                    'photo_before_path' => $before ? $this->store($tenantId, $number, 'antes', $before) : null,
                    'photo_after_path' => $after ? $this->store($tenantId, $number, 'despues', $after) : null,
                ], $importedBy, notify: false, reportToMaintenance: $correctedOn === null && ! $photoOnly);

                if ($correctedOn !== null) {
                    foreach ($finding->tasks as $task) {
                        $task->update(['status' => FindingStatus::Ejecutado, 'executed_on' => $correctedOn->toDateString(), 'notes' => 'Importado del cuadro de Excel']);
                    }

                    $finding->update(['status' => FindingStatus::Ejecutado, 'executed_on' => $correctedOn->toDateString()]);
                }

                if ($finding->refresh()->isOverdue()) {
                    $finding->update(['overdue_notified_at' => now()]);
                }

                return $finding;
            });

            $existing[] = $number;
            $summary['created']++;
            $summary[$finding->status === FindingStatus::Ejecutado ? 'executed' : 'pending']++;
            $summary['photos'] += ($before ? 1 : 0) + ($after ? 1 : 0);
        }

        return $summary;
    }

    /**
     * Las celdas de la primera hoja visible, por fila de Excel y letra de columna.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $path): array
    {
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            if (! $sheet->isVisible()) {
                continue;
            }

            foreach ($sheet->getRowIterator() as $index => $row) {
                foreach ($row->getCells() as $column => $cell) {
                    $value = $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell->getValue();

                    if ($value !== null && $value !== '') {
                        $rows[$index][chr(ord('A') + $column)] = $value;
                    }
                }
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * Las fotos ancladas en las columnas I (antes) y J (después), por fila de Excel: los
     * bytes de cada imagen. OpenSpout no lee imágenes, así que se leen del dibujo de la hoja.
     *
     * @return array<int, array<string, array{bytes: string, extension: string}>>
     */
    private function photos(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return [];
        }

        $photos = [];
        $sheetRels = (string) $zip->getFromName('xl/worksheets/_rels/sheet1.xml.rels');
        preg_match('#Target="(?:\.\./drawings/|/xl/drawings/)(drawing\d+\.xml)"#', $sheetRels, $drawingMatch);
        $drawing = $drawingMatch[1] ?? 'drawing1.xml';
        $xml = (string) $zip->getFromName("xl/drawings/{$drawing}");
        $rels = (string) $zip->getFromName("xl/drawings/_rels/{$drawing}.rels");

        $targets = [];
        preg_match_all('#<Relationship\b[^>]*>#', $rels, $relationships);
        foreach ($relationships[0] as $relationship) {
            if (preg_match('#Id="([^"]+)"#', $relationship, $id) && preg_match('#Target="([^"]+)"#', $relationship, $target)) {
                $targets[$id[1]] = 'xl/'.ltrim(str_replace('../', '', $target[1]), '/');
            }
        }

        preg_match_all('#<xdr:(twoCellAnchor|oneCellAnchor)\b.*?</xdr:\1>#s', $xml, $anchors);

        foreach ($anchors[0] as $anchor) {
            if (! preg_match('#<xdr:from><xdr:col>(\d+)</xdr:col>.*?<xdr:row>(\d+)</xdr:row>#s', $anchor, $from)
                || ! preg_match('#r:embed="([^"]+)"#', $anchor, $embed)
                || ! isset($targets[$embed[1]])) {
                continue;
            }

            $column = match ((int) $from[1]) {
                8 => 'I',
                9 => 'J',
                default => null,
            };
            $bytes = $zip->getFromName($targets[$embed[1]]);

            if ($column !== null && $bytes !== false) {
                $photos[(int) $from[2] + 1][$column] ??= [
                    'bytes' => $bytes,
                    'extension' => strtolower(pathinfo($targets[$embed[1]], PATHINFO_EXTENSION)) ?: 'jpg',
                ];
            }
        }

        $zip->close();

        return $photos;
    }

    /** @param  array{bytes: string, extension: string}  $photo */
    private function store(string $tenantId, int $number, string $moment, array $photo): string
    {
        $path = "sst-findings/{$tenantId}/hallazgo-{$number}-{$moment}-".Str::lower(Str::random(6)).".{$photo['extension']}";

        Storage::disk(persistent_disk())->put($path, $photo['bytes'], persistent_disk() === 'public' ? 'public' : 'private');

        return $path;
    }

    /** @param  Collection<string, string>  $areas */
    private function areaFor(?string $location, $areas): ?string
    {
        $location = self::normalize((string) $location);

        foreach (self::AREA_KEYWORDS as $keyword => $areaName) {
            if (str_contains($location, $keyword) && $areas->has($areaName)) {
                return $areas->get($areaName);
            }
        }

        return null;
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        // Una fecha sin formato de fecha llega como el número de serie de Excel (45790).
        if (is_numeric($value) && $value > 20_000 && $value < 80_000) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $value);
        }

        if (is_string($value) && preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', trim($value), $parts)) {
            return CarbonImmutable::createFromDate((int) $parts[3], (int) $parts[2], (int) $parts[1])->startOfDay();
        }

        return null;
    }

    /** El texto sin los espacios de relleno con que el Excel parte las líneas. */
    private static function text(mixed $value): ?string
    {
        $text = trim((string) preg_replace('/\s{2,}/u', ' ', (string) $value));

        return $text === '' ? null : $text;
    }

    private static function normalize(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }
}
