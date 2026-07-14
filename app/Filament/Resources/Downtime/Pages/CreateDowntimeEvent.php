<?php

namespace App\Filament\Resources\Downtime\Pages;

use App\Domain\Assets\Services\DowntimeService;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\Downtime\DowntimeEventResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDowntimeEvent extends CreateRecord
{
    protected static string $resource = DowntimeEventResource::class;

    /**
     * El registro pasa por el servicio, no por `Model::create()`.
     *
     * Ahí viven las reglas que hacen que las horas perdidas signifiquen algo: que
     * dos paros del mismo equipo no se pisen (y sumen sus horas dos veces), que el
     * equipo pertenezca a este tenant, y que el Tipo I decida si el paro fue
     * programado. Crear el modelo directo desde Filament se las saltaría todas.
     */
    protected function handleRecordCreation(array $data): Model
    {
        // Un select vacío llega como '' y no como null: sin esto, un paro de planta
        // se leería como «un equipo cuyo id es la cadena vacía» y el servicio lo
        // rechazaría por no existir en el tenant.
        $clean = array_filter($data, fn ($value): bool => $value !== '' && $value !== null);

        try {
            return app(DowntimeService::class)->register(
                [...$clean, 'tenant_id' => Filament::getTenant()->id],
                auth()->user(),
            );
        } catch (BusinessRuleException $e) {
            // El servicio ya explicó en español por qué el paro no se puede
            // registrar (un solape, casi siempre). Aquí solo se muestra: una
            // pantalla de error 500 no le dice nada al supervisor de turno.
            Notification::make()->title($e->getMessage())->danger()->send();

            $this->halt();
        }
    }
}
