<?php

namespace App\Filament\Platform\Pages;

use App\Domain\Platform\Services\BackupService;
use App\Domain\Platform\Services\PlatformSettingsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Respaldos';

    protected static ?string $title = 'Respaldos';

    protected static string|\UnitEnum|null $navigationGroup = 'Observabilidad';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.platform.backups';

    protected function getHeaderActions(): array
    {
        return [
            $this->toggleAutomaticAction(),
            $this->runBackupAction(),
        ];
    }

    /**
     * El interruptor del respaldo nocturno.
     *
     * Apagarlo es una decisión legítima —una instalación recién montada no necesita una
     * copia cada noche— y por eso queda registrada con autor y fecha. Lo que no se
     * permite es apagarlo sin enterarse de lo que implica: el modal lo dice sin adornos.
     */
    private function toggleAutomaticAction(): Action
    {
        $settings = app(PlatformSettingsService::class);
        $enabled = $settings->automaticBackupsEnabled();

        return Action::make('toggleAutomatic')
            ->label($enabled ? 'Desactivar respaldo automático' : 'Activar respaldo automático')
            ->icon($enabled ? Heroicon::OutlinedPause : Heroicon::OutlinedPlay)
            ->color($enabled ? 'danger' : 'success')
            ->requiresConfirmation()
            ->modalHeading($enabled ? 'Desactivar el respaldo automático' : 'Activar el respaldo automático')
            ->modalDescription($enabled
                ? 'Dejarán de guardarse copias de la base de datos cada noche. Si el servidor se pierde mañana, se pierde todo lo que no hayas respaldado a mano. Puedes volver a activarlo cuando quieras.'
                : 'Cada noche a la 1:00 se guardará una copia de la base de datos.')
            ->action(function () use ($settings, $enabled): void {
                $settings->setAutomaticBackups(! $enabled, auth()->user());

                Notification::make()
                    ->title($enabled ? 'Respaldo automático desactivado' : 'Respaldo automático activado')
                    ->body($enabled ? 'El sistema dejará de guardar copias cada noche.' : null)
                    ->color($enabled ? 'warning' : 'success')
                    ->send();
            });
    }

    /** Respaldar ahora funciona siempre, aunque el automático esté apagado. */
    private function runBackupAction(): Action
    {
        return Action::make('runBackup')
            ->label('Respaldar ahora')
            ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Respaldar la base de datos')
            ->modalDescription('Puede tardar unos minutos y carga la base mientras corre. Hazlo antes de un despliegue, no en plena molienda.')
            ->action(function (): void {
                try {
                    app(BackupService::class)->runNow();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('El respaldo falló')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()->title('Respaldo completado')->success()->send();
            });
    }

    /** Llevarse la copia fuera del servidor: es lo único que protege de perder la máquina. */
    public function downloadAction(): Action
    {
        return Action::make('download')
            ->label('Descargar')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->action(function (array $arguments): StreamedResponse {
                $service = app(BackupService::class);

                return response()->streamDownload(
                    fn () => print (Storage::disk($service->disk())
                        ->get($service->pathOf($arguments['name']))),
                    $arguments['name'],
                );
            });
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Borrar')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Borrar este respaldo')
            ->modalDescription('El archivo se elimina del servidor y no se puede recuperar. Si es tu única copia, asegúrate de haberla descargado antes.')
            ->action(function (array $arguments): void {
                try {
                    app(BackupService::class)->delete($arguments['name']);
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Respaldo borrado')->success()->send();
            });
    }

    public function getViewData(): array
    {
        $service = app(BackupService::class);
        $settings = app(PlatformSettingsService::class);

        return [
            'backups' => $service->list(),
            'disk' => $service->disk(),
            'automaticEnabled' => $settings->automaticBackupsEnabled(),
            'changedBy' => $settings->automaticBackupsChangedBy(),
        ];
    }
}
