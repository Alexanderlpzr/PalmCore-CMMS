<?php

namespace App\Filament\Resources\Downtime\Pages;

use App\Domain\Assets\Services\DowntimeService;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\Downtime\DowntimeEventResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditDowntimeEvent extends EditRecord
{
    protected static string $resource = DowntimeEventResource::class;

    /**
     * La corrección pasa por el servicio, igual que el registro.
     *
     * Cambiar una hora a mano puede hacer que el paro se cruce con otro del
     * mismo equipo, y entonces la misma hora perdida se cobraría dos veces en
     * los indicadores del mes. El servicio lo comprueba —ignorando este propio
     * paro— y recalcula la duración a partir de las fechas.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Un select vacío llega como '' y no como null: sin esto, un paro de
        // planta se leería como «un equipo cuyo id es la cadena vacía».
        $clean = array_filter($data, fn ($value): bool => $value !== '' && $value !== null);

        try {
            return app(DowntimeService::class)->update(
                $record,
                [...$clean, 'tenant_id' => Filament::getTenant()->id],
                auth()->user(),
            );
        } catch (BusinessRuleException $e) {
            // El servicio ya explicó en español qué regla se rompió —un solape,
            // casi siempre—. Una pantalla de error 500 no le dice nada al
            // supervisor que está corrigiendo la planilla del turno.
            Notification::make()->title($e->getMessage())->danger()->send();

            $this->halt();
        }
    }
}
