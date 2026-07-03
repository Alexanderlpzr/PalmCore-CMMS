<?php

namespace App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\WebhookSubscriptionResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWebhookSubscription extends EditRecord
{
    use HasBackAction;

    protected static string $resource = WebhookSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reactivate')
                ->label('Reactivar')
                ->color('success')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => ! $this->getRecord()->is_active)
                ->action(function (): void {
                    $this->getRecord()->forceFill([
                        'is_active' => true,
                        'failure_count' => 0,
                        'last_error' => null,
                    ])->save();

                    Notification::make()
                        ->title('Webhook reactivado')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Action::make('regenerate_secret')
                ->label('Regenerar secret')
                ->color('warning')
                ->icon('heroicon-o-key')
                ->requiresConfirmation()
                ->modalHeading('Regenerar secret')
                ->modalDescription('El secret actual quedará inválido. Los consumers deberán actualizar su verificación de firma.')
                ->action(function (): void {
                    $newSecret = bin2hex(random_bytes(32));
                    $this->getRecord()->forceFill(['secret' => $newSecret])->save();

                    Notification::make()
                        ->title('Secret regenerado: '.$newSecret)
                        ->warning()
                        ->persistent()
                        ->send();
                }),

            ViewAction::make(),
            DeleteAction::make(),
            $this->getBackAction(),
        ];
    }
}
