<?php

namespace App\Filament\Resources\Safety\Pages;

use App\Domain\Safety\Enums\FindingPriority;
use App\Filament\Resources\Safety\SstFindingResource;
use App\Models\SstFinding;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\EditRecord;

class EditSstFinding extends EditRecord
{
    protected static string $resource = SstFindingResource::class;

    protected static ?string $title = 'Corregir datos del hallazgo';

    /** El plazo sigue a la fecha de reporte y la prioridad; si cambia, puede volver a avisarse. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var SstFinding $finding */
        $finding = $this->getRecord();

        // El origen no se corrige: lo fijó la cuenta que lo reportó.
        unset($data['origin']);

        $dueOn = CarbonImmutable::parse($data['reported_on'])->addDays(FindingPriority::from($data['priority'])->days());

        if (! $finding->due_on->isSameDay($dueOn)) {
            $data['overdue_notified_at'] = null;
        }

        return [...$data, 'due_on' => $dueOn->toDateString()];
    }

    protected function getHeaderActions(): array
    {
        return [SstFindingResource::deleteAction()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
