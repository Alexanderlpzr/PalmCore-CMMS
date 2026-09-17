<?php

namespace App\Console\Commands;

use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga el histórico mensual de consumo eléctrico que la planta lleva en Excel.
 *
 * Se invoca a mano; nunca entra en un despliegue. El archivo es el CSV exportado de la
 * hoja «ENERGIA - KWh/RFF», con una fila por mes y las tres fuentes en columnas. Es
 * idempotente: se puede reexportar y volver a correr sin duplicar nada.
 *
 * Los meses cargados así quedan marcados con `energy_is_imported`, y el cierre mensual
 * los respeta. Sin esa marca, el primer recálculo los pondría en cero: sus lecturas
 * diarias nunca existieron, viven solo como total del mes.
 *
 * La columna `rff_toneladas` es opcional y trae la fila RFF/MES de la misma hoja. Es el
 * denominador de KWh/RFF, y sin ella el indicador principal no existe. Se guarda marcada
 * como manual, por la misma razón: son totales del mes, sin los días detrás.
 *
 * El corolario hay que tenerlo presente: un mes con la fruta importada ya no la
 * recalcula desde el calendario de producción. Si más adelante se carga la producción
 * diaria de esos meses y se quiere que mande, hay que limpiarles
 * `processed_tons_is_manual`.
 *
 * Tres reglas que el CSV ya trae aplicadas y conviene no deshacer:
 *
 *   - Una celda vacía entra como NULL, no como cero. En 2025 hay cinco meses sin dato
 *     de turbina, y cero kWh de turbina afirma que la planta funcionó a diésel — que es
 *     justo lo que no sabemos de esos meses.
 *   - Los meses sin ninguna cifra no se cargan. En la hoja dan cero porque suman celdas
 *     vacías, no porque la planta no consumiera.
 *   - Agosto de 2026 lleva la turbina recalculada desde el acumulado del contador
 *     (63.454 kWh) y no la de la hoja (67.160). Dos fórmulas de delta restaban la fila
 *     equivocada y contaron dos veces el mismo tramo.
 */
class ImportEnergyHistory extends Command
{
    protected $signature = 'energy:import-history
        {file : Ruta al CSV con anio,mes y las columnas que se quieran cargar: kwh_red,kwh_planta,kwh_turbina,rff_toneladas,cambios_energia,horas_planta,galones}
        {--tenant= : Slug o ID de la organización}
        {--plant= : Código o ID de la planta (por defecto, la única que haya)}
        {--dry-run : Muestra lo que se cargaría sin escribir nada}';

    protected $description = 'Importa el histórico mensual de consumo de energía desde el CSV de la planilla.';

    public function handle(): int
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

        $rows = $this->readCsv($file);

        if ($rows === null) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $updated = 0;

        $write = function () use ($rows, $plant, &$created, &$updated): void {
            foreach ($rows as $row) {
                $existing = PlantMonthlyKpi::withoutGlobalScopes()
                    ->where('plant_id', $plant->id)
                    ->where('year', $row['anio'])
                    ->where('month', $row['mes'])
                    ->first();

                $payload = [
                    'tenant_id' => $plant->tenant_id,
                    'energy_is_imported' => true,
                    // Un mes importado no se «calculó» nunca: se cargó. Pero la
                    // columna es obligatoria y la fecha de carga es la respuesta
                    // honesta a «desde cuándo está este número aquí».
                    'calculated_at' => $existing?->calculated_at ?? now(),
                ];

                // Los kWh solo si el archivo trae esas columnas. Escribirlos siempre
                // significaba pisar con nulos un mes ya cargado cuando el CSV venía solo
                // con los renglones de la planta eléctrica.
                if ($row['trae_kwh']) {
                    $payload['kwh_grid'] = $row['red'];
                    $payload['kwh_genset'] = $row['planta'];
                    $payload['kwh_turbine'] = $row['turbina'];
                }

                // La fruta del mes viene de la misma hoja, y es el denominador sin el
                // cual KWh/RFF no existe. Va marcada como manual porque lo es: son
                // totales mensuales, sin los días detrás que el cierre suele sumar. Sin
                // esa marca, el primer recálculo los pondría en cero.
                if ($row['rff'] !== null) {
                    $payload['processed_tons'] = $row['rff'];
                    $payload['processed_tons_is_manual'] = true;
                }

                // Los tres renglones de la planta eléctrica, si la hoja los trae. Solo se
                // escriben los que vienen: una columna ausente no debe borrar lo que el
                // cierre ya calculó del horómetro.
                foreach (['cambios' => 'energy_switch_count', 'horas' => 'genset_hours', 'galones' => 'genset_fuel_gallons'] as $origen => $columna) {
                    if ($row[$origen] !== null) {
                        $payload[$columna] = $origen === 'cambios' ? (int) $row[$origen] : $row[$origen];
                    }
                }

                PlantMonthlyKpi::withoutGlobalScopes()->updateOrCreate(
                    ['plant_id' => $plant->id, 'year' => $row['anio'], 'month' => $row['mes']],
                    $payload,
                );

                $existing === null ? $created++ : $updated++;
            }
        };

