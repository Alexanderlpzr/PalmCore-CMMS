<?php

namespace App\Filament\Resources\MaintenanceBudgets\Pages;

use App\Filament\Resources\MaintenanceBudgets\MaintenanceBudgetResource;
use App\Models\MaintenanceBudget;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMaintenanceBudget extends CreateRecord
{
    protected static string $resource = MaintenanceBudgetResource::class;

    /**
     * Fijar el presupuesto de un mes que ya lo tiene lo corrige, no crea una
     * segunda fila: el unique(plant, año, mes) lo garantiza en la base, y aquí se
     * hace explícito para que la pantalla no falle con un error de duplicado.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $data['tenant_id'] = Filament::getTenant()->id;
        $data['created_by'] = auth()->id();

        return MaintenanceBudget::updateOrCreate(
            [
                'tenant_id' => $data['tenant_id'],
                'plant_id' => $data['plant_id'],
                'year' => $data['year'],
                'month' => $data['month'],
            ],
            $data,
        );
    }
}
