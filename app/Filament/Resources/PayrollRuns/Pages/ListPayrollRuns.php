<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayrollRuns extends ListRecords
{
    use HasBackAction;

    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getHistoryBackAction(),
            CreateAction::make()->label('Nuevo período'),
        ];
    }
}
