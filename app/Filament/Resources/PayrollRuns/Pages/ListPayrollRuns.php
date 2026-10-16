<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Filament\Widgets\Lists\PayrollRunKpis;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayrollRuns extends ListRecords
{
    use HasBackAction;

    protected static string $resource = PayrollRunResource::class;

    /** Lo que dice la tabla en cuatro tarjetas; cada una filtra la lista al tocarla. */
    protected function getHeaderWidgets(): array
    {
        return [PayrollRunKpis::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getHistoryBackAction(),
            CreateAction::make()->label('Nuevo período'),
        ];
    }
}
