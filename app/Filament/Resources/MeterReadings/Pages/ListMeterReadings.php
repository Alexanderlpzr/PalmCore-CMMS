<?php

namespace App\Filament\Resources\MeterReadings\Pages;

use App\Domain\Maintenance\Enums\MeterReadingUnit;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Filament\Resources\MeterReadings\MeterReadingResource;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ListMeterReadings extends ListRecords
{
    protected static string $resource = MeterReadingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar lectura')
                ->visible(fn (): bool => auth()->user()->can('create', EquipmentMeterReading::class))
                // La lectura pasa por el servicio: es él quien calcula el delta,
                // detecta el cambio de dial y mueve el acumulado, que es el único
                // número contra el que un plan por horómetro puede programarse.
                ->using(function (array $data): Model {
                    $equipment = Equipment::findOrFail($data['equipment_id']);

                    try {
                        return app(EquipmentMeterReadingService::class)->record(
                            equipment: $equipment,
                            readingValue: (float) $data['reading_value'],
                            recordedBy: auth()->user(),
                            unit: $equipment->meter_unit ?? MeterReadingUnit::Hours,
                            recordedAt: isset($data['recorded_at']) ? Carbon::parse($data['recorded_at']) : null,
                            notes: $data['notes'] ?? null,
                        );
                    } catch (\Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        throw $e;
                    }
                }),
        ];
    }
}
