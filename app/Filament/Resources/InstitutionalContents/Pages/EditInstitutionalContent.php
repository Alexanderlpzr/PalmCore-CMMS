<?php

namespace App\Filament\Resources\InstitutionalContents\Pages;

use App\Filament\Resources\InstitutionalContents\InstitutionalContentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;

class EditInstitutionalContent extends EditRecord
{
    protected static string $resource = InstitutionalContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Flush after save ensures pivot (tenant) changes also invalidate the cache,
        // since BelongsToMany sync doesn't trigger the model observer.
        Cache::tags(['institutional-content'])->flush();
    }
}
