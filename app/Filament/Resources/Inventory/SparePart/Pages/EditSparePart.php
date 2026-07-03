<?php

namespace App\Filament\Resources\Inventory\SparePart\Pages;

use App\Domain\Inventory\Services\SparePartService;
use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Inventory\SparePart\SparePartResource;
use App\Models\SparePart;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSparePart extends EditRecord
{
    use HasBackAction;

    protected static string $resource = SparePartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            $this->getBackAction(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var SparePart $record */
        return app(SparePartService::class)->update($record, $data, auth()->user());
    }
}
