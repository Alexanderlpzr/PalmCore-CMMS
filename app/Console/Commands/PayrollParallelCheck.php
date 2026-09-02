<?php

namespace App\Console\Commands;

use App\Domain\HumanResources\Services\PayrollRunService;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader;

use function Laravel\Prompts\select;

/**
 * El paralelo: liquida el período en el sistema y lo compara, peso a peso, contra el neto
 * que trae el libro de Excel.
 *
 * Es el paso que decide si el módulo puede reemplazar la hoja de cálculo, y por eso no
 * dice «coincide» o «no coincide» y ya: cuando hay diferencias las lista por trabajador y
 * ordenadas por tamaño, porque una diferencia de dos pesos es redondeo y una de doscientos
 * mil es un concepto que falta. Las dos hay que verlas, pero no son el mismo problema.
 *
 * La tolerancia por omisión es de un peso. El Excel arrastra decimales largos —las
 * bonificaciones vienen con seis— y el sistema redondea a dos en cada renglón: pedir
 * coincidencia exacta al centavo produciría cuarenta y ocho diferencias que no significan
 * nada.
 */
class PayrollParallelCheck extends Command
{
    protected $signature = 'payroll:parallel-check
        {file : Ruta al .xlsx de la nómina}
        {--tenant= : ID de la empresa}
        {--period-start=2026-08-01 : Primer día del período}
        {--period-end=2026-08-30 : Último día del período}
        {--tolerance=1 : Diferencia en pesos que se considera redondeo}
        {--recalculate : Vuelve a liquidar antes de comparar}';

    protected $description = 'Compara la liquidación del sistema contra el neto del libro de Excel';

    private const LIQUIDACION_FIRST_ROW = 6;

    private const COL_CEDULA = 2;

    private const COL_NOMBRE = 3;

    private const COL_NETO = 77; // BY

    public function handle(PayrollRunService $runService): int
    {
        $file = (string) $this->argument('file');

        if (! is_readable($file)) {
            $this->error("No se puede leer el archivo: {$file}");

            return self::FAILURE;
        }

        $tenant = $this->resolveTenant();

        if (! $tenant) {
            return self::FAILURE;
        }

        $from = Carbon::parse($this->option('period-start'));
        $to = Carbon::parse($this->option('period-end'));
        $tolerance = (float) $this->option('tolerance');

        $run = PayrollRun::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereDate('period_start', $from)
            ->whereDate('period_end', $to)
            ->first();

        if (! $run) {
            $this->error('No hay una nómina para ese período. Corra primero payroll:import-workbook.');

            return self::FAILURE;
        }

        if ($this->option('recalculate') || $run->calculated_at === null) {
            $this->info('Liquidando…');
            $run = $runService->calculate($run);
        }

        $excel = $this->readExpectedNets($file);
        $entries = PayrollEntry::withoutGlobalScopes()
            ->where('payroll_run_id', $run->id)
            ->orderBy('employee_name')
            ->get()
            ->keyBy('document_number');

        return $this->report($run, $entries, $excel, $tolerance);
    }

