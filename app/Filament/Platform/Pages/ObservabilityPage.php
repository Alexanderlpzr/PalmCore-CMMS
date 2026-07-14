<?php

namespace App\Filament\Platform\Pages;

use App\Domain\Platform\Services\SystemHealthService;
use App\Models\FailedJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

/**
 * Colas y trabajos fallidos.
 *
 * Un job que falla no se lo dice a nadie: se guarda en una tabla que nadie abre. Y un
 * job encolado en una cola sin worker ni siquiera falla — se queda esperando para
 * siempre, en silencio. Las dos cosas se ven aquí, y desde aquí se arreglan.
 */
class ObservabilityPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Colas y trabajos';

    protected static ?string $title = 'Colas y trabajos fallidos';

    protected static string|\UnitEnum|null $navigationGroup = 'Observabilidad';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.platform.observability';

    public function getViewData(): array
    {
        $health = app(SystemHealthService::class);
        $supervised = $health->supervisedQueues();
        $pending = $health->queuesWithPendingJobs();

        return [
            'supervised' => $supervised,
            'pending' => $pending,
            // Las colas con trabajo esperando que ningún worker atiende.
            'orphans' => array_diff_key($pending, array_flip($supervised)),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(FailedJob::query())
            ->columns([
                TextColumn::make('failed_at')
                    ->label('Falló')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('payload')
                    ->label('Trabajo')
                    ->formatStateUsing(fn ($state, FailedJob $record): string => $record->jobName())
                    ->searchable(),
                TextColumn::make('queue')
                    ->label('Cola')
                    ->badge(),
                TextColumn::make('exception')
                    ->label('Motivo')
                    ->formatStateUsing(fn ($state, FailedJob $record): string => $record->reason())
                    ->wrap()
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Reintentar')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('El trabajo se vuelve a encolar tal como estaba. Si la causa del fallo sigue ahí, volverá a fallar.')
                    ->action(function (FailedJob $record): void {
                        Artisan::call('queue:retry', ['id' => [$record->uuid]]);

                        Notification::make()->title('Trabajo reencolado')->success()->send();
                    }),
                Action::make('forget')
                    ->label('Descartar')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Se borra el registro del fallo. El trabajo NO se ejecuta: lo que tenía que hacer, no se hará.')
                    ->action(function (FailedJob $record): void {
                        Artisan::call('queue:forget', ['id' => $record->uuid]);

                        Notification::make()->title('Trabajo descartado')->success()->send();
                    }),
            ])
            ->headerActions([
                Action::make('retryAll')
                    ->label('Reintentar todos')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => FailedJob::query()->exists())
                    ->action(function (): void {
                        Artisan::call('queue:retry', ['id' => ['all']]);

                        Notification::make()->title('Todos los trabajos fallidos fueron reencolados')->success()->send();
                    }),
            ])
            ->defaultSort('failed_at', 'desc')
            ->emptyStateHeading('Ningún trabajo ha fallado')
            ->emptyStateDescription('Es la única pantalla del panel donde estar vacía es una buena noticia.');
    }
}
