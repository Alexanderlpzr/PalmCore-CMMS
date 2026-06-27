<?php

namespace App\Filament\Resources\CarouselSlides\Pages;

use App\Filament\Resources\CarouselSlides\CarouselSlideResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCarouselSlide extends EditRecord
{
    protected static string $resource = CarouselSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
