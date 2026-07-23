<?php

namespace App\Filament\Resources\MeterReadings\Pages;

use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Assets\Enums\MeterReadingFrequency;
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
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Exceptions\Halt;
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
            $this->configureEquipmentAction(),
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

    /**
     * Asignar en bloque qué equipos van a Registro Diario y cuáles a Semanal, con
     * dos menús desplegables, en vez de entrar equipo por equipo a su ficha. Las
     * dos listas son la definición completa de las rondas: un equipo que se saca de
     * ambas queda sin ronda.
     */
    private function configureEquipmentAction(): Action
    {
        return Action::make('configureEquipment')
            ->label('Configurar equipos')
            ->tooltip('Elige qué equipos van a Registro Diario y cuáles a Semanal')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('primary')
            ->visible(fn (): bool => (bool) (auth()->user()?->is_super_admin || auth()->user()?->can('equipment.update')))
            ->modalHeading('Equipos de las rondas de horómetro')
            ->modalSubmitActionLabel('Guardar')
            ->fillForm(fn (): array => [
                'daily' => $this->equipmentIdsFor(MeterReadingFrequency::Daily),
                'weekly' => $this->equipmentIdsFor(MeterReadingFrequency::Weekly),
            ])
            ->schema([
                Select::make('daily')
                    ->label('Registro Diario')
                    ->helperText('Los equipos críticos que se leen todos los días.')
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => $this->equipmentOptions()),
                Select::make('weekly')
                    ->label('Registro Semanal')
                    ->helperText('Los equipos que se leen una vez por semana.')
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => $this->equipmentOptions()),
            ])
            ->action(function (array $data): void {
                $daily = array_values($data['daily'] ?? []);
                $weekly = array_values($data['weekly'] ?? []);

                $conflict = array_intersect($daily, $weekly);

                if ($conflict !== []) {
                    Notification::make()
                        ->title('Un equipo no puede ser diario y semanal a la vez')
                        ->body('Hay equipos repetidos en las dos listas. Déjalo en una sola.')
                        ->danger()
                        ->send();

                    throw new Halt;
                }

                Equipment::query()->whereIn('id', $daily)->update(['reading_frequency' => MeterReadingFrequency::Daily->value]);
                Equipment::query()->whereIn('id', $weekly)->update(['reading_frequency' => MeterReadingFrequency::Weekly->value]);

                // Los que salieron de ambas listas quedan sin ronda.
                Equipment::query()
                    ->whereNotNull('reading_frequency')
                    ->whereNotIn('id', array_merge($daily, $weekly))
                    ->update(['reading_frequency' => null]);

                Notification::make()
                    ->title('Rondas actualizadas')
                    ->body(count($daily).' diario(s) · '.count($weekly).' semanal(es).')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return list<string>
     */
    private function equipmentIdsFor(MeterReadingFrequency $frequency): array
    {
        return Equipment::query()
            ->where('reading_frequency', $frequency->value)
            ->pluck('id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function equipmentOptions(): array
    {
        return Equipment::query()
            ->whereNotIn('status', [EquipmentStatus::Retired->value, EquipmentStatus::Disposed->value])
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (Equipment $e): array => [$e->id => "{$e->code} — {$e->name}"])
            ->all();
    }
}
