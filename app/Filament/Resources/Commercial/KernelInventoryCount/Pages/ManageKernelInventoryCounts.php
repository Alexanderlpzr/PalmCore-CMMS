<?php

namespace App\Filament\Resources\Commercial\KernelInventoryCount\Pages;

use App\Filament\Resources\Commercial\KernelInventoryCount\KernelInventoryCountResource;
use App\Filament\Widgets\Lists\KernelKpis;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKernelInventoryCounts extends ManageRecords
{
    protected static string $resource = KernelInventoryCountResource::class;

    protected function getHeaderWidgets(): array
    {
        return [KernelKpis::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar corte')
                ->mutateDataUsing(function (array $data): array {
                    $data['recorded_by'] = auth()->id();

                    return $data;
                }),
        ];
    }
}
