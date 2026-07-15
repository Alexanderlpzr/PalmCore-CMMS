<?php

namespace App\Filament\Platform\Resources\LoginBackgroundImages\Pages;

use App\Filament\Platform\Resources\LoginBackgroundImages\LoginBackgroundImageResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditLoginBackgroundImage extends EditRecord
{
    protected static string $resource = LoginBackgroundImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
