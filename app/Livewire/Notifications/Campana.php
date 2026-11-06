<?php

namespace App\Livewire\Notifications;

use App\Domain\Notifications\NotificationPresenter;
use App\Filament\Pages\Notificaciones;
use Filament\Notifications\Livewire\DatabaseNotifications;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\DatabaseNotification;
use Throwable;

/**
 * La campana del panel. La de Filament solo muestra los avisos guardados en su formato,
 * y los de alertas, OT asignadas, stock crítico, preventivos y MTBF se guardaban para la
 * app móvil: llegaban a la base y nadie los veía. Esta los muestra todos, traducidos por
 * {@see NotificationPresenter}, cada uno con «Marcar como leída / no leída», y lleva al
 * centro de notificaciones.
 */
class Campana extends DatabaseNotifications
{
    public function getNotificationsQuery(): Builder|Relation
    {
        $user = $this->getUser();

        if (! $user) {
            abort(401);
        }

        /** @phpstan-ignore-next-line */
        return $user->notifications();
    }

    public function getNotification(DatabaseNotification $notification): Notification
    {
        return app(NotificationPresenter::class)->toFilament($notification)
            ->date($this->formatNotificationDate($notification->getAttributeValue('created_at')));
    }

    public function allNotificationsUrl(): ?string
    {
        try {
            return Notificaciones::getUrl();
        } catch (Throwable) {
            return null;
        }
    }

    public function render(): View
    {
        return view('filament.notifications.campana');
    }
}
