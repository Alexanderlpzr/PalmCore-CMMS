<?php

namespace App\Filament\Resources\Commercial\KernelTollLoad\Pages;

use App\Filament\Resources\Commercial\KernelTollLoad\KernelTollLoadResource;
use App\Filament\Widgets\Lists\KernelKpis;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKernelTollLoads extends ManageRecords
{
    protected static string $resource = KernelTollLoadResource::class;

    protected function getHeaderWidgets(): array
    {
        return [KernelKpis::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar cargue')
                ->mutateDataUsing(function (array $data): array {
                    $data['created_by'] = auth()->id();

                    return $data;
                }),
        ];
    }
}
