<?php

namespace App\Filament\Resources\Holidays\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Holidays\HolidayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHolidays extends ListRecords
{
    use HasBackAction;

    protected static string $resource = HolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getHistoryBackAction(),
            CreateAction::make(),
        ];
    }
}