        if ($dryRun) {
            $this->table(
                ['Año', 'Mes', 'Red', 'Planta', 'Turbina', 'RFF (t)', 'Cambios', 'Horas', 'Galones'],
                array_map(fn (array $r): array => [
                    $r['anio'], $r['mes'],
                    $r['red'] ?? '—', $r['planta'] ?? '—', $r['turbina'] ?? '—', $r['rff'] ?? '—',
                    $r['cambios'] ?? '—', $r['horas'] ?? '—', $r['galones'] ?? '—',
                ], $rows),
            );
            $this->info(count($rows).' meses se cargarían. Nada se escribió (--dry-run).');

            return self::SUCCESS;
        }

        DB::transaction($write);

        $this->info("Histórico de energía cargado: {$created} meses creados, {$updated} actualizados.");

        return self::SUCCESS;
    }

    /**
     * @return list<array{anio: int, mes: int, red: ?float, planta: ?float, turbina: ?float, rff: ?float, cambios: ?float, horas: ?float, galones: ?float, trae_kwh: bool}>|null
     */
    private function readCsv(string $file): ?array
    {
        $handle = fopen($file, 'r');

        if ($handle === false) {
            $this->error("No se pudo abrir el archivo: {$file}");

            return null;
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            $this->error('El archivo está vacío.');

            return null;
        }

        $header = array_map(fn (string $h): string => strtolower(trim($h)), $header);
        // Solo el período es obligatorio. Un CSV que trae únicamente los renglones de la
        // planta eléctrica —porque los kWh ya están cargados— es un CSV válido, y exigirle
        // columnas de kWh vacías era justamente la forma de borrarlos con nulos.
        $required = ['anio', 'mes'];
        $missing = array_diff($required, $header);

        if ($missing !== []) {
            fclose($handle);
            $this->error('Faltan columnas en el CSV: '.implode(', ', $missing));

            return null;
        }

        // Y al menos una columna de datos: un archivo con solo el período no carga nada, y
        // aceptarlo en silencio haría creer que se cargó.
        $conocidas = ['kwh_red', 'kwh_planta', 'kwh_turbina', 'rff_toneladas', 'cambios_energia', 'horas_planta', 'galones'];

        if (array_intersect($conocidas, $header) === []) {
            fclose($handle);
            $this->error('El CSV no trae ninguna columna de datos. Se esperaba alguna de: '.implode(', ', $conocidas));

            return null;
        }

        $index = array_flip($header);
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || $line === []) {
                continue;
            }

            $value = function (string $column) use ($line, $index): ?float {
                $raw = trim((string) ($line[$index[$column]] ?? ''));

                return $raw === '' ? null : (float) $raw;
            };

            $anio = (int) ($line[$index['anio']] ?? 0);
            $mes = (int) ($line[$index['mes']] ?? 0);

            if ($anio < 2000 || $mes < 1 || $mes > 12) {
                $this->warn("Fila ignorada, período inválido: {$anio}-{$mes}");

                continue;
            }

            // `array_key_exists` y no `??`: una columna ausente no es lo mismo que una
            // celda vacía. La ausente se deja como está en la base; la vacía se escribe
            // como «no se sabe».
            $opcional = fn (string $columna): ?float => array_key_exists($columna, $index) ? $value($columna) : null;

            $red = $opcional('kwh_red');
            $planta = $opcional('kwh_planta');
            $turbina = $opcional('kwh_turbina');
            $rff = $opcional('rff_toneladas');
            $cambios = $opcional('cambios_energia');
            $horas = $opcional('horas_planta');
            $galones = $opcional('galones');

            // Un mes sin ninguna cifra no es un mes de cero consumo: es un mes que
            // nadie cargó.
            if ($red === null && $planta === null && $turbina === null && $rff === null
                && $cambios === null && $horas === null && $galones === null) {
                continue;
            }

            $rows[] = [
                'anio' => $anio, 'mes' => $mes,
                'red' => $red, 'planta' => $planta, 'turbina' => $turbina, 'rff' => $rff,
                'cambios' => $cambios, 'horas' => $horas, 'galones' => $galones,
                'trae_kwh' => array_key_exists('kwh_red', $index)
                    || array_key_exists('kwh_planta', $index)
                    || array_key_exists('kwh_turbina', $index),
            ];
        }

        fclose($handle);

        return $rows;
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
                $this->error("No existe la organización: {$option}");
            }

            return $tenant;
        }

        $tenants = Tenant::withoutGlobalScopes()->get();

        if ($tenants->count() === 1) {
            return $tenants->first();
        }

        $this->error('Hay varias organizaciones; indica cuál con --tenant.');

        return null;
    }

    private function resolvePlant(Tenant $tenant): ?Plant
    {
        $option = $this->option('plant');
        $query = Plant::withoutGlobalScopes()->where('tenant_id', $tenant->id);

        if ($option !== null) {
            $plant = (clone $query)->where('code', $option)->orWhere('id', $option)->first();

            if ($plant === null) {
                $this->error("No existe la planta: {$option}");
            }

            return $plant;
        }

        $plants = $query->get();

        if ($plants->count() === 1) {
            return $plants->first();
        }

        $this->error('Hay varias plantas; indica cuál con --plant.');

        return null;
    }
}
