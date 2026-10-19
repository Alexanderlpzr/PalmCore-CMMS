<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\RelationManagers;

use App\Domain\Maintenance\Enums\MaintenanceChecklistItemType;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderTaskStatus;
use App\Domain\Maintenance\Services\WorkOrderTaskService;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklistResult;
use App\Models\WorkOrderTask;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * El checklist del preventivo, para llenarlo desde la web: cada tarea que trae la OT del
 * plan, con sus mediciones congeladas —qué medir, en qué unidad y entre qué valores—.
 *
 * Hasta ahora solo se podía llenar desde el móvil, y como el cierre directo de la OT no
 * pasa por «Completada», los preventivos se cerraban sin registrar ni una medición. Aquí
 * se marca cada tarea, se escriben los valores y se omite con motivo lo que no se hizo;
 * un valor fuera de rango levanta la alerta de confiabilidad, como en el móvil.
 *
 * Solo aparece en las OT que traen tareas, y se cierra a cambios con la OT.
 */
class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Checklist';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var WorkOrder $ownerRecord */
        return $ownerRecord->tasks()->exists();
    }

    /** Cuántas tareas van resueltas de cuántas: «3/5». */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var WorkOrder $ownerRecord */
        $progress = app(WorkOrderTaskService::class)->progress($ownerRecord);

        return $progress['total'] > 0 ? $progress['resolved'].'/'.$progress['total'] : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var WorkOrder $ownerRecord */
        $progress = app(WorkOrderTaskService::class)->progress($ownerRecord);

        return $progress['resolved'] === $progress['total'] ? 'success' : 'warning';
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['checklistResults', 'completedBy:id,name']))
            ->paginated(false)
            ->emptyStateHeading('Esta OT no trae tareas')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->width('3rem'),
                TextColumn::make('title')
                    ->label('Tarea')
                    ->wrap()
                    ->description(fn (WorkOrderTask $record): ?string => $record->status === WorkOrderTaskStatus::Skipped
                        ? 'Omitida: '.$record->skipped_reason
                        : $record->description),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (WorkOrderTaskStatus $state): string => $state->label())
                    ->color(fn (WorkOrderTaskStatus $state): string => $state->color()),
                TextColumn::make('mediciones')
                    ->label('Mediciones')
                    ->state(function (WorkOrderTask $record): ?string {
                        $total = $record->checklistResults->count();

                        if ($total === 0) {
                            return null;
                        }

                        return $record->checklistResults->filter(fn (WorkOrderChecklistResult $r): bool => $r->isAnswered())->count().' de '.$total;
                    })
                    ->description(function (WorkOrderTask $record): ?string {
                        $fuera = $record->checklistResults->filter(fn (WorkOrderChecklistResult $r): bool => (bool) $r->is_out_of_range);

                        return $fuera->isEmpty() ? null : $fuera->map(fn (WorkOrderChecklistResult $r): string => $r->label.': '.$r->displayValue())->join(' · ');
                    })
                    ->color(fn (WorkOrderTask $record): ?string => $record->hasDeviations() ? 'danger' : null)
                    ->placeholder('Sin mediciones')
                    ->alignEnd(),
                TextColumn::make('completedBy.name')
                    ->label('Hecha por')
                    ->description(fn (WorkOrderTask $record): ?string => $record->completed_at?->format('d/m/Y h:i a'))
                    ->placeholder('—'),
            ])
            ->recordActions([
                $this->fillAction(),
                $this->skipAction(),
            ]);
    }

    /** Llenar las mediciones de la tarea y, si se quiere, darla por hecha. */
    private function fillAction(): Action
    {
        return Action::make('llenar')
            ->label(fn (WorkOrderTask $record): string => $record->status === WorkOrderTaskStatus::Done ? 'Corregir' : 'Llenar')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('primary')
            ->visible(fn (WorkOrderTask $record): bool => $this->canExecute() && $record->status !== WorkOrderTaskStatus::Skipped)
            ->modalHeading(fn (WorkOrderTask $record): string => $record->title)
            ->modalDescription(fn (WorkOrderTask $record): ?string => $record->description)
            ->modalSubmitActionLabel('Guardar')
            ->schema(fn (WorkOrderTask $record): array => [
                ...$record->checklistResults->sortBy('sort_order')->map(fn (WorkOrderChecklistResult $result) => $this->fieldFor($result))->values()->all(),
                Toggle::make('marcar_hecha')
                    ->label('Dar la tarea por hecha')
                    ->helperText('Con las mediciones obligatorias completas. Si queda algo pendiente, guarde y termine después.')
                    ->default($record->status !== WorkOrderTaskStatus::Done)
                    ->visible($record->status !== WorkOrderTaskStatus::Done),
            ])
            ->action(function (WorkOrderTask $record, array $data): void {
                /** @var User $user */
                $user = auth()->user();
                $service = app(WorkOrderTaskService::class);
                $fuera = [];

                try {
                    foreach ($record->checklistResults as $result) {
                        $value = $data['r_'.$result->id] ?? null;

                        if ($value === null || $value === '') {
                            continue;
                        }

                        $saved = $service->recordChecklistResult($result, $user, ['value' => $value]);

                        if ($saved->is_out_of_range) {
                            $fuera[] = $saved->label.': '.$saved->displayValue().$this->rangeText($saved);
                        }
                    }

                    if (($data['marcar_hecha'] ?? false) && $record->status !== WorkOrderTaskStatus::Done) {
                        $service->completeTask($record->refresh(), $user);
                    }
                } catch (BusinessRuleException|\InvalidArgumentException $e) {
                    Notification::make()->title('No se guardó todo')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title($fuera === [] ? 'Checklist guardado' : 'Guardado, con mediciones fuera de rango')
                    ->body($fuera === [] ? null : implode(' · ', $fuera).'. Se avisó en Alertas.')
                    ->color($fuera === [] ? 'success' : 'warning')
                    ->send();
            });
    }

    /** Omitir la tarea, con su motivo: «no se hizo» sin porqué es como se pudre un preventivo. */
    private function skipAction(): Action
    {
        return Action::make('omitir')
            ->label('Omitir')
            ->icon(Heroicon::OutlinedForward)
            ->color('gray')
            ->visible(fn (WorkOrderTask $record): bool => $this->canExecute() && ! $record->status->isResolved())
            ->modalHeading(fn (WorkOrderTask $record): string => 'Omitir «'.$record->title.'»')
            ->schema([
                Textarea::make('motivo')
                    ->label('¿Por qué no se hizo?')
                    ->required()
                    ->rows(2),
            ])
            ->modalSubmitActionLabel('Omitir la tarea')
            ->action(function (WorkOrderTask $record, array $data): void {
                /** @var User $user */
                $user = auth()->user();
                app(WorkOrderTaskService::class)->skipTask($record, $user, (string) $data['motivo']);

                Notification::make()->title('Tarea omitida')->success()->send();
            });
    }

    /** El campo de una medición según su tipo congelado, con su unidad y su rango a la vista. */
    private function fieldFor(WorkOrderChecklistResult $result): Radio|TextInput
    {
        $name = 'r_'.$result->id;

        $field = match ($result->item_type) {
            MaintenanceChecklistItemType::Boolean => Radio::make($name)
                ->options(['1' => 'Sí / Bien', '0' => 'No / Mal'])
                ->inline()
                ->default($result->value_boolean === null ? null : ($result->value_boolean ? '1' : '0')),
            MaintenanceChecklistItemType::Numeric => TextInput::make($name)
                ->numeric()
                ->suffix($result->unit)
                ->helperText($result->hasRange() ? 'Rango permitido'.$this->rangeText($result) : null)
                ->default($result->value_numeric === null ? null : (float) $result->value_numeric),
            MaintenanceChecklistItemType::Text => TextInput::make($name)
                ->default($result->value_text),
        };

        return $field
            ->label($result->label)
            ->required((bool) $result->is_required);
    }

    private function rangeText(WorkOrderChecklistResult $result): string
    {
        if (! $result->hasRange()) {
            return '';
        }

        $unit = $result->unit ? ' '.$result->unit : '';
        $min = $result->expected_min !== null ? rtrim(rtrim(number_format((float) $result->expected_min, 3, ',', '.'), '0'), ',') : null;
        $max = $result->expected_max !== null ? rtrim(rtrim(number_format((float) $result->expected_max, 3, ',', '.'), '0'), ',') : null;

        return match (true) {
            $min !== null && $max !== null => " ({$min} a {$max}{$unit})",
            $min !== null => " (mínimo {$min}{$unit})",
            default => " (máximo {$max}{$unit})",
        };
    }

    /** Quien ejecuta OT, mientras la OT siga abierta. */
    private function canExecute(): bool
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->getOwnerRecord();

        return ! in_array($workOrder->status, [WorkOrderStatus::Closed, WorkOrderStatus::Cancelled], true)
            && (auth()->user()?->hasPermissionTo('work-orders.execute') || auth()->user()?->is_super_admin);
    }
}
