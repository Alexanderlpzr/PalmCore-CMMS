<?php

namespace App\Filament\Platform\Pages;

use App\Domain\Platform\Services\BackupService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
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
            Action::make('runBackup')
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
                            ->send();

                        return;
                    }

                    Notification::make()->title('Respaldo completado')->success()->send();
                }),
        ];
    }

    public function getViewData(): array
    {
        $service = app(BackupService::class);

        return [
            'backups' => $service->list(),
            'disk' => $service->disk(),
        ];
    }
}
