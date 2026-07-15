<?php

namespace App\Filament\Platform\Resources\LoginBackgroundImages\Pages;

use App\Filament\Platform\Resources\LoginBackgroundImages\LoginBackgroundImageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLoginBackgroundImages extends ListRecords
{
    protected static string $resource = LoginBackgroundImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
