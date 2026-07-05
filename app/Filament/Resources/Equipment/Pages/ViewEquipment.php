<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Domain\Assets\Services\QrCodeService;
use App\Domain\Reports\DTOs\ReportRequest;
use App\Domain\Reports\Enums\ReportType;
use App\Domain\Reports\Services\ReportManager;
use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Widgets\Analytics\EquipmentReliabilityTrendWidget;
use App\Models\Equipment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class ViewEquipment extends ViewRecord
{
    use HasBackAction;

    protected static string $resource = EquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_qr')
                ->label('Ver QR')
                ->tooltip('Muestra el código QR de este equipo para imprimir o escanear')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('info')
                ->modalHeading(fn (): string => 'QR — '.$this->record->code)
                ->modalWidth('sm')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->registerModalActions([
                    Action::make('regenerate')
                        ->label('Regenerar QR')
                        ->tooltip('Genera un nuevo QR e invalida el actual')
                        ->color('warning')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->requiresConfirmation()
                        ->modalHeading('¿Regenerar código QR?')
                        ->modalDescription('El QR actual quedará inactivo. Todos los stickers impresos dejarán de funcionar.')
                        ->action(function (QrCodeService $service): void {
                            /** @var Equipment $equipment */
                            $equipment = $this->record;
                            $qrCode = $equipment->qrCode;

                            if ($qrCode) {
                                $service->regenerate($qrCode);
                            } else {
                                $service->createForEquipment($equipment);
                            }

                            $this->record->refresh();

                            Notification::make()
                                ->title('QR regenerado correctamente')
                                ->success()
                                ->send();
                        }),
                ])
                ->modalContent(fn (Action $action): View => view(
                    'filament.equipment.qr-modal',
                    [
                        'equipment' => $this->record,
                        'qrCode' => $this->record->qrCode,
                        'action' => $action,
                    ]
                )),
            Action::make('download_pdf')
                ->label('Descargar PDF')
                ->tooltip('Descarga la ficha técnica de este equipo en PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (ReportManager $manager): mixed {
                    /** @var Equipment $equipment */
                    $equipment = $this->record;

                    return $manager->streamDownload(new ReportRequest(
                        type: ReportType::EquipmentSheet,
                        tenantId: Filament::getTenant()->id,
                        requestedBy: auth()->id(),
                        recordId: $equipment->id,
                    ));
                }),

            EditAction::make()
                ->tooltip('Editar los datos del equipo'),
            DeleteAction::make()
                ->tooltip('Eliminar este equipo'),
            RestoreAction::make()
                ->tooltip('Recuperar este equipo eliminado'),
            $this->getBackAction(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            EquipmentReliabilityTrendWidget::class,
        ];
    }
}
