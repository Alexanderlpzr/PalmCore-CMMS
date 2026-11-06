<?php

namespace App\Domain\Notifications;

use App\Channels\WebPushChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Un aviso de Fronda: llega a la campana del panel y, si la persona activó los avisos en
 * el celular, también allá.
 *
 * Se guarda en el formato de la campana de Filament —título, cuerpo, ícono, color y el
 * botón «Abrir»— más el tipo, el enlace y la empresa, que usan el centro de
 * notificaciones y el push. Se manda después de confirmar la transacción: un aviso de
 * algo que al final no se guardó es peor que ninguno.
 */
class InboxNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'success'|'warning'|'danger'|'info'  $status
     */
    public function __construct(
        public readonly NotificationKind $kind,
        public readonly string $title,
        public readonly ?string $body,
        public readonly string $status,
        public readonly ?string $url,
        public readonly ?string $tenantId,
        public readonly string $actionLabel = 'Abrir',
    ) {
        $this->afterCommit();
    }

    /** @return array<int, string|class-string> */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $message = FilamentNotification::make()
            ->title($this->title)
            ->body($this->body)
            ->status($this->status)
            ->icon($this->kind->icon())
            ->actions($this->url ? [
                Action::make('abrir')
                    ->label($this->actionLabel)
                    ->url($this->url)
                    ->markAsRead(),
            ] : [])
            ->getDatabaseMessage();

        return [
            ...$message,
            'kind' => $this->kind->value,
            'url' => $this->url,
            'tenant_id' => $this->tenantId,
        ];
    }

    /** @return array<string, mixed> */
    public function toWebPush(object $notifiable, Notification $notification): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'title' => $this->title,
            'body' => Str::limit((string) $this->body, 120),
            'icon' => '/icons/icon-192.png',
            'badge' => '/icons/icon-192.png',
            'url' => $this->url ?? '/',
            'tag' => $this->kind->value.'-'.md5($this->title),
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return 'fronda.'.$this->kind->value;
    }
}
