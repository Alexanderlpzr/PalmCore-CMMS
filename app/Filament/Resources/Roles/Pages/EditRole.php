<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    use HasBackAction;

    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            $this->getBackAction(),
        ];
    }
}
