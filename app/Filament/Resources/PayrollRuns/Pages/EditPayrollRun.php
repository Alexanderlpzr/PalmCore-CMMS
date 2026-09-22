<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPayrollRun extends EditRecord
{
    use HasBackAction;

    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getBackAction(),
            DeleteAction::make(),
        ];
    }
}
