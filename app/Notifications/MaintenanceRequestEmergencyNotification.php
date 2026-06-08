<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceRequestEmergencyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly MaintenanceRequest $maintenanceRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mr = $this->maintenanceRequest;

        return (new MailMessage)
            ->subject("[EMERGENCIA] Solicitud de mantenimiento {$mr->request_number}")
            ->greeting('Alerta de emergencia')
            ->line("Se ha creado una solicitud de mantenimiento de emergencia ({$mr->request_number}).")
            ->line("**Equipo:** {$mr->equipment->code} — {$mr->equipment->name}")
            ->line("**Descripción:** {$mr->title}")
            ->action('Ver solicitud', rescue(fn () => route('filament.admin.resources.maintenance-requests.view', $mr), url('/admin')));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'maintenance_request_id' => $this->maintenanceRequest->id,
            'request_number'         => $this->maintenanceRequest->request_number,
        ];
    }
}
