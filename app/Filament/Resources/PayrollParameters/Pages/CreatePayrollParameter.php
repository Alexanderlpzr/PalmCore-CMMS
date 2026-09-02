<?php

namespace App\Filament\Resources\PayrollParameters\Pages;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Exceptions\PayrollParameterException;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Filament\Resources\PayrollParameters\PayrollParameterResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CreatePayrollParameter extends CreateRecord
{
    protected static string $resource = PayrollParameterResource::class;

    /**
     * Crear un parámetro no es insertar una fila: es cerrar el tramo anterior y abrir uno
     * nuevo. Por eso pasa por el servicio y no por el `create` de Filament, que dejaría
     * dos vigencias abiertas a la vez y ninguna forma de saber cuál manda.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $parameter = PayrollParameter::from($data['key']);

        try {
            return app(PayrollParameterService::class)->setValue(
                parameter: $parameter,
                value: (float) $data['value'],
                from: Carbon::parse($data['effective_from']),
                tenantId: Filament::getTenant()->id,
                userId: auth()->id(),
                notes: $data['notes'] ?? null,
            );
        } catch (PayrollParameterException $e) {
            // Como error de formulario y no como excepción: es una decisión del usuario
            // que hay que explicarle, no una falla del sistema.
            throw ValidationException::withMessages(['data.effective_from' => $e->getMessage()]);
        }
    }

    protected function afterCreate(): void
    {
        $this->warnAboutInconsistentSundayFactors();
    }

    /**
     * Si acaban de mover la base dominical, los tres factores derivados quedaron
     * desactualizados. Avisar aquí es la diferencia entre corregirlo en un minuto y
     * liquidar la planta entera mal durante un mes.
     */
    private function warnAboutInconsistentSundayFactors(): void
    {
        $problems = app(PayrollParameterService::class)->inconsistentSundayFactors(
            Carbon::parse($this->record->effective_from),
            Filament::getTenant()->id,
        );

        if ($problems === []) {
            return;
        }

        $lines = array_map(
            fn (array $p): string => sprintf(
                '%s: hoy %s, debería ser %s',
                $p['parameter']->label(),
                number_format($p['current'], 2, ',', '.'),
                number_format($p['expected'], 2, ',', '.'),
            ),
            $problems,
        );

        Notification::make()
            ->title('Faltan factores de domingo por actualizar')
            ->body(implode('. ', $lines).'.')
            ->warning()
            ->persistent()
            ->send();
    }
}
