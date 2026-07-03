<?php

namespace App\Filament\Resources\Inventory\SparePart\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Inventory\SparePart\SparePartResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSparePart extends ViewRecord
{
    use HasBackAction;

    protected static string $resource = SparePartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
            $this->getBackAction(),
        ];
    }
}
