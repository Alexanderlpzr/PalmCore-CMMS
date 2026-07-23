<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Models\EquipmentPhoto;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class CreateEquipment extends CreateRecord
{
    protected static string $resource = EquipmentResource::class;

    private ?string $primaryPhotoTmpPath = null;

    /** @var list<array<string, mixed>> */
    private array $componentsData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->primaryPhotoTmpPath = $data['primary_photo_path'] ?? null;
        unset($data['primary_photo_path']);

        // Las piezas del equipo (repetidor «Componentes») se crean aparte en
        // afterCreate, cuando el equipo ya tiene id; no son atributos del equipo.
        $this->componentsData = $data['components'] ?? [];
        unset($data['components']);

        // Explicit tenant_id — the BelongsToTenant auto-fill (CurrentTenant) is not
        // reliably populated during Livewire actions; every create page in this app
        // sets it explicitly instead (see CreateWorkOrder, CreateMaintenanceRequest).
        $data['tenant_id'] = Filament::getTenant()->id;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Equipment $equipment */
        $equipment = $this->record;

        $this->createComponents($equipment);

        if (blank($this->primaryPhotoTmpPath)) {
            return;
        }

        $disk = Storage::disk(persistent_disk());

        if (! $disk->exists($this->primaryPhotoTmpPath)) {
            return;
        }

        $finalPath = 'equipment-photos/'.$equipment->tenant_id.'/'.$equipment->id.'/'.basename($this->primaryPhotoTmpPath);
        $disk->move($this->primaryPhotoTmpPath, $finalPath);

        EquipmentPhoto::create([
            'tenant_id' => $equipment->tenant_id,
            'equipment_id' => $equipment->id,
            'file_path' => $finalPath,
            'file_name' => basename($finalPath),
            'file_size' => $disk->size($finalPath),
            'mime_type' => $disk->mimeType($finalPath) ?: null,
            'is_primary' => true,
            'sort_order' => 0,
            'uploaded_by' => auth()->id(),
        ]);
    }

    private function createComponents(Equipment $equipment): void
    {
        foreach ($this->componentsData as $component) {
            if (blank($component['name'] ?? null)) {
                continue;
            }

            $attributes = Arr::only($component, [
                'name', 'code', 'part_number', 'criticality', 'status', 'useful_life_hours', 'unit_cost',
            ]);

            // Los numéricos vacíos llegan como '' desde el formulario; quedan en null.
            $attributes = array_filter($attributes, fn ($value): bool => $value !== '' && $value !== null);

            EquipmentComponent::create([
                'tenant_id' => $equipment->tenant_id,
                'equipment_id' => $equipment->id,
                ...$attributes,
            ]);
        }
    }
}
