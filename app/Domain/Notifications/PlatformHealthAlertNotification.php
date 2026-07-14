<?php

namespace App\Domain\Notifications;

use App\Domain\Platform\Enums\HealthStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El aviso que convierte el panel en vigilancia.
 *
 * Un tablero que hay que abrir no sirve de nada: si el scheduler se muere un viernes,
 * te enteras el lunes. Esto llega solo, y solo cuando hay algo que hacer.
 */
class PlatformHealthAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{key: string, label: string, status: HealthStatus, value: string, detail: ?string}>  $problems
     */
    public function __construct(
        private readonly array $problems,
        private readonly bool $recovered = false,
    ) {
        $this->onQueue('default');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->recovered) {
            return (new MailMessage)
                ->subject('Fronda · la plataforma volvió a la normalidad')
                ->greeting('Todo en orden')
                ->line('Los problemas que se avisaron antes ya no están.')
                ->action('Ver el panel', url('/platform'));
        }

        $mail = (new MailMessage)
            ->subject('Fronda · '.$this->summary())
            ->greeting('Hay algo que revisar')
            ->line('Los siguientes chequeos de la plataforma no están bien:');

        foreach ($this->problems as $problem) {
            $mail->line("• {$problem['label']}: {$problem['value']}".
                ($problem['detail'] !== null ? " — {$problem['detail']}" : ''));
        }

        return $mail->action('Abrir el panel', url('/platform'));
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->recovered ? 'La plataforma volvió a la normalidad' : $this->summary(),
            'body' => $this->recovered
                ? 'Los problemas avisados antes ya no están.'
                : implode(' · ', array_map(
                    fn (array $problem): string => "{$problem['label']}: {$problem['value']}",
                    $this->problems,
                )),
            'url' => '/platform',
        ];
    }

    private function summary(): string
    {
        $critical = array_filter(
            $this->problems,
            fn (array $problem): bool => $problem['status'] === HealthStatus::Critical,
        );

        return $critical !== []
            ? count($critical).' problema(s) crítico(s) en la plataforma'
            : count($this->problems).' aviso(s) en la plataforma';
    }
}
