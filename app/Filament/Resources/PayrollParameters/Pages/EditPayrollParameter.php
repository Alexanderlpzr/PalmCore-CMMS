<?php

namespace App\Filament\Resources\PayrollParameters\Pages;

use App\Filament\Resources\PayrollParameters\PayrollParameterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Solo el tramo abierto llega hasta aquí: la policy niega el resto, porque editar una
 * vigencia cerrada cambiaría nóminas ya liquidadas y pagadas.
 */
class EditPayrollParameter extends EditRecord
{
    protected static string $resource = PayrollParameterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
