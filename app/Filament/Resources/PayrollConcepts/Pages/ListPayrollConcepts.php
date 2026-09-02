<?php

namespace App\Filament\Resources\PayrollConcepts\Pages;

use App\Filament\Resources\PayrollConcepts\PayrollConceptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayrollConcepts extends ListRecords
{
    protected static string $resource = PayrollConceptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
