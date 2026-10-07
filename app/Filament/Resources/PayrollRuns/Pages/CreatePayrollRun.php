<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Filament\Resources\PayrollRuns\Schemas\PayrollRunForm;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollRun extends CreateRecord
{
    protected static string $resource = PayrollRunResource::class;

    /**
     * Si quien crea la nómina borró la ventana de horas, vuelve la del día de corte: con
     * corte, una nómina sin ventana tomaría las horas del mes calendario.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['hours_from'] ?? null) && blank($data['hours_to'] ?? null)) {
            [$data['hours_from'], $data['hours_to']] = PayrollRunForm::defaultHoursWindow($data['period_start'] ?? null) ?? [null, null];
        }

        return $data;
    }
}
