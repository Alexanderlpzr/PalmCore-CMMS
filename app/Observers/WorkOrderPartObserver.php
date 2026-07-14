<?php

namespace App\Observers;

use App\Models\SparePart;
use App\Models\WorkOrderPart;

/**
 * M6 — el enlace al inventario manda sobre el código escrito a mano.
 *
 * Cuando el renglón apunta al maestro de repuestos, `part_code` deja de ser un
 * campo editable y pasa a ser el **snapshot** del código del maestro en el momento
 * del consumo: si mañana alguien renombra RD-0417, la OT sigue diciendo lo que el
 * almacén decía el día que el repuesto salió. Es la misma regla que ya congela
 * tarifas, tareas y costos.
 *
 * Sin enlace, el texto libre se respeta tal cual: no todo repuesto vive en el
 * maestro, y forzarlo ahí sería obligar a inventar un código.
 */
class WorkOrderPartObserver
{
    public function saving(WorkOrderPart $part): void
    {
        if ($part->spare_part_id === null) {
            return;
        }

        if (! $part->isDirty('spare_part_id') && $part->part_code !== null) {
            return;
        }

        $sparePart = SparePart::withoutGlobalScopes()->find($part->spare_part_id);

        if ($sparePart === null) {
            return;
        }

        $part->part_code = $sparePart->code;
        $part->description ??= $sparePart->name;
    }
}
