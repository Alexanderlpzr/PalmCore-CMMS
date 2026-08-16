<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Pages;

use App\Domain\Maintenance\Enums\WorkOrderAttachmentType;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource;
use App\Models\WorkOrder;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateWorkOrder extends CreateRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // El soporte de cotización no es un campo de la OT: es un adjunto. Se saca del
        // payload antes de crearla para no colarlo en un `create()` masivo que no lo
        // espera.
        $quotePath = $data['quote_document'] ?? null;
        $quoteName = $data['quote_document_name'] ?? null;
        unset($data['quote_document'], $data['quote_document_name']);

        $workOrder = app(WorkOrderService::class)->create(
            array_merge($data, ['tenant_id' => Filament::getTenant()->id]),
            auth()->user()
        );

        $this->attachQuote($workOrder, $quotePath, $quoteName);

        return $workOrder;
    }

    /**
     * Se engancha aquí y no dentro de `WorkOrderService::create()` a propósito: la OT
     * nace por ocho caminos —API, móvil, conversión de solicitud, generador de
     * preventivos— y la cotización solo tiene sentido en el que teclea una persona.
     */
    private function attachQuote(WorkOrder $workOrder, mixed $path, mixed $originalName = null): void
    {
        if (blank($path) || ! is_string($path)) {
            return;
        }

        $disk = Storage::disk(private_files_disk());

        if (! $disk->exists($path)) {
            return;
        }

        // `storeFileNamesIn` entrega una cadena o un mapa ruta => nombre, según si el
        // campo admite varios archivos.
        if (is_array($originalName)) {
            $originalName = $originalName[$path] ?? reset($originalName);
        }

        $workOrder->attachments()->create([
            'tenant_id' => $workOrder->tenant_id,
            'attachment_type' => WorkOrderAttachmentType::Quote,
            'file_path' => $path,
            'file_name' => filled($originalName) ? $originalName : basename($path),
            'file_size' => $disk->size($path),
            'mime_type' => $disk->mimeType($path) ?: 'application/pdf',
            'caption' => 'Soporte de cotización',
            'uploaded_by' => auth()->id(),
        ]);
    }
}
