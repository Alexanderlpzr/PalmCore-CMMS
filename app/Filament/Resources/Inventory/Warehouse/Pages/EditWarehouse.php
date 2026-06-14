<?php

namespace App\Filament\Resources\Inventory\Warehouse\Pages;

use App\Domain\Inventory\Services\WarehouseService;
use App\Filament\Resources\Inventory\Warehouse\WarehouseResource;
use App\Models\Warehouse;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditWarehouse extends EditRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Warehouse $record */
        return app(WarehouseService::class)->update($record, $data, auth()->user());
    }
}
