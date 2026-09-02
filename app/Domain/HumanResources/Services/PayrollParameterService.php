<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Exceptions\PayrollParameterException;
use App\Models\PayrollParameterVersion;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Responde «cuánto valía este parámetro en esta fecha», que es la única pregunta que la
 * liquidación tiene derecho a hacer.
 *
 * Nunca «cuánto vale hoy». Esa diferencia es todo el módulo: una nómina de enero que se
 * reabre en abril debe seguir liquidando con lo que regía en enero, porque eso es lo que
 * se pagó, lo que se aportó y lo que habría que defender ante una demanda.
 *
 * Escribir un valor nuevo tampoco es un UPDATE. `setValue` cierra el tramo abierto el día
 * anterior y abre uno nuevo, de modo que la serie completa queda como un histórico de
 * precios y ninguna liquidación pasada cambia de resultado.
 */
class PayrollParameterService
{
    /** Memo por petición: la liquidación de 48 empleados pide el mismo divisor 48 veces. */
    private array $cache = [];

    // ── Lectura ───────────────────────────────────────────────────────────────

    /**
     * El valor que regía en esa fecha.
     *
     * @throws PayrollParameterException cuando no hay vigencia cargada. Falla en vez de
     *                                   asumir un valor por omisión: liquidar con un
     *                                   recargo inventado es peor que no liquidar.
     */
    public function valueOn(PayrollParameter $parameter, CarbonInterface $date, string $tenantId): float
    {
        $key = $tenantId.'|'.$parameter->value.'|'.$date->toDateString();

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $value = PayrollParameterVersion::query()
            ->forTenant($tenantId)
            ->where('key', $parameter->value)
            ->effectiveOn($date)
            ->orderByDesc('effective_from')
            ->value('value');

        if ($value === null) {
            throw PayrollParameterException::missing($parameter, $date);
        }

        return $this->cache[$key] = (float) $value;
    }

    /**
     * Todos los parámetros vigentes en esa fecha, listos para pasar al clasificador de
     * horas y a la liquidación sin volver a consultar.
     *
     * @return array<string, float>
     */
    public function allOn(CarbonInterface $date, string $tenantId): array
    {
        $rows = PayrollParameterVersion::query()
            ->forTenant($tenantId)
            ->effectiveOn($date)
            ->orderBy('effective_from')
            ->get(['key', 'value']);

        return $rows
            ->mapWithKeys(fn (PayrollParameterVersion $row): array => [$row->key => (float) $row->value])
            ->all();
    }

    /** Las claves que aún no tienen vigencia en esa fecha. Lo que hay que cargar antes de liquidar. */
    public function missingOn(CarbonInterface $date, string $tenantId): array
    {
        $present = array_keys($this->allOn($date, $tenantId));

        return array_values(array_diff(
            array_column(PayrollParameter::cases(), 'value'),
            $present,
        ));
    }

    // ── Escritura ─────────────────────────────────────────────────────────────

    /**
     * Abre una vigencia nueva y cierra la anterior el día antes.
     *
     * Reescribir la misma fecha de inicio sí es un UPDATE, y es correcto: corregir hoy un
     * número que se cargó mal hoy no altera ninguna nómina, porque todavía no se ha
     * liquidado nada con él. Lo que no se permite es abrir un tramo por detrás de uno que
     * ya existe.
     */
    public function setValue(
        PayrollParameter $parameter,
        float $value,
        CarbonInterface $from,
        string $tenantId,
        ?string $userId = null,
        ?string $notes = null,
    ): PayrollParameterVersion {
        $this->guardRange($parameter, $value);

        return DB::transaction(function () use ($parameter, $value, $from, $tenantId, $userId, $notes) {
            $laterExists = PayrollParameterVersion::query()
                ->forTenant($tenantId)
                ->where('key', $parameter->value)
                ->whereDate('effective_from', '>', $from)
                ->exists();

            if ($laterExists) {
                throw PayrollParameterException::wouldRewriteHistory($parameter, $from);
            }

            $sameDay = PayrollParameterVersion::query()
                ->forTenant($tenantId)
                ->where('key', $parameter->value)
                ->whereDate('effective_from', '=', $from)
                ->first();

            if ($sameDay) {
                $sameDay->update(['value' => $value, 'notes' => $notes, 'created_by' => $userId]);

                $this->cache = [];

                return $sameDay;
            }

            PayrollParameterVersion::query()
                ->forTenant($tenantId)
                ->where('key', $parameter->value)
                ->open()
                ->update(['effective_to' => $from->copy()->subDay()->toDateString()]);

            $this->cache = [];

            return PayrollParameterVersion::create([
                'tenant_id' => $tenantId,
                'key' => $parameter->value,
                'value' => $value,
                'effective_from' => $from->toDateString(),
                'effective_to' => null,
                'notes' => $notes,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Carga la primera vigencia de todos los parámetros con los valores que hoy aplica el
     * libro de Excel. No pisa lo que ya exista.
     */
    public function seedDefaults(string $tenantId, CarbonInterface $from, ?string $userId = null): int
    {
        $created = 0;

        foreach (PayrollParameter::cases() as $parameter) {
            $exists = PayrollParameterVersion::query()
                ->forTenant($tenantId)
                ->where('key', $parameter->value)
                ->exists();

            if ($exists) {
                continue;
            }

            PayrollParameterVersion::create([
                'tenant_id' => $tenantId,
                'key' => $parameter->value,
                'value' => $parameter->seedValue(),
                'effective_from' => $from->toDateString(),
                'effective_to' => null,
                'notes' => 'Valor inicial tomado del libro de nómina vigente.',
                'created_by' => $userId,
            ]);

            $created++;
        }

        $this->cache = [];

        return $created;
    }

    // ── Coherencia ────────────────────────────────────────────────────────────

    /**
     * Los factores de domingo que no cuadran con la base dominical vigente.
     *
     * No corrige: avisa. El nocturno dominical debería ser la base más 0,35, la extra
     * dominical diurna la base más 1,25 y la nocturna la base más 1,75. Si alguien sube
     * la base del 80% al 90% y olvida los tres derivados, esto lo dice en pantalla en vez
     * de dejar que la planta entera se liquide mal durante un mes.
     *
     * @return array<int, array{parameter: PayrollParameter, current: float, expected: float}>
     */
    public function inconsistentSundayFactors(CarbonInterface $date, string $tenantId): array
    {
        $values = $this->allOn($date, $tenantId);
        $base = $values[PayrollParameter::SurchargeSunday->value] ?? null;

        if ($base === null) {
            return [];
        }

        $problems = [];

        foreach (PayrollParameter::cases() as $parameter) {
            $rule = $parameter->derivedFromSunday();

            if ($rule === null || ! array_key_exists($parameter->value, $values)) {
                continue;
            }

            $expected = round($base + $rule['extra'], 4);
            $current = round($values[$parameter->value], 4);

            if (abs($expected - $current) > 0.0001) {
                $problems[] = ['parameter' => $parameter, 'current' => $current, 'expected' => $expected];
            }
        }

        return $problems;
    }

    private function guardRange(PayrollParameter $parameter, float $value): void
    {
        if ($value < 0 || $value > $parameter->unit()->maxValue()) {
            throw PayrollParameterException::outOfRange($parameter, $value);
        }
    }
}