    /**
     * @param  Collection<string, PayrollEntry>  $entries
     * @param  array<string, array{nombre: string, neto: float}>  $excel
     */
    private function report($run, $entries, array $excel, float $tolerance): int
    {
        $diffs = [];
        $matched = 0;
        $missingInSystem = [];
        $missingInExcel = [];
        $sumExcel = 0.0;
        $sumSystem = 0.0;

        foreach ($excel as $cedula => $row) {
            $sumExcel += $row['neto'];

            $entry = $entries->get($cedula);

            if (! $entry) {
                $missingInSystem[] = $row['nombre'];

                continue;
            }

            $system = (float) $entry->net_pay;
            $sumSystem += $system;
            $delta = $system - $row['neto'];

            if (abs($delta) <= $tolerance) {
                $matched++;

                continue;
            }

            $diffs[] = [
                'nombre' => $row['nombre'],
                'excel' => $row['neto'],
                'sistema' => $system,
                'delta' => $delta,
                'avisos' => $entry->hasWarnings() ? implode(' · ', $entry->warnings) : '',
            ];
        }

        foreach ($entries as $cedula => $entry) {
            if (! isset($excel[$cedula])) {
                $missingInExcel[] = $entry->employee_name;
            }
        }

        // Las diferencias grandes primero: son las que esconden un concepto que falta.
        usort($diffs, fn (array $a, array $b): int => abs($b['delta']) <=> abs($a['delta']));

        $this->newLine();
        $this->line('<options=bold>Paralelo — '.$run->name.'</>');
        $this->newLine();

        $this->table(
            ['', 'Excel', 'Sistema', 'Diferencia'],
            [[
                'Neto del período',
                $this->money($sumExcel),
                $this->money($sumSystem),
                $this->money($sumSystem - $sumExcel),
            ]],
        );

        $this->line("Coinciden dentro de la tolerancia: <fg=green>{$matched}</> de ".count($excel));

        if ($missingInSystem !== []) {
            $this->newLine();
            $this->warn('En el Excel pero no en el sistema: '.implode(', ', array_map(
                fn (string $n): string => Str::limit($n, 28),
                $missingInSystem,
            )));
        }

        if ($missingInExcel !== []) {
            $this->newLine();
            $this->warn('En el sistema pero no en el Excel: '.implode(', ', array_map(
                fn (string $n): string => Str::limit($n, 28),
                $missingInExcel,
            )));
        }

        if ($diffs === []) {
            $this->newLine();
            $this->info('Sin diferencias por encima de la tolerancia.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('<options=bold>Diferencias, de mayor a menor</>');
        $this->table(
            ['Trabajador', 'Excel', 'Sistema', 'Diferencia', 'Avisos'],
            array_map(fn (array $d): array => [
                Str::limit($d['nombre'], 30),
                $this->money($d['excel']),
                $this->money($d['sistema']),
                $this->money($d['delta']),
                Str::limit($d['avisos'], 44),
            ], $diffs),
        );

        $this->newLine();
        $this->comment(
            'Una diferencia de pocos pesos es redondeo. Una grande es un concepto que falta '
            .'o un parámetro distinto: revise primero los que traen avisos.'
        );

        // Falla a propósito: así el paralelo sirve en un script y no solo mirándolo.
        return self::FAILURE;
    }

    /** @return array<string, array{nombre: string, neto: float}> */
    private function readExpectedNets(string $file): array
    {
        $rows = [];
        $reader = new Reader;
        $reader->open($file);

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== 'NOMINA ACTUALIZADA') {
                continue;
            }

            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                if ($rowIndex < self::LIQUIDACION_FIRST_ROW) {
                    continue;
                }

                $cells = [];

                foreach ($row->getCells() as $i => $cell) {
                    // El neto del libro es una fórmula: `getValue()` devolvería el texto
                    // «=+BK6-BX6» y la comparación se haría contra cero.
                    $cells[$i + 1] = $cell instanceof FormulaCell
                        ? $cell->getComputedValue()
                        : $cell->getValue();
                }

                $cedula = trim((string) ($cells[self::COL_CEDULA] ?? ''));
                $nombre = trim((string) ($cells[self::COL_NOMBRE] ?? ''));
                $neto = $cells[self::COL_NETO] ?? null;

                if ($cedula === '' || $nombre === '' || ! is_numeric($neto)) {
                    continue;
                }

                $rows[$cedula] = ['nombre' => $nombre, 'neto' => (float) $neto];
            }
        }

        $reader->close();

        return $rows;
    }

    private function money(float $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private function resolveTenant(): ?Tenant
    {
        if ($id = $this->option('tenant')) {
            return Tenant::find($id);
        }

        $tenants = Tenant::query()->orderBy('name')->pluck('name', 'id')->all();

        if ($tenants === []) {
            $this->error('No hay empresas registradas.');

            return null;
        }

        return Tenant::find(select('¿En qué empresa?', $tenants));
    }
}
