<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Domain\Assets\Services\QrCodeService;
use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\Equipment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class EditEquipment extends EditRecord
{
    use HasBackAction;

    protected static string $resource = EquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_qr')
                ->label('Ver QR')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('info')
                ->modalHeading(fn (): string => 'QR — '.$this->record->code)
                ->modalWidth('sm')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->registerModalActions([
                    Action::make('regenerate')
                        ->label('Regenerar QR')
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
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
            $this->getBackAction(),
        ];
    }
}
