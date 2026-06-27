<?php

namespace App\Filament\Platform\Resources\InstitutionalContents\Pages;

use App\Filament\Platform\Resources\InstitutionalContents\InstitutionalContentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstitutionalContents extends ListRecords
{
    protected static string $resource = InstitutionalContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
