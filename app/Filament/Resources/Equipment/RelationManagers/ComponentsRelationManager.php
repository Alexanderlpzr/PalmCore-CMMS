<?php

namespace App\Filament\Resources\Equipment\RelationManagers;

use App\Domain\Assets\Enums\ComponentStatus;
use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Assets\Services\ComponentLifeHoursService;
use App\Domain\Maintenance\Enums\MaintenanceTimeFrequency;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Domain\Maintenance\Services\MaintenancePlanService;
use App\Domain\Maintenance\Services\PreventiveWorkOrderGenerator;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Models\MaintenancePlan;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ComponentsRelationManager extends RelationManager
{
    protected static string $relationship = 'components';

    protected static ?string $title = 'Piezas';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->components()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label('Código')
                    ->maxLength(50),
                TextInput::make('part_number')
                    ->label('N° de parte')
                    ->maxLength(100),
                TextInput::make('manufacturer')
                    ->label('Fabricante')
                    ->maxLength(255),
                TextInput::make('model')
                    ->label('Modelo')
                    ->maxLength(255),
                TextInput::make('serial_number')
                    ->label('N° de serie')
                    ->maxLength(255),
                Select::make('criticality')
                    ->label('Criticidad')
                    ->options(EquipmentCriticality::options())
                    ->default(EquipmentCriticality::Medium)
                    ->required(),
                Select::make('status')
                    ->label('Estado')
                    ->options(ComponentStatus::options())
                    ->default(ComponentStatus::Active)
                    ->required(),
                TextInput::make('useful_life_hours')
                    ->label('Vida útil')
                    ->numeric()
                    ->suffix('h'),
                TextInput::make('worked_hours')
                    ->label('Horas trabajadas')
                    ->helperText('Cuánto lleva la pieza HOY. A partir de aquí, el sistema las va sumando solo con cada lectura de horómetro del equipo — no hay que volver a escribirlas.')
                    ->numeric()
                    ->suffix('h'),
                TextInput::make('unit_cost')
                    ->label('Valor del repuesto')
                    ->helperText('Costo de la pieza instalada, para llevar el total invertido en el equipo.')
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0),
                Textarea::make('notes')
                    ->label('Notas')
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            // Sin esto, mostrar «próximo mantenimiento» en cada fila dispararía una
            // consulta por componente (N+1) para traer sus planes y el schedule de cada uno.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('maintenancePlans.schedule'))
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('part_number')
                    ->label('N° de parte')
                    ->placeholder('—')
                    // Casi siempre vacío: no vale la pena que se coma espacio de
                    // entrada. Sigue ahí para quien lo necesite.
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('manufacturer')
                    ->label('Fabricante')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('criticality')
                    ->label('Criticidad')
                    ->badge()
                    ->color(fn (EquipmentCriticality $state): string => $state->color())
                    ->formatStateUsing(fn (EquipmentCriticality $state): string => $state->label()),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (ComponentStatus $state): string => $state->color())
                    ->formatStateUsing(fn (ComponentStatus $state): string => $state->label()),
                TextColumn::make('worked_hours')
                    ->label('Horas de vida')
                    ->suffix('h')
                    ->placeholder('—')
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('hours_remaining')
                    ->label('Faltan')
                    ->badge()
                    ->getStateUsing(fn (EquipmentComponent $record): ?string => $this->hoursRemainingLabel($record))
                    ->color(fn (EquipmentComponent $record): string => $this->hoursRemainingColor($record))
                    ->tooltip('Horas de horómetro que faltan para el mantenimiento más próximo de esta pieza')
                    ->placeholder('—'),
                // Una sola línea con lo más urgente, no la lista entera: con varios
                // planes activos por pieza, mostrarlos todos aquí desbordaba la fila.
                // El detalle completo está en la pestaña «Planes de mantenimiento» —
                // este badge es solo el vistazo rápido, con el resto en el tooltip.
                TextColumn::make('next_maintenance')
                    ->label('Próximo mantenimiento')
                    ->getStateUsing(fn (EquipmentComponent $record): ?string => $this->nextMaintenanceSummary($record))
                    ->tooltip(fn (EquipmentComponent $record): ?string => $this->nextMaintenanceFullList($record))
                    ->placeholder('Sin plan')
                    ->limit(35),
                TextColumn::make('unit_cost')
                    ->label('Valor')
                    ->money(fn (): string => $this->getOwnerRecord()->currency_code ?? 'COP')
                    ->placeholder('—')
                    ->alignEnd()
                    ->summarize(
                        Sum::make()->money(fn (): string => $this->getOwnerRecord()->currency_code ?? 'COP')
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar pieza')
                    ->tooltip('Registrar una pieza o repuesto de este equipo')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = Filament::getTenant()->id;

                        return $data;
                    })
                    ->using(function (array $data): EquipmentComponent {
                        // worked_hours no se escribe directo: pasa por el servicio para
                        // quedar anclado a un punto de partida en el horómetro del
                        // equipo. Sin eso, el número se queda congelado para siempre —
                        // que es exactamente el bug que esto arregla.
                        $startingHours = $data['worked_hours'] ?? null;
                        unset($data['worked_hours']);

                        /** @var Equipment $equipment */
                        $equipment = $this->getOwnerRecord();

                        $component = $equipment->components()->create($data);

                        app(ComponentLifeHoursService::class)->initializeBaseline($component, $startingHours);

                        return $component;
                    }),
            ])
            ->recordActions([
                $this->scheduleMaintenanceAction(),
                EditAction::make()
                    ->tooltip('Editar los datos de esta pieza')
                    ->using(function (EquipmentComponent $record, array $data): EquipmentComponent {
                        $hasWorkedHours = array_key_exists('worked_hours', $data);
                        $newWorkedHours = $data['worked_hours'] ?? null;
                        unset($data['worked_hours']);

                        $record->update($data);

                        if ($hasWorkedHours) {
                            $service = app(ComponentLifeHoursService::class);

                            $newWorkedHours !== null
                                ? $service->rebaseline($record, (float) $newWorkedHours)
                                : $service->clear($record);
                        }

                        return $record;
                    }),
                DeleteAction::make()
                    ->tooltip('Eliminar esta pieza')
                    ->modalDescription('Sus planes de mantenimiento no se borran: quedan asociados a todo el equipo en vez de a esta pieza.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            // Sin esto, Filament arma el estado vacío a partir del nombre crudo del
            // modelo: «Cree un equipment component para empezar». El mismo defecto
            // bilingüe que tenía el botón de crear, solo que aquí nadie lo ve hasta
            // que la tabla está vacía.
            ->emptyStateHeading('Sin piezas registradas')
            ->emptyStateDescription('Registra los componentes o repuestos de este equipo para llevarles la vida útil y programarles mantenimiento.');
    }

    /**
     * «Programar mantenimiento» directo desde la pieza — sin salir a la pantalla de
     * planes, elegir el equipo y volver a buscar el componente. El técnico ve la
     * pieza y le agenda su rutina ahí mismo: cada cuántas horas (o cada cuánto
     * tiempo) y con cuánta anticipación quiere la OT para pedir el repuesto.
     *
     * Crea Y activa el plan en un paso: un plan creado pero sin activar no genera
     * nada, y desde aquí la intención es inequívoca —se está programando, no
     * dejando un borrador—.
     */
    private function scheduleMaintenanceAction(): Action
    {
        return Action::make('scheduleMaintenance')
            ->label('Programar mantenimiento')
            ->tooltip('Crear un plan de mantenimiento para esta pieza')
            ->icon(Heroicon::OutlinedCalendarDateRange)
            ->color('success')
            ->modalHeading(fn (EquipmentComponent $record): string => "Programar mantenimiento — {$record->name}")
            ->modalSubmitActionLabel('Programar')
            ->schema([
                TextInput::make('name')
                    ->label('Nombre del plan')
                    ->required()
                    ->maxLength(255)
                    ->default(fn (EquipmentComponent $record): string => "Mantenimiento — {$record->name}"),
                Select::make('trigger_source')
                    ->label('Se programa por')
                    ->options([
                        MaintenanceTriggerSource::Meter->value => 'Horas de operación (horómetro)',
                        MaintenanceTriggerSource::Calendar->value => 'Fecha (calendario)',
                    ])
                    ->default(MaintenanceTriggerSource::Meter->value)
                    ->live()
                    ->required(),
                TextInput::make('meter_interval')
                    ->label('Cada cuántas horas')
                    ->helperText('Cada cuántas horas de operación se repite la intervención (ej: aceite cada 5000 h).')
                    ->numeric()
                    ->minValue(1)
                    ->suffix('h')
                    ->visible(fn (Get $get): bool => $get('trigger_source') === MaintenanceTriggerSource::Meter->value)
                    ->required(fn (Get $get): bool => $get('trigger_source') === MaintenanceTriggerSource::Meter->value),
                TextInput::make('meter_lead_hours')
                    ->label('Avisar con anticipación')
                    ->helperText('Cuántas horas antes del vencimiento aparece la OT, para pedir el repuesto a tiempo.')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('h')
                    ->default(200)
                    ->visible(fn (Get $get): bool => $get('trigger_source') === MaintenanceTriggerSource::Meter->value),
                Select::make('time_frequency')
                    ->label('Cada cuánto')
                    ->options(MaintenanceTimeFrequency::options())
                    ->visible(fn (Get $get): bool => $get('trigger_source') === MaintenanceTriggerSource::Calendar->value)
                    ->required(fn (Get $get): bool => $get('trigger_source') === MaintenanceTriggerSource::Calendar->value),
            ])
            ->action(function (array $data, EquipmentComponent $record, MaintenancePlanService $service): void {
                /** @var Equipment $equipment */
                $equipment = $this->getOwnerRecord();

                $plan = $service->create([
                    'tenant_id' => Filament::getTenant()->id,
                    'equipment_id' => $equipment->id,
                    'equipment_component_id' => $record->id,
                    'name' => $data['name'],
                    'trigger_source' => $data['trigger_source'],
                    'time_frequency' => $data['time_frequency'] ?? null,
                    'meter_interval' => isset($data['meter_interval']) ? (int) $data['meter_interval'] : null,
                    'meter_lead_hours' => isset($data['meter_lead_hours']) && $data['meter_lead_hours'] !== null
                        ? (int) $data['meter_lead_hours']
                        : null,
                ], auth()->user());

                // Sin activar, el plan no genera OTs. Desde aquí la intención es
                // programar, así que se activa en el mismo paso.
                $service->activate($plan);

                Notification::make()
                    ->title('Mantenimiento programado')
                    ->body("El plan «{$plan->name}» quedó activo para esta pieza.")
                    ->success()
                    ->send();
            });
    }

    /**
     * El plan por horómetro más próximo a vencer de la pieza, con las horas que le
     * faltan y su anticipación configurada. Null si la pieza no tiene planes por
     * horómetro (los de fecha se leen en «Próximo mantenimiento»). Es lo que
     * alimenta el badge «Faltan»: el número que un técnico quiere ver de un vistazo.
     *
     * @return array{remaining: float, lead: int}|null
     */
    private function mostUrgentMeterPlan(EquipmentComponent $component): ?array
    {
        /** @var Equipment $equipment */
        $equipment = $this->getOwnerRecord();
        $meterService = app(EquipmentMeterReadingService::class);

        $best = null;

        foreach ($component->maintenancePlans->where('is_active', true) as $plan) {
            if (! $plan->isMeterBased()) {
                continue;
            }

            $remaining = $meterService->metersRemaining($equipment, $plan);

            if ($remaining === null) {
                continue;
            }

            if ($best === null || $remaining < $best['remaining']) {
                $best = [
                    'remaining' => $remaining,
                    'lead' => $plan->meter_lead_hours ?? PreventiveWorkOrderGenerator::DEFAULT_METER_LEAD_HOURS,
                ];
            }
        }

        return $best;
    }

    private function hoursRemainingLabel(EquipmentComponent $component): ?string
    {
        $urgent = $this->mostUrgentMeterPlan($component);

        if ($urgent === null) {
            return null;
        }

        return $urgent['remaining'] <= 0
            ? 'Vencido'
            : number_format($urgent['remaining'], 0).' h';
    }

    /**
     * Verde: hay tiempo. Amarillo: ya entró en la ventana de anticipación —la OT se
     * está por generar o ya se generó—. Rojo: se pasó del intervalo. El mismo umbral
     * que usa el generador para decidir cuándo crear la OT, así el color no miente.
     */
    private function hoursRemainingColor(EquipmentComponent $component): string
    {
        $urgent = $this->mostUrgentMeterPlan($component);

        if ($urgent === null) {
            return 'gray';
        }

        return match (true) {
            $urgent['remaining'] <= 0 => 'danger',
            $urgent['remaining'] <= $urgent['lead'] => 'warning',
            default => 'success',
        };
    }

    /**
     * Un renglón por plan activo («Aceite: 320 h»), ordenado por lo más urgente
     * primero. Mezcla planes por horómetro y por fecha sin distinción: a quien
     * mira la tabla no le interesa cómo se dispara, le interesa cuánto falta.
     *
     * @return Collection<int, string>
     */
    private function activePlanLines(EquipmentComponent $component): Collection
    {
        /** @var Equipment $equipment */
        $equipment = $this->getOwnerRecord();
        $meterService = app(EquipmentMeterReadingService::class);

        return $component->maintenancePlans
            ->where('is_active', true)
            ->map(function (MaintenancePlan $plan) use ($equipment, $meterService): ?array {
                if ($plan->isMeterBased()) {
                    $remaining = $meterService->metersRemaining($equipment, $plan);

                    return $remaining !== null
                        ? ['label' => "{$plan->name}: ".number_format($remaining, 0).' h', 'sort' => $remaining]
                        : null;
                }

                $dueAt = $plan->schedule?->next_due_at;

                return $dueAt !== null
                    ? ['label' => "{$plan->name}: ".$dueAt->diffForHumans(), 'sort' => $dueAt->timestamp]
                    : null;
            })
            ->filter()
            ->sortBy('sort')
            ->values()
            ->pluck('label');
    }

    /**
     * Lo que se ve en la celda: solo el plan más urgente. Con varios planes
     * activos por pieza —el caso normal una vez que se empieza a usar «Programar
     * mantenimiento»— listarlos todos aquí desbordaba la fila. El resto vive en
     * el tooltip y en la pestaña «Planes de mantenimiento».
     */
    private function nextMaintenanceSummary(EquipmentComponent $component): ?string
    {
        $lines = $this->activePlanLines($component);

        if ($lines->isEmpty()) {
            return null;
        }

        $extra = $lines->count() - 1;

        return $extra > 0 ? "{$lines->first()} (+{$extra} más)" : $lines->first();
    }

    /** El detalle completo, para el tooltip de la celda compacta. */
    private function nextMaintenanceFullList(EquipmentComponent $component): ?string
    {
        $lines = $this->activePlanLines($component);

        return $lines->isNotEmpty() ? $lines->implode(' · ') : null;
    }
}
