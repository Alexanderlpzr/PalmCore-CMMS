<?php

namespace App\Filament\Resources\MeterReadings\Pages;

use App\Domain\Maintenance\Enums\MeterReadingUnit;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Filament\Pages\WorkedHoursLog;
use App\Filament\Resources\MeterReadings\Actions\RegisterMeterReadingRoundAction;
use App\Filament\Resources\MeterReadings\Concerns\InteractsWithMeterMatrix;
use App\Filament\Resources\MeterReadings\MeterReadingResource;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * El centro de horómetros, todo en una página con pestañas de color en vez de
 * cuatro botones sueltos en el menú:
 *
 *   - Registro Diario (azul)   — la matriz de los equipos de lectura diaria.
 *   - Registro Semanal (ámbar) — la matriz de los de lectura semanal.
 *   - Historial (gris)         — la tabla de todas las lecturas registradas.
 *
 * Las dos primeras son captura estilo Excel (matriz equipo × fecha); la tercera
 * es la tabla del recurso de siempre. La consolidación de horas trabajadas
 * (mensual/anual) sigue en su propia pantalla, accesible desde el encabezado.
 */
class ListMeterReadings extends ListRecords
{
    use InteractsWithMeterMatrix;

    protected static string $resource = MeterReadingResource::class;

    protected string $view = 'filament.resources.meter-readings.list-hub';

    /** Pestaña activa: 'diario' | 'semanal' | 'historial'. */
    public string $tab = 'diario';

    public function mount(): void
    {
        parent::mount();
        $this->resetAnchor();
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;

        // Al volver a una matriz, la ventana se realinea al período actual (diario y
        // semanal usan pasos distintos, así que el ancla no puede compartirse tal cual).
        if ($tab !== 'historial') {
            $this->resetAnchor();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('workedHours')
                ->label('Horas trabajadas')
                ->tooltip('Consolidado diario, semanal, mensual y anual de horas por equipo')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->url(fn (): string => WorkedHoursLog::getUrl()),
            RegisterMeterReadingRoundAction::make(),
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
