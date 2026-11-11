<?php

namespace App\Filament\Resources\Safety\Pages;

use App\Domain\Safety\Enums\FindingOrigin;
use App\Domain\Safety\Enums\FindingStatus;
use App\Domain\Safety\Services\SafetyFindingService;
use App\Domain\Safety\Services\SafetyFindingsWorkbookImporter;
use App\Filament\Resources\Safety\SstFindingResource;
use App\Models\SstFinding;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ListSstFindings extends ListRecords
{
    protected static string $resource = SstFindingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importar')
                ->label('Importar cuadro de Excel')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->authorize(fn (): bool => SstFindingResource::canCreate())
                ->modalHeading('Importar el cuadro de hallazgos')
                ->modalDescription('El «Cuadro de hallazgos SG-SST / Ambiental» con sus fotos. Los que tienen fecha de corrección entran ejecutados en esa fecha; los «Pendiente», pendientes. Un hallazgo ya importado no se repite.')
                ->schema([
                    Select::make('origin')
                        ->label('¿De quién es el cuadro?')
                        ->options(FindingOrigin::options())
                        ->default(fn (): string => (FindingOrigin::forUser(auth()->user()) ?? FindingOrigin::Sst)->value)
                        ->selectablePlaceholder(false)
                        ->helperText('Los del cuadro ambiental toman los números siguientes a los que ya hay, sin chocar con los de SST.')
                        ->required(),
                    FileUpload::make('file')
                        ->label('Archivo de Excel (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->disk('local')
                        ->directory('sst-imports')
                        ->maxSize(51200)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();
                    $path = Storage::disk('local')->path($data['file']);

                    try {
                        $summary = app(SafetyFindingsWorkbookImporter::class)->import($path, (string) filament()->getTenant()->getKey(), $user, FindingOrigin::from($data['origin']));
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()->title('No se pudo leer el cuadro')->body($e->getMessage())->danger()->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }

                    Notification::make()
                        ->title("Importados {$summary['created']} hallazgos")
                        ->body("{$summary['executed']} ejecutados, {$summary['pending']} pendientes, {$summary['photos']} fotos"
                            .($summary['skipped'] > 0 ? ", {$summary['skipped']} ya estaban" : '').'.'
                            .($summary['warnings'] !== [] ? ' Revise: '.implode(' ', $summary['warnings']) : ''))
                        ->success()
                        ->persistent()
                        ->send();
                }),
            CreateAction::make()->label('Reportar hallazgo'),
        ];
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $myAreas = SafetyFindingService::areasOfUser($user);

        return [
            'abiertos' => Tab::make('Abiertos')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open())
                ->badge(fn (): int => SstFinding::query()->open()->count()),
            'vencidos' => Tab::make('Vencidos')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->overdue())
                ->badge(fn (): int => SstFinding::query()->overdue()->count())
                ->badgeColor('danger'),
            'mi-area' => Tab::make('De mi área')
                ->visible($myAreas !== [])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereHas('tasks', fn (Builder $tasks) => $tasks
                    ->whereIn('area', $myAreas)
                    ->where('status', '!=', FindingStatus::Ejecutado->value)))
                ->badge(fn (): int => SstFinding::query()->whereHas('tasks', fn (Builder $tasks) => $tasks
                    ->whereIn('area', $myAreas)
                    ->where('status', '!=', FindingStatus::Ejecutado->value))->count()),
            'por-verificar' => Tab::make('Por verificar')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', FindingStatus::Ejecutado->value))
                ->badge(fn (): int => SstFinding::query()->where('status', FindingStatus::Ejecutado->value)->count())
                ->badgeColor('info'),
            'todos' => Tab::make('Todos'),
        ];
    }
}
