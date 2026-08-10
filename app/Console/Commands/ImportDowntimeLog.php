<?php

namespace App\Console\Commands;

use App\Domain\Assets\Services\DowntimeLogImporter;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Carga en el módulo de paros la planilla «REGISTROS DE PAROS» que la planta
 * lleva en Excel. Se invoca a mano; nunca entra en un despliegue.
 *
 * El archivo es el CSV exportado de esa hoja, con sus mismos encabezados. Es
 * idempotente: un paro ya cargado se salta, así que la planta puede reexportar
 * el Excel completo cada mes y volver a correrlo sin duplicar nada.
 */
class ImportDowntimeLog extends Command
{
    protected $signature = 'downtime:import
        {file : Ruta al CSV exportado de la hoja «Registrio Paros»}
        {--tenant= : Slug o ID de la organización}
        {--plant= : Código o ID de la planta (por defecto, la única que haya)}
        {--until= : Ignora los paros posteriores a esta fecha (YYYY-MM-DD)}
        {--dry-run : Muestra lo que se cargaría sin escribir nada}';

    protected $description = 'Importa el registro de paros de la planta desde el CSV de su planilla de Excel.';

    public function handle(DowntimeLogImporter $importer): int
    {
        $file = (string) $this->argument('file');

        if (! is_readable($file)) {
            $this->error("No se puede leer el archivo: {$file}");

            return self::FAILURE;
        }

        $tenant = $this->resolveTenant();

        if ($tenant === null) {
            return self::FAILURE;
        }

        $plant = $this->resolvePlant($tenant);

        if ($plant === null) {
            return self::FAILURE;
        }

        // El histórico no lo registró nadie en la app: se le atribuye al usuario
        // más antiguo de la organización para que la trazabilidad apunte a una
        // persona real y no a un usuario fantasma creado por el importador.
        $registeredBy = User::withoutGlobalScopes()
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenant->id))
            ->orderBy('created_at')
            ->first();

        if ($registeredBy === null) {
            $this->error("La organización «{$tenant->name}» no tiene usuarios.");

            return self::FAILURE;
        }

        $until = $this->option('until') !== null
            ? Carbon::parse((string) $this->option('until'))->endOfDay()
            : null;

        $dryRun = (bool) $this->option('dry-run');

        $this->components->info(sprintf(
            '%s paros en %s / %s%s',
            $dryRun ? 'Simulando' : 'Importando',
            $tenant->name,
            $plant->name,
            $until !== null ? ' hasta '.$until->format('d/m/Y') : '',
        ));

        $result = $importer->import($file, $plant, $registeredBy, $until, $dryRun);

        $this->newLine();
        $this->components->twoColumnDetail('Filas leídas', (string) $result['rows']);
        $this->components->twoColumnDetail(
            $dryRun ? 'Se cargarían' : 'Cargados',
            "<fg=green>{$result['imported']}</>",
        );
        $this->components->twoColumnDetail('Saltados (ya estaban o fuera de rango)', (string) $result['skipped']);
        $this->components->twoColumnDetail(
            'Con error',
            $result['errors'] === [] ? '0' : '<fg=red>'.count($result['errors']).'</>',
        );

        if ($result['unmatched'] !== []) {
            $this->newLine();
            $this->components->warn('Equipos del Excel que no están en el inventario (entraron como paro de planta):');

            foreach ($result['unmatched'] as $name => $count) {
                $this->line("  {$count}×  {$name}");
            }
        }

        if ($result['errors'] !== []) {
            $this->newLine();
            $this->components->error('Filas que no se pudieron cargar:');

            foreach ($result['errors'] as $error) {
                $this->line("  {$error}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveTenant(): ?Tenant
    {
        $option = $this->option('tenant');

        if ($option !== null) {
            $tenant = Tenant::withoutGlobalScopes()
                ->where('slug', $option)
                ->orWhere('id', $option)
                ->first();

            if ($tenant === null) {
                $this->error("No existe la organización «{$option}».");
            }

            return $tenant;
        }

        $tenants = Tenant::withoutGlobalScopes()->get();

        if ($tenants->count() === 1) {
            return $tenants->first();
        }

        $this->error('Hay varias organizaciones. Indique cuál con --tenant='
            .$tenants->pluck('slug')->implode(', --tenant='));

        return null;
    }

    private function resolvePlant(Tenant $tenant): ?Plant
    {
        $option = $this->option('plant');

        $plants = Plant::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();

        if ($option !== null) {
            $plant = $plants->firstWhere('code', $option) ?? $plants->firstWhere('id', $option);

            if ($plant === null) {
                $this->error("No existe la planta «{$option}» en {$tenant->name}.");
            }

            return $plant;
        }

        if ($plants->count() === 1) {
            return $plants->first();
        }

        $this->error($plants->isEmpty()
            ? "La organización «{$tenant->name}» no tiene plantas."
            : 'Hay varias plantas. Indique cuál con --plant='.$plants->pluck('code')->implode(', --plant='));

        return null;
    }
}
