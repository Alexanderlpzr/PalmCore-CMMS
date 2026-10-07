<?php

namespace App\Console\Commands;

use App\Domain\HumanResources\Services\LaborReformSchedule;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Carga en los parámetros de nómina los cambios de la reforma laboral: noche desde las
 * 7 p. m., dominical del 90 % y jornada de 42 horas, cada uno desde su fecha.
 *
 * Sin `--apply` solo muestra el plan. Es idempotente: lo que ya está cargado se queda
 * como está, y un hito futuro solo entra cuando `--until` lo alcanza.
 */
#[Signature('payroll:apply-labor-reform
    {--tenant= : Slug de la empresa; sin él, todas}
    {--work-days=6 : Días que trabaja la empresa por semana, para repartir las 42 horas}
    {--until= : Hasta qué fecha aplicar hitos (AAAA-MM-DD); por omisión, hoy}
    {--apply : Escribe las vigencias; sin esto solo muestra el plan}')]
#[Description('Carga como vigencias de nómina los cambios de la reforma laboral (noche 7 p. m., dominical 90 %, jornada de 42 h)')]
class ApplyLaborReform extends Command
{
    public function handle(LaborReformSchedule $schedule): int
    {
        $workDays = (int) $this->option('work-days');

        if ($workDays < 1 || $workDays > 7) {
            $this->error('Los días por semana van de 1 a 7.');

            return self::FAILURE;
        }

        $until = $this->option('until') ? CarbonImmutable::parse($this->option('until')) : CarbonImmutable::today();

        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($query, string $slug) => $query->where('slug', $slug))
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No encontré esa empresa.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $rows = $this->option('apply')
                ? $schedule->apply($tenant->id, $until, $workDays)
                : $schedule->plan($tenant->id, $until, $workDays);

            $this->info($tenant->name.($this->option('apply') ? '' : ' (plan: no se escribió nada)'));
            $this->table(
                ['Desde', 'Parámetro', 'Antes', 'Después', 'Resultado', 'Norma'],
                array_map(fn (array $row): array => [
                    $row['from']->format('d/m/Y'),
                    $row['parameter']->label(),
                    $row['before'] === null ? '—' : $row['parameter']->unit()->format($row['before']),
                    $row['parameter']->unit()->format($row['after']),
                    $row['status'],
                    $row['law'],
                ], $rows),
            );
        }

        return self::SUCCESS;
    }
}
