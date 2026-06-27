<?php

namespace App\Filament\Resources\CarouselSlides\Pages;

use App\Filament\Resources\CarouselSlides\CarouselSlideResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCarouselSlides extends ListRecords
{
    protected static string $resource = CarouselSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
