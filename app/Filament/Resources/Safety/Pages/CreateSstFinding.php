<?php

namespace App\Filament\Resources\Safety\Pages;

use App\Domain\Safety\Services\SafetyFindingService;
use App\Filament\Resources\Safety\SstFindingResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSstFinding extends CreateRecord
{
    protected static string $resource = SstFindingResource::class;

    protected static ?string $title = 'Reportar hallazgo';

    /** Nace con una parte por área responsable; la de mantenimiento, con su reporte de novedad. */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            return app(SafetyFindingService::class)->create((string) filament()->getTenant()->getKey(), $data, $user);
        } catch (\DomainException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
            $this->halt();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Hallazgo reportado: ya les llegó a las áreas responsables';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
