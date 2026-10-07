<?php

namespace App\Console\Commands;

use App\Domain\HumanResources\Services\OvertimeParallel;
use App\Domain\HumanResources\Services\OvertimeWorkbookReader;
use App\Domain\Reports\Services\FormatoHorasExtrasPdfService;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * El paralelo del libro de horas extras (TH-NOM-P-001) contra el sistema.
 *
 * Lee las hojas visibles del libro, pasa la hora de entrada y de salida de cada día por
 * el reloj y por la regla del bono, y compara bolsa por bolsa con lo que se escribió a
 * mano. No escribe nada en la base de datos: muestra el resumen por trabajador y deja el
 * detalle día por día en un Excel, para revisarlo con talento humano antes de dejar de
 * llenar el libro.
 */
#[Signature('payroll:overtime-parallel
    {file : Ruta del libro de horas extras (.xlsx)}
    {--tenant= : Slug de la empresa; sin él, la única que haya}
    {--month= : Mes que se paga (AAAA-MM); por omisión, el del último día del libro}
    {--output= : Dónde guardar el detalle (.xlsx)}')]
#[Description('Compara el libro de horas extras con lo que calcula el sistema, trabajador por trabajador')]
class OvertimeWorkbookParallel extends Command
{
    public function handle(OvertimeWorkbookReader $reader, OvertimeParallel $parallel): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("No encuentro el libro {$file}.");

            return self::FAILURE;
        }

        $tenant = $this->resolveTenant();

        if (! $tenant) {
            return self::FAILURE;
        }

        $sheets = $reader->read($file);

        if ($sheets === []) {
            $this->error('El libro no tiene hojas de trabajador visibles con el formato de horas extras.');

            return self::FAILURE;
        }

        $month = $this->option('month')
            ? CarbonImmutable::parse($this->option('month').'-01')
            : collect($sheets)->flatMap(fn (array $s): array => array_column($s['days'], 'date'))->filter()->max()->startOfMonth();

        $results = $parallel->compare($sheets, $tenant, $month->startOfMonth());

        $this->info("{$tenant->name}: ".count($results).' hojas, nómina de '.$month->locale('es')->translatedFormat('F \d\e Y').'.');
        $this->table(
            ['Hoja', 'Trabajador', 'Días', 'Extras Excel', 'Extras sistema', 'Bono Excel', 'Bono sistema', 'Días distintos', 'Bono $ Excel', 'Bono $ sistema'],
            array_map(fn (array $r): array => [
                $r['sheet'],
                $r['name'].($r['found'] ? '' : ' (no está en Personal)').($r['earnsOvertime'] ? '' : ' (no causa extras)'),
                count($r['days']),
                $this->hours($r['excelLegal']),
                $this->hours($r['systemLegal']),
                $this->hours($r['excelBonus']),
                $this->hours($r['systemBonus']),
                $r['differentDays'],
                $r['excelBonusValue'] === null ? '—' : $this->money($r['excelBonusValue']),
                $this->money($r['systemBonusValue']),
            ], $results),
        );

        $days = array_sum(array_map(fn (array $r): int => count($r['days']), $results));
        $different = array_sum(array_column($results, 'differentDays'));
        $this->line("Días comparados: {$days}. Iguales: ".($days - $different).". Distintos: {$different}.");

        $output = $this->option('output') ?: storage_path('app/private/paralelos/horas-extra-'.$month->format('Y-m').'-'.now()->format('Ymd-His').'.xlsx');
        $this->writeDetail($output, $results);
        $this->info("El detalle día por día quedó en {$output}");

        return self::SUCCESS;
    }

    /** @param  list<array<string, mixed>>  $results */
    private function writeDetail(string $path, array $results): void
    {
        File::ensureDirectoryExists(dirname($path));

        $siglas = FormatoHorasExtrasPdfService::COLUMNS;
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Resumen');
        $writer->addRow(Row::fromValues(['Hoja', 'Trabajador', 'Documento', 'En Personal', 'Días', 'Extras Excel', 'Extras sistema', 'Bono Excel', 'Bono sistema', 'Días distintos', 'Bono $ Excel', 'Bono $ sistema']));

        foreach ($results as $r) {
            $writer->addRow(Row::fromValues([
                $r['sheet'], $r['name'], $r['document'], $r['found'] ? 'Sí' : 'No', count($r['days']),
                round($r['excelLegal'], 2), round($r['systemLegal'], 2), round($r['excelBonus'], 2), round($r['systemBonus'], 2),
                $r['differentDays'], $r['excelBonusValue'], round($r['systemBonusValue'], 0),
            ]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Días');

        $header = ['Hoja', 'Trabajador', 'Fecha', 'Inicio', 'Salida', 'Horas', 'Descanso trabajado', 'Distinto'];

        foreach ($siglas as $sigla) {
            array_push($header, "{$sigla} Excel", "{$sigla} sistema", "Bono {$sigla} Excel", "Bono {$sigla} sistema");
        }

        $writer->addRow(Row::fromValues([...$header, 'Justificación']));

        foreach ($results as $r) {
            foreach ($r['days'] as $day) {
                $row = [
                    $r['sheet'], $r['name'], $day['date']?->format('d/m/Y'), $day['start'], $day['exit'],
                    $day['system']['worked'], $day['system']['restDay'] ? 'Sí' : '', $day['differs'] ? 'Sí' : '',
                ];

                foreach (array_keys($siglas) as $bucket) {
                    array_push($row, $day['legal'][$bucket], $day['system']['legal'][$bucket], $day['bonus'][$bucket], $day['system']['bonus'][$bucket]);
                }

                $writer->addRow(Row::fromValues([...$row, $day['notes']]));
            }
        }

        $writer->close();
    }

    private function resolveTenant(): ?Tenant
    {
        $slug = $this->option('tenant');
        $tenants = Tenant::query()->when($slug, fn ($query, string $slug) => $query->where('slug', $slug))->get();

        if ($tenants->count() === 1) {
            return $tenants->first();
        }

        $this->error($slug ? "No encontré la empresa {$slug}." : 'Hay varias empresas: indique cuál con --tenant.');

        return null;
    }

    private function hours(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private function money(float $value): string
    {
        return '$ '.number_format($value, 0, ',', '.');
    }
}
