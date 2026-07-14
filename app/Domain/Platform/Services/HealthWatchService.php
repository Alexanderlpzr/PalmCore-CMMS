<?php

namespace App\Domain\Platform\Services;

use App\Domain\Notifications\PlatformHealthAlertNotification;
use App\Domain\Platform\Enums\HealthStatus;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * El panel, pero al revés: en vez de que tú lo mires, él te busca.
 *
 * Solo avisa cuando **cambia** lo que está mal. Un correo cada hora diciendo lo mismo
 * es un correo que se aprende a ignorar, y una alerta que se ignora es exactamente
 * igual a no tener alerta. También avisa cuando todo vuelve a la normalidad: saber que
 * se arregló vale tanto como saber que se rompió.
 */
class HealthWatchService
{
    private const STATE_KEY = 'platform.health.last_notified_state';

    public function __construct(private readonly SystemHealthService $health) {}

    /**
     * @return array{notified: bool, recovered: bool, problems: int}
     */
    public function run(): array
    {
        $problems = array_values(array_filter(
            $this->health->checks(),
            // `Unknown` también entra: un chequeo que no se pudo ejecutar es una pregunta
            // sin responder, y en producción eso hay que mirarlo.
            fn (array $check): bool => $check['status'] !== HealthStatus::Ok,
        ));

        $state = $this->fingerprint($problems);
        $previous = Cache::get(self::STATE_KEY);

        if ($state === $previous) {
            return ['notified' => false, 'recovered' => false, 'problems' => count($problems)];
        }

        $recipients = $this->recipients();

        if ($recipients->isEmpty()) {
            Cache::forever(self::STATE_KEY, $state);

            return ['notified' => false, 'recovered' => false, 'problems' => count($problems)];
        }

        // Se pasó de tener problemas a no tener ninguno.
        $recovered = $problems === [] && $previous !== null && $previous !== '';

        if ($problems === [] && ! $recovered) {
            Cache::forever(self::STATE_KEY, $state);

            return ['notified' => false, 'recovered' => false, 'problems' => 0];
        }

        Notification::send(
            $recipients,
            new PlatformHealthAlertNotification($problems, recovered: $recovered),
        );

        Cache::forever(self::STATE_KEY, $state);

        return ['notified' => true, 'recovered' => $recovered, 'problems' => count($problems)];
    }

    /** Los superadministradores activos: los únicos que pueden hacer algo al respecto. */
    private function recipients()
    {
        return User::where('is_super_admin', true)
            ->where('is_active', true)
            ->get();
    }

    /**
     * La huella de «qué está mal ahora». Cambia el estado de un chequeo → cambia la
     * huella → llega un aviso. Sigue igual → silencio.
     *
     * @param  list<array{key: string, status: HealthStatus}>  $problems
     */
    private function fingerprint(array $problems): string
    {
        $parts = array_map(
            fn (array $problem): string => $problem['key'].':'.$problem['status']->value,
            $problems,
        );

        sort($parts);

        return implode('|', $parts);
    }
}
