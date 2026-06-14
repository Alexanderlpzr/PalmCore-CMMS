<?php

namespace App\Filament\Resources\Inventory\Warehouse\Pages;

use App\Domain\Inventory\Services\WarehouseService;
use App\Filament\Resources\Inventory\Warehouse\WarehouseResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWarehouse extends CreateRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(WarehouseService::class)->create(
            array_merge($data, ['tenant_id' => Filament::getTenant()->id]),
            auth()->user()
        );
    }
}
