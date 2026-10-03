<?php

namespace App\Console\Commands;

use App\Models\Holiday;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Carga el calendario de festivos de Colombia de un año en una empresa.
 *
 * Las fechas van escritas aquí, no calculadas, como pide `Holiday`: un error en un
 * festivo se paga a más del doble en toda la planta, y una lista escrita se revisa de un
 * vistazo contra el calendario oficial. Cada año nuevo se agrega a mano.
 *
 * No borra nada: un festivo propio de la empresa que ya esté cargado se queda.
 */
#[Signature('hr:load-holidays {year : Año del calendario, p. ej. 2027} {--tenant= : Slug de la empresa; sin él, todas}')]
#[Description('Carga los festivos de Colombia de un año en el calendario de la empresa')]
class LoadColombianHolidays extends Command
{
    /**
     * Ley 51 de 1983 (Ley Emiliani): seis fijos, siete corridos al lunes y cinco atados a
     * la Pascua (Jueves y Viernes Santo en su día; Ascensión, Corpus y Sagrado Corazón a
     * lunes). Dieciocho por año.
     *
     * @var array<int, array<string, string>>
     */
    public const CALENDARS = [
        2026 => [
            '2026-01-01' => 'Año Nuevo',
            '2026-01-12' => 'Reyes Magos',
            '2026-03-23' => 'San José',
            '2026-04-02' => 'Jueves Santo',
            '2026-04-03' => 'Viernes Santo',
            '2026-05-01' => 'Día del Trabajo',
            '2026-05-18' => 'Ascensión del Señor',
            '2026-06-08' => 'Corpus Christi',
            '2026-06-15' => 'Sagrado Corazón',
            '2026-06-29' => 'San Pedro y San Pablo',
            '2026-07-20' => 'Día de la Independencia',
            '2026-08-07' => 'Batalla de Boyacá',
            '2026-08-17' => 'Asunción de la Virgen',
            '2026-10-12' => 'Día de la Raza',
            '2026-11-02' => 'Todos los Santos',
            '2026-11-16' => 'Independencia de Cartagena',
            '2026-12-08' => 'Inmaculada Concepción',
            '2026-12-25' => 'Navidad',
        ],
        2027 => [
            '2027-01-01' => 'Año Nuevo',
            '2027-01-11' => 'Reyes Magos',
            '2027-03-22' => 'San José',
            '2027-03-25' => 'Jueves Santo',
            '2027-03-26' => 'Viernes Santo',
            '2027-05-01' => 'Día del Trabajo',
            '2027-05-10' => 'Ascensión del Señor',
            '2027-05-31' => 'Corpus Christi',
            '2027-06-07' => 'Sagrado Corazón',
            '2027-07-05' => 'San Pedro y San Pablo',
            '2027-07-20' => 'Día de la Independencia',
            '2027-08-07' => 'Batalla de Boyacá',
            '2027-08-16' => 'Asunción de la Virgen',
            '2027-10-18' => 'Día de la Raza',
            '2027-11-01' => 'Todos los Santos',
            '2027-11-15' => 'Independencia de Cartagena',
            '2027-12-08' => 'Inmaculada Concepción',
            '2027-12-25' => 'Navidad',
        ],
    ];

    public function handle(): int
    {
        $year = (int) $this->argument('year');
        $calendar = self::CALENDARS[$year] ?? null;

        if ($calendar === null) {
            $this->error("No hay calendario de {$year}. Agrégalo en LoadColombianHolidays::CALENDARS, revisado contra el oficial.");

            return self::FAILURE;
        }

        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($query, string $slug) => $query->where('slug', $slug))
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No encontré esa empresa.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $created = 0;

            foreach ($calendar as $date => $name) {
                $holiday = Holiday::query()->forTenant($tenant->id)->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'holiday_date' => $date],
                    ['name' => $name, 'is_national' => true],
                );

                $created += $holiday->wasRecentlyCreated ? 1 : 0;
            }

            $this->info("{$tenant->name}: {$created} festivos nuevos de {$year} (ya había ".(count($calendar) - $created).').');
        }

        return self::SUCCESS;
    }
}
