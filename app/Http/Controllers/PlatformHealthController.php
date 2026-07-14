<?php

namespace App\Http\Controllers;

use App\Domain\Platform\Enums\HealthStatus;
use App\Domain\Platform\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * El punto ciego del panel: cuando la aplicación se cae del todo, el panel tampoco
 * carga y el vigilante horario tampoco corre. Esto es lo que un monitor externo
 * (UptimeRobot y compañía) puede preguntar cada minuto para enterarse antes que nadie.
 *
 * Es distinto de `/health`, que mira si la aplicación **respira** (base, caché, disco)
 * y por eso lo consulta Railway para decidir si reinicia el contenedor. Este mira si la
 * plataforma **funciona**: si el scheduler corre, si el respaldo de anoche existe, si
 * hay trabajos encolados que nadie procesa. Reiniciar el contenedor no arregla nada de
 * eso, así que vive en su propia puerta.
 *
 * Es público —un healthcheck detrás de login no sirve para nada— y por eso **no dice de
 * qué se está muriendo**: devuelve el estado de cada chequeo y nada más. Los mensajes de
 * excepción y los nombres de las colas se quedan dentro, donde solo los ve quien tiene
 * sesión.
 */
class PlatformHealthController extends Controller
{
    public function __invoke(SystemHealthService $health): JsonResponse
    {
        // Los chequeos leen Redis y listan el disco de respaldos: cachearlos evita que
        // un monitor cada minuto —o alguien con curiosidad— los convierta en carga.
        $payload = Cache::remember('platform.health.public', 60, function () use ($health): array {
            $status = $health->overallStatus();

            return [
                'status' => $status->value,
                'checks' => array_map(
                    fn (array $check): array => [
                        'key' => $check['key'],
                        'status' => $check['status']->value,
                    ],
                    $health->checks(),
                ),
                'timestamp' => now()->toISOString(),
            ];
        });

        return response()->json(
            $payload,
            // Solo lo crítico devuelve 503. Un aviso no debe disparar una alarma a las
            // tres de la mañana: para eso está la severidad.
            $payload['status'] === HealthStatus::Critical->value ? 503 : 200,
        );
    }
}
