<?php

namespace App\Filament\Resources\Inventory\SparePart\Pages;

use App\Domain\Inventory\Services\SparePartService;
use App\Filament\Resources\Inventory\SparePart\SparePartResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSparePart extends CreateRecord
{
    protected static string $resource = SparePartResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SparePartService::class)->create(
            array_merge($data, ['tenant_id' => Filament::getTenant()->id]),
            auth()->user()
        );
    }
}
