<?php

namespace App\Filament\Resources\Inventory\InventoryCount\Pages;

use App\Domain\Inventory\Enums\InventoryCountScope;
use App\Domain\Inventory\Services\InventoryCountService;
use App\Filament\Resources\Inventory\InventoryCount\InventoryCountResource;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateInventoryCount extends CreateRecord
{
    protected static string $resource = InventoryCountResource::class;

    protected static ?string $title = 'Abrir conteo';

    protected static bool $canCreateAnother = false;

    /** El conteo nace con sus repuestos y lo que el sistema decía de cada uno al empezar. */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            return app(InventoryCountService::class)->start(
                Warehouse::query()->findOrFail($data['warehouse_id']),
                InventoryCountScope::from($data['scope']),
                $user,
                $data['location_filter'] ?? null,
                $data['notes'] ?? null,
            );
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
