<?php

namespace App\Filament\Resources\MeterReadings\Pages;

use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Assets\Enums\MeterReadingFrequency;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Services\MaintenancePlanService;
use App\Domain\Maintenance\Services\PreventiveWorkOrderGenerator;
use App\Filament\Resources\MeterReadings\Concerns\InteractsWithMaintenanceControl;
use App\Filament\Resources\MeterReadings\Concerns\InteractsWithMeterMatrix;
use App\Filament\Resources\MeterReadings\Concerns\InteractsWithWorkedHoursReport;
use App\Filament\Resources\MeterReadings\MeterReadingResource;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;

/**
 * El centro de horómetros, todo en una página con pestañas de color:
 *
 *   - Control de Mantenimiento (verde) — el tablero de vencimientos por horómetro.
 *   - Registro Diario (azul)           — captura de los equipos de lectura diaria.
 *   - Registro Semanal (ámbar)         — captura de los de lectura semanal.
 *   - Horas Trabajadas (violeta)       — consolidado mensual/anual, sumado del horómetro.
 *
 * La captura (Diario/Semanal) alimenta el horómetro actual; el Control lo consume
 * para avisar cuándo toca cada mantenimiento. La captura se hace dentro de las
 * pestañas (Capturar/Cuadrícula), sin botones sueltos en el encabezado.
 */
class ListMeterReadings extends ListRecords
{
    use InteractsWithMaintenanceControl;
    use InteractsWithMeterMatrix;
    use InteractsWithWorkedHoursReport;

    protected static string $resource = MeterReadingResource::class;

    protected string $view = 'filament.resources.meter-readings.list-hub';

    /** Pestaña activa: 'control' | 'diario' | 'semanal' | 'horas'. */
    public string $tab = 'control';

    public function mount(): void
    {
        parent::mount();
        $this->resetAnchor();
        $this->initWorkedHoursReport();

        // El tablero de Control es la vista principal para quien puede verlo; los
        // operarios de ronda, que no lo ven, arrancan en la captura diaria.
        $this->tab = $this->controlTabVisible() ? 'control' : 'diario';
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;

        // Al volver a una matriz, la ventana se realinea al período actual (diario y
        // semanal usan pasos distintos, así que el ancla no puede compartirse tal cual).
        if ($this->onMatrixTab()) {
            $this->resetAnchor();
        }
    }

    /** Las pestañas de captura estilo Excel (matriz equipo × fecha). */
    public function onMatrixTab(): bool
    {
        return in_array($this->tab, ['diario', 'semanal'], true);
    }

    public function onControlTab(): bool
    {
        return $this->tab === 'control';
    }

    /** El tablero de Control solo lo ve quien puede ver los planes de mantenimiento. */
    public function controlTabVisible(): bool
    {
        return (bool) (auth()->user()?->is_super_admin || auth()->user()?->can('maintenance-plans.view'));
    }

    protected function getHeaderActions(): array
    {
        // La captura de lecturas se hace en las propias pestañas (Capturar/Cuadrícula),
        // así que ya no hacen falta los botones «Registrar lectura» ni «Registrar ronda».
        return [
            $this->configureEquipmentAction(),
            $this->addControlTaskAction(),
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
            ->visible(fn (): bool => $this->onMatrixTab() && (bool) (auth()->user()?->is_super_admin || auth()->user()?->can('equipment.update')))
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
     * Agregar una tarea de mantenimiento por horómetro directo desde el tablero de
     * Control, como una fila más del Excel: equipo, qué se hace, cada cuántas horas,
     * el horómetro del último mtto y con cuánta anticipación avisar. Por detrás es un
     * plan por horómetro que se crea y activa de una vez, con
     * próximo = último + frecuencia.
     */
    private function addControlTaskAction(): Action
    {
        return Action::make('addControlTask')
            ->label('Agregar tarea')
            ->tooltip('Registra una tarea de mantenimiento por horómetro')
            ->icon(Heroicon::OutlinedPlus)
            ->color('primary')
            ->visible(fn (): bool => $this->onControlTab()
                && (bool) (auth()->user()?->is_super_admin || auth()->user()?->can('maintenance-plans.create')))
            ->modalHeading('Nueva tarea de mantenimiento')
            ->modalSubmitActionLabel('Agregar')
            ->schema([
                Select::make('equipment_id')
                    ->label('Equipo')
                    ->required()
                    ->searchable()
                    ->live()
                    ->options(fn (): array => $this->equipmentOptions())
                    ->afterStateUpdated(fn (Set $set) => $set('equipment_component_id', null)),
                Select::make('equipment_component_id')
                    ->label('Pieza')
                    ->helperText('Opcional. Sin pieza, la tarea es del equipo entero.')
                    ->searchable()
                    ->nullable()
                    ->placeholder('Todo el equipo')
                    ->disabled(fn (Get $get): bool => blank($get('equipment_id')))
                    ->options(fn (Get $get): array => blank($get('equipment_id'))
                        ? []
                        : EquipmentComponent::query()
                            ->where('equipment_id', $get('equipment_id'))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()),
                TextInput::make('name')
                    ->label('Qué se hace')
                    ->placeholder('Ej: cambio aceite reductor')
                    ->required()
                    ->maxLength(255),
                TextInput::make('meter_interval')
                    ->label('Frecuencia')
                    ->helperText('Cada cuántas horas de horómetro se repite.')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->suffix('h'),
                TextInput::make('last_completed_meter')
                    ->label('Horómetro último mtto')
                    ->helperText('El horómetro al que se hizo por última vez. Vacío = nunca (0).')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->suffix('h'),
                TextInput::make('meter_lead_hours')
                    ->label('Avisar con anticipación')
                    ->helperText('Horas antes del vencimiento para el semáforo ámbar y la OT.')
                    ->numeric()
                    ->minValue(0)
                    ->default(PreventiveWorkOrderGenerator::DEFAULT_METER_LEAD_HOURS)
                    ->suffix('h'),
            ])
            ->action(function (array $data): void {
                $service = app(MaintenancePlanService::class);
                $interval = (int) $data['meter_interval'];

                $last = ($data['last_completed_meter'] === '' || $data['last_completed_meter'] === null)
                    ? null
                    : (float) $data['last_completed_meter'];

                $plan = $service->create([
                    'tenant_id' => Filament::getTenant()->id,
                    'equipment_id' => $data['equipment_id'],
                    'equipment_component_id' => $data['equipment_component_id'] ?? null,
                    'name' => $data['name'],
                    'trigger_source' => MaintenanceTriggerSource::Meter->value,
                    'meter_interval' => $interval,
                    'cadence_mode' => 'floating',
                    'meter_lead_hours' => ($data['meter_lead_hours'] === '' || $data['meter_lead_hours'] === null)
                        ? null
                        : (int) $data['meter_lead_hours'],
                ], auth()->user());

                // Próximo = último + frecuencia, la cuenta del Excel.
                $schedule = $service->activate($plan, firstDueMeter: ($last ?? 0.0) + $interval);

                if ($last !== null) {
                    $schedule->update(['last_completed_meter' => $last]);
                }

                Notification::make()
                    ->title('Tarea agregada')
                    ->body("{$plan->plan_number} — {$plan->name}")
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
