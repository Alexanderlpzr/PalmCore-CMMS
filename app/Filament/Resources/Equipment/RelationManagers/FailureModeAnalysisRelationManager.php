<?php

namespace App\Filament\Resources\Equipment\RelationManagers;

use App\Domain\Maintenance\Enums\FailureConsequenceCategory;
use App\Domain\Maintenance\Enums\FailureMode;
use App\Domain\Maintenance\Enums\MaintenanceTimeFrequency;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Services\MaintenancePlanService;
use App\Models\Equipment;
use App\Models\FailureModeAnalysis;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * RCM-lite: catálogo de modos de falla por equipo (o pieza), con su
 * consecuencia (seguridad/ambiental, operacional, no-operacional, oculta).
 * Cuando una falla es oculta, esta pantalla es también donde se le crea la
 * tarea de búsqueda que la revela — la acción por defecto de la pregunta 7
 * de RCM ante consecuencia oculta.
 */
class FailureModeAnalysisRelationManager extends RelationManager
{
    protected static string $relationship = 'failureModeAnalyses';

    protected static ?string $title = 'Análisis de fallas (RCM)';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('failure_mode')
                    ->label('Modo de falla')
                    ->options(FailureMode::options())
                    ->searchable()
                    ->required(),
                Select::make('equipment_component_id')
                    ->label('Pieza afectada')
                    ->options(fn (): array => $this->getOwnerRecord()->components()->pluck('name', 'id')->all())
                    ->placeholder('Todo el equipo')
                    ->searchable(),
                Select::make('consequence_category')
                    ->label('Consecuencia')
                    ->options(FailureConsequenceCategory::options())
                    ->required()
                    ->live(),
                Textarea::make('effect_description')
                    ->label('Efecto de la falla')
                    ->helperText('Qué pasa cuando ocurre: qué se detiene, cómo se nota, qué riesgo trae.')
                    ->maxLength(2000)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('Notas')
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('failure_mode')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['equipmentComponent', 'failureFindingPlan']))
            ->columns([
                TextColumn::make('failure_mode')
                    ->label('Modo de falla')
                    ->badge()
                    ->formatStateUsing(fn (FailureMode $state): string => $state->label()),
                TextColumn::make('equipmentComponent.name')
                    ->label('Pieza')
                    ->placeholder('Todo el equipo'),
                TextColumn::make('consequence_category')
                    ->label('Consecuencia')
                    ->badge()
                    ->color(fn (FailureConsequenceCategory $state): string => $state->color())
                    ->formatStateUsing(fn (FailureConsequenceCategory $state): string => $state->label()),
                TextColumn::make('effect_description')
                    ->label('Efecto')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('failure_finding_plan')
                    ->label('Tarea de búsqueda')
                    ->badge()
                    ->getStateUsing(fn (FailureModeAnalysis $record): string => match (true) {
                        $record->failureFindingPlan !== null => $record->failureFindingPlan->name,
                        $record->needsFailureFindingTask() => 'Falta tarea de búsqueda',
                        default => '—',
                    })
                    ->color(fn (FailureModeAnalysis $record): string => match (true) {
                        $record->failureFindingPlan !== null => 'success',
                        $record->needsFailureFindingTask() => 'danger',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar modo de falla')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = Filament::getTenant()->id;

                        return $data;
                    }),
            ])
            ->recordActions([
                $this->createFailureFindingTaskAction(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin modos de falla registrados')
            ->emptyStateDescription('Cataloga los modos de falla de este equipo y su consecuencia para identificar cuáles necesitan una tarea de búsqueda.');
    }

    /**
     * Crea y activa un plan preventivo marcado is_failure_finding, y lo
     * enlaza al análisis en el mismo paso — sin esto, el catálogo solo
     * documenta el problema (falla oculta sin forma de detectarla) sin
     * ofrecer la acción que RCM manda tomar.
     */
    private function createFailureFindingTaskAction(): Action
    {
        return Action::make('createFailureFindingTask')
            ->label('Crear tarea de búsqueda')
            ->color('danger')
            ->visible(fn (FailureModeAnalysis $record): bool => $record->needsFailureFindingTask())
            ->modalHeading(fn (FailureModeAnalysis $record): string => "Tarea de búsqueda — {$record->failure_mode->label()}")
            ->modalSubmitActionLabel('Crear y activar')
            ->schema([
                TextInput::make('name')
                    ->label('Nombre del plan')
                    ->required()
                    ->maxLength(255)
                    ->default(fn (FailureModeAnalysis $record): string => "Búsqueda de falla — {$record->failure_mode->label()}"),
                Select::make('trigger_source')
                    ->label('Se programa por')
                    ->options([
                        MaintenanceTriggerSource::Meter->value => 'Horas de operación (horómetro)',
                        MaintenanceTriggerSource::Calendar->value => 'Fecha (calendario)',
                    ])
                    ->default(MaintenanceTriggerSource::Calendar->value)
                    ->live()
                    ->required(),
                TextInput::make('meter_interval')
                    ->label('Cada cuántas horas')
                    ->numeric()
                    ->minValue(1)
                    ->suffix('h')
                    ->visible(fn (Get $get): bool => $get('trigger_source') === MaintenanceTriggerSource::Meter->value)
                    ->required(fn (Get $get): bool => $get('trigger_source') === MaintenanceTriggerSource::Meter->value),
                Select::make('time_frequency')
                    ->label('Cada cuánto')
                    ->options(MaintenanceTimeFrequency::options())
                    ->visible(fn (Get $get): bool => $get('trigger_source') === MaintenanceTriggerSource::Calendar->value)
                    ->required(fn (Get $get): bool => $get('trigger_source') === MaintenanceTriggerSource::Calendar->value),
            ])
            ->action(function (array $data, FailureModeAnalysis $record, MaintenancePlanService $service): void {
                /** @var Equipment $equipment */
                $equipment = $this->getOwnerRecord();

                $plan = $service->create([
                    'tenant_id' => Filament::getTenant()->id,
                    'equipment_id' => $equipment->id,
                    'equipment_component_id' => $record->equipment_component_id,
                    'name' => $data['name'],
                    'trigger_source' => $data['trigger_source'],
                    'time_frequency' => $data['time_frequency'] ?? null,
                    'meter_interval' => isset($data['meter_interval']) ? (int) $data['meter_interval'] : null,
                    'is_failure_finding' => true,
                    'description' => "Tarea de búsqueda de falla para «{$record->failure_mode->label()}» — revela si la falla oculta ya ocurrió.",
                ], auth()->user());

                $service->activate($plan);

                $record->update(['failure_finding_plan_id' => $plan->id]);

                Notification::make()
                    ->title('Tarea de búsqueda creada')
                    ->body("El plan «{$plan->name}» quedó activo y enlazado a este análisis.")
                    ->success()
                    ->send();
            });
    }
}
