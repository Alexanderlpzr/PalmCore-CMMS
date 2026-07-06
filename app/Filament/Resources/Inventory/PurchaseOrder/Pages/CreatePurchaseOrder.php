<?php

namespace App\Filament\Resources\Inventory\PurchaseOrder\Pages;

use App\Domain\Inventory\Services\PurchaseOrderService;
use App\Filament\Resources\Inventory\PurchaseOrder\PurchaseOrderResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $lines = $data['lines'] ?? [];
        unset($data['lines']);

        return app(PurchaseOrderService::class)->create(
            array_merge($data, ['tenant_id' => Filament::getTenant()->id]),
            $lines,
            auth()->user(),
        );
    }
}
