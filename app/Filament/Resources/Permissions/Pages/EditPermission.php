<?php

namespace App\Filament\Resources\Permissions\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Permissions\PermissionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPermission extends EditRecord
{
    use HasBackAction;

    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            $this->getBackAction(),
        ];
    }
}
