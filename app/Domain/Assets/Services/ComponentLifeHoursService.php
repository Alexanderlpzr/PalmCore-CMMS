<?php

namespace App\Domain\Assets\Services;

use App\Domain\Assets\Enums\ComponentStatus;
use App\Models\Equipment;
use App\Models\EquipmentComponent;

/**
 * Las horas de vida de un componente: cuánto ha trabajado desde que se instaló —o
 * desde la última vez que alguien corrigió el número a mano.
 *
 * El bug que este servicio existe para no repetir: `worked_hours` era un campo de
 * texto libre que alguien escribía una vez al crear la pieza y quedaba congelado
 * para siempre, mientras el horómetro del equipo seguía acumulando horas reales
 * sin que nadie las trasladara al componente. Aquí `worked_hours` se comporta
 * como el acumulado del equipo mismo: nunca se vuelve a pedir desde cero, se hace
 * avanzar por la diferencia desde la última vez que se supo la verdad.
 */
class ComponentLifeHoursService
{
    /**
     * Al crear el componente: fija cuánto llevaba trabajado ANTES de entrar a
     * Fronda —si alguien lo sabe— y ancla el reloj al acumulado de hoy. Desde
     * aquí, cada lectura nueva del horómetro del equipo suma horas a esta pieza.
     */
    public function initializeBaseline(EquipmentComponent $component, ?float $startingHours = null): void
    {
        $component->update([
            'worked_hours' => $startingHours ?? 0,
            'meter_reading_baseline' => (float) $component->equipment->accumulated_meter_reading,
        ]);
    }

    /**
     * Corrección manual —o el reemplazo físico de la pieza—: «a partir de ahora,
     * esto es lo cierto». El mismo significado que resetear un horómetro: el
     * valor que se escribe es el nuevo punto de partida, no un ajuste sobre el
     * anterior.
     */
    public function rebaseline(EquipmentComponent $component, float $newWorkedHours): void
    {
        $component->update([
            'worked_hours' => $newWorkedHours,
            'meter_reading_baseline' => (float) $component->equipment->accumulated_meter_reading,
        ]);
    }

    /**
     * Limpia el rastro: «no se sabe» cuántas horas lleva. El reloj deja de correr
     * hasta que alguien vuelva a fijar un punto de partida.
     */
    public function clear(EquipmentComponent $component): void
    {
        $component->update(['worked_hours' => null, 'meter_reading_baseline' => null]);
    }

    /**
     * Se llama cada vez que llega una lectura nueva del horómetro del equipo.
     * Adelanta `worked_hours` de cada pieza todavía en servicio exactamente lo
     * que avanzó el acumulado desde su propio punto de partida —y mueve ese punto
     * de partida a hoy, para que la próxima lectura no cuente lo mismo dos veces.
     *
     * Una pieza reemplazada, retirada o fallada no avanza: su reloj se congeló el
     * día que salió de servicio, y ese número congelado es historia útil (cuántas
     * horas aguantó antes de fallar), no un dato que haya que seguir moviendo.
     *
     * @return int cuántos componentes se actualizaron
     */
    public function syncForEquipment(Equipment $equipment): int
    {
        $accumulated = (float) $equipment->accumulated_meter_reading;

        $components = EquipmentComponent::where('equipment_id', $equipment->id)
            ->whereIn('status', [ComponentStatus::Active->value, ComponentStatus::Degraded->value])
            ->whereNotNull('meter_reading_baseline')
            ->get();

        $updated = 0;

        foreach ($components as $component) {
            $delta = $accumulated - (float) $component->meter_reading_baseline;

            // El acumulado del equipo nunca retrocede, así que un delta negativo
            // solo puede ser un dato corrupto de otro lado — no se le resta vida a
            // la pieza por un error ajeno.
            if ($delta <= 0) {
                continue;
            }

            $component->update([
                'worked_hours' => round((float) $component->worked_hours + $delta, 2),
                'meter_reading_baseline' => $accumulated,
            ]);

            $updated++;
        }

        return $updated;
    }
}
