<?php

namespace App\Filament\Resources\Safety\Pages;

use App\Domain\Safety\Enums\FindingStatus;
use App\Domain\Safety\Services\SafetyFindingService;
use App\Filament\Forms\Components\NewPhotosUpload;
use App\Filament\Resources\Safety\SstFindingResource;
use App\Models\SstFinding;
use App\Models\SstFindingTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class ViewSstFinding extends ViewRecord
{
    protected static string $resource = SstFindingResource::class;

    /** Todo lo que muestra la ficha, de una vez. */
    private const RELATIONS = ['area', 'equipment', 'createdBy', 'verifiedBy', 'tasks.executedBy', 'tasks.issueReport.workOrder'];

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(self::RELATIONS);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('empezar')
                ->label('Empezar a corregir')
                ->icon(Heroicon::OutlinedPlayCircle)
                ->color('warning')
                ->visible(fn (): bool => $this->actionableTasks(FindingStatus::Pendiente) !== [])
                ->schema(fn (): array => [
                    Select::make('task_id')
                        ->label('Parte de')
                        ->options($this->actionableTasks(FindingStatus::Pendiente))
                        ->default(array_key_first($this->actionableTasks(FindingStatus::Pendiente)))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(SafetyFindingService::class)->start($this->task($data['task_id']), $this->user());
                    $this->refreshRecord('Quedó en ejecución');
                }),

            Action::make('ejecutar')
                ->label('Marcar corregido')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->visible(fn (): bool => $this->actionableTasks() !== [])
                ->modalDescription('La fecha de corrección es el día en que quedó corregido. Cuando todas las áreas terminan, el hallazgo queda «Ejecutado» y SST lo verifica.')
                // Los campos son de la parte del área, no del hallazgo: no se llenan con él.
                ->fillForm(fn (): array => [
                    'task_id' => array_key_first($this->actionableTasks()),
                    'executed_on' => SstFinding::today()->toDateString(),
                    'notes' => null,
                    'evidence_paths' => [],
                ])
                ->schema(fn (): array => [
                    Select::make('task_id')
                        ->label('Parte de')
                        ->options($this->actionableTasks())
                        ->default(array_key_first($this->actionableTasks()))
                        ->required(),
                    DatePicker::make('executed_on')
                        ->label('Fecha de corrección')
                        ->default(SstFinding::today()->toDateString())
                        ->maxDate(SstFinding::today()->toDateString())
                        ->required(),
                    Textarea::make('notes')
                        ->label('Qué se hizo')
                        ->placeholder('Ej.: se instaló la guarda del dosificador y se señalizó el área.')
                        ->rows(3)
                        ->maxLength(2000)
                        ->required(),
                    NewPhotosUpload::make('evidence_paths')
                        ->label('Fotos de lo que se hizo')
                        ->helperText('Hasta 6 fotos. Con ellas SST verifica el arreglo.')
                        ->maxFiles(6)
                        ->reorderable()
                        ->disk(persistent_disk())
                        ->directory(fn (): string => 'sst-findings/'.$this->getRecord()->tenant_id)
                        ->visibility(persistent_disk() === 'public' ? 'public' : 'private')
                        ->maxSize(10240),
                ])
                ->action(function (array $data): void {
                    app(SafetyFindingService::class)->execute(
                        $this->task($data['task_id']),
                        $this->user(),
                        CarbonImmutable::parse($data['executed_on']),
                        $data['notes'] ?? null,
                        array_values((array) ($data['evidence_paths'] ?? [])),
                    );
                    $this->refreshRecord('Parte marcada como corregida');
                }),

            Action::make('verificar')
                ->label('Verificar')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('success')
                ->visible(fn (SstFinding $record): bool => $record->status === FindingStatus::Ejecutado && $this->user()->can('verify', $record))
                ->modalDescription('Revisó el arreglo en la planta: el hallazgo se cierra y se les avisa a las áreas.')
                ->schema([
                    Textarea::make('verification_notes')->label('Observación (opcional)')->rows(2),
                    $this->afterPhotoField(),
                ])
                ->action(function (array $data, SstFinding $record): void {
                    app(SafetyFindingService::class)->verify($record, $this->user(), $data['verification_notes'] ?? null, $data['photo_after_path'] ?? null);
                    $this->refreshRecord('Hallazgo verificado');
                }),

            EditAction::make()->label('Corregir datos')->color('gray'),
        ];
    }

    /**
     * Las partes que este usuario puede mover: las de su área o, si es de SST o de la
     * administración, todas. La de mantenimiento con reporte se mueve con su OT.
     *
     * @return array<string, string>
     */
    private function actionableTasks(?FindingStatus $only = null): array
    {
        /** @var SstFinding $finding */
        $finding = $this->getRecord();
        $service = app(SafetyFindingService::class);

        if ($finding->status === FindingStatus::Verificado) {
            return [];
        }

        return $finding->tasks
            ->filter(fn (SstFindingTask $task): bool => $task->status !== FindingStatus::Ejecutado
                && ($only === null || $task->status === $only)
                && $service->canActOn($this->user(), $task))
            ->mapWithKeys(fn (SstFindingTask $task): array => [$task->id => $task->area->label()])
            ->all();
    }

    private function afterPhotoField(): FileUpload
    {
        return FileUpload::make('photo_after_path')
            ->label('Foto del después (opcional)')
            ->image()
            ->disk(persistent_disk())
            ->directory(fn (): string => 'sst-findings/'.$this->getRecord()->tenant_id)
            ->visibility(persistent_disk() === 'public' ? 'public' : 'private')
            ->preventFilePathTampering()
            ->maxSize(10240);
    }

    private function task(string $taskId): SstFindingTask
    {
        $task = $this->getRecord()->tasks->firstWhere('id', $taskId);

        abort_unless($task && app(SafetyFindingService::class)->canActOn($this->user(), $task), 403);

        return $task;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function refreshRecord(string $message): void
    {
        $this->record->refresh()->load(self::RELATIONS);

        Notification::make()->title($message)->success()->send();
    }
}
