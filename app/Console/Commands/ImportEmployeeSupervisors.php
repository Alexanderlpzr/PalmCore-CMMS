<?php

namespace App\Console\Commands;

use App\Domain\HumanResources\Services\OvertimeWorkbookReader;
use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Carga el jefe inmediato de cada trabajador desde el libro de horas extras (TH-NOM-P-001):
 * la fila «JEFE INMEDIATO» de su hoja. El trabajador se busca por la cédula de la hoja.
 *
 * Sin `--apply` solo muestra lo que cambiaría. No borra un jefe ya escrito si la hoja no
 * trae ninguno.
 */
#[Signature('hr:import-supervisors
    {file : Ruta del libro de horas extras (.xlsx)}
    {--tenant= : Slug de la empresa; sin él, la única que haya}
    {--apply : Escribe los cambios; sin esto solo muestra el plan}')]
#[Description('Carga el jefe inmediato de cada trabajador desde el libro de horas extras')]
class ImportEmployeeSupervisors extends Command
{
    public function handle(OvertimeWorkbookReader $reader): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("No encuentro el libro {$file}.");

            return self::FAILURE;
        }

        $slug = $this->option('tenant');
        $tenants = Tenant::query()->when($slug, fn ($query, string $slug) => $query->where('slug', $slug))->get();

        if ($tenants->count() !== 1) {
            $this->error($slug ? "No encontré la empresa {$slug}." : 'Hay varias empresas: indique cuál con --tenant.');

            return self::FAILURE;
        }

        $employees = Employee::query()
            ->forTenant($tenants->first()->id)
            ->get()
            ->keyBy(fn (Employee $e): string => preg_replace('/\D/', '', (string) $e->document_number));

        $rows = [];

        foreach ($reader->read($file) as $sheet) {
            $employee = $employees[$sheet['document']] ?? null;
            $supervisor = $sheet['supervisor'] ? mb_convert_case(mb_strtolower($sheet['supervisor']), MB_CASE_TITLE) : null;

            $status = match (true) {
                $employee === null => 'no está en Personal',
                $supervisor === null => 'la hoja no trae jefe',
                $employee->immediate_supervisor === $supervisor => 'ya estaba',
                default => $this->option('apply') ? 'cargado' : 'cambia',
            };

            if ($status === 'cargado') {
                $employee->update(['immediate_supervisor' => $supervisor]);
            }

            $rows[] = [$sheet['sheet'], $employee?->fullName() ?? $sheet['name'], $employee?->immediate_supervisor ?? '—', $supervisor ?? '—', $status];
        }

        $this->table(['Hoja', 'Trabajador', 'Jefe en la ficha', 'Jefe en el libro', 'Resultado'], $rows);

        if (! $this->option('apply')) {
            $this->info('Plan: no se escribió nada. Repita con --apply para cargarlo.');
        }

        return self::SUCCESS;
    }
}
