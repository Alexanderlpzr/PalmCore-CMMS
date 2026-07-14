<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Enums\HealthStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * ¿Está viva la plataforma ahora mismo?
 *
 * Es la pregunta que el superadministrador se hace un lunes por la mañana, y hasta
 * hoy la única forma de responderla era entrar por SSH. Cada chequeo devuelve lo que
 * midió, no una carita verde: «último backup hace 4 h» se puede auditar, «OK» no.
 *
 * Lo que no sabe, lo dice. Un chequeo que no puede ejecutarse (Redis apagado, disco
 * inaccesible) devuelve `Unknown`, nunca `Ok`: un panel que inventa buenas noticias
 * es peor que no tener panel.
 */
class SystemHealthService
{
    /** Cada cuántos minutos late el scheduler. Si el latido se enfría, está muerto. */
    private const SCHEDULER_HEARTBEAT_KEY = 'platform.scheduler.heartbeat';

    private const SCHEDULER_STALE_MINUTES = 15;

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     status: HealthStatus,
     *     value: string,
     *     detail: ?string,
     * }>
     */
    public function checks(): array
    {
        return [
            $this->database(),
            $this->cache(),
            $this->scheduler(),
            $this->failedJobs(),
            $this->orphanQueues(),
            $this->backups(),
            $this->storage(),
        ];
    }

    /** El peor estado de todos: lo que el semáforo de la cabecera debe mostrar. */
    public function overallStatus(): HealthStatus
    {
        $statuses = array_column($this->checks(), 'status');

        foreach ([HealthStatus::Critical, HealthStatus::Warning, HealthStatus::Unknown] as $status) {
            if (in_array($status, $statuses, strict: true)) {
                return $status;
            }
        }

        return HealthStatus::Ok;
    }

    // ── Chequeos ──────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function database(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $ms = (int) round((microtime(true) - $start) * 1000);

            return $this->check(
                'database',
                'Base de datos',
                $ms > 500 ? HealthStatus::Warning : HealthStatus::Ok,
                "{$ms} ms",
                $ms > 500 ? 'La base responde lenta.' : null,
            );
        } catch (Throwable $e) {
            return $this->check('database', 'Base de datos', HealthStatus::Critical, 'Sin conexión', $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function cache(): array
    {
        try {
            $probe = 'platform.health.probe';
            Cache::put($probe, 1, 10);
            $alive = Cache::get($probe) === 1;

            return $this->check(
                'cache',
                'Caché',
                $alive ? HealthStatus::Ok : HealthStatus::Critical,
                $alive ? 'Responde' : 'No devuelve lo que se le escribe',
            );
        } catch (Throwable $e) {
            return $this->check('cache', 'Caché', HealthStatus::Critical, 'Sin conexión', $e->getMessage());
        }
    }

    /**
     * El scheduler es de quien dependen los preventivos, el cierre de KPIs y las
     * alertas de horómetro. Si deja de correr no hay error en ningún lado: el sistema
     * simplemente deja de hacer cosas. Por eso late cada cinco minutos.
     *
     * @return array<string, mixed>
     */
    private function scheduler(): array
    {
        $beat = Cache::get(self::SCHEDULER_HEARTBEAT_KEY);

        if ($beat === null) {
            return $this->check(
                'scheduler',
                'Tareas programadas',
                HealthStatus::Unknown,
                'Sin latido',
                'Nunca ha latido, o la caché se limpió. Si el sistema lleva más de 5 minutos arriba, el scheduler no está corriendo.',
            );
        }

        $at = Carbon::parse($beat);
        $minutes = (int) $at->diffInMinutes(now());

        return $this->check(
            'scheduler',
            'Tareas programadas',
            $minutes > self::SCHEDULER_STALE_MINUTES ? HealthStatus::Critical : HealthStatus::Ok,
            $minutes < 1 ? 'Hace menos de un minuto' : "Hace {$minutes} min",
            $minutes > self::SCHEDULER_STALE_MINUTES
                ? 'El scheduler dejó de correr: los preventivos no se generan y el cierre mensual no se ejecuta.'
                : null,
        );
    }

    /** @return array<string, mixed> */
    private function failedJobs(): array
    {
        try {
            $total = DB::table('failed_jobs')->count();
            $lastDay = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count();
        } catch (Throwable $e) {
            return $this->check('failed_jobs', 'Trabajos fallidos', HealthStatus::Unknown, 'No se pudo leer', $e->getMessage());
        }

        return $this->check(
            'failed_jobs',
            'Trabajos fallidos',
            match (true) {
                $lastDay > 0 => HealthStatus::Critical,
                $total > 0 => HealthStatus::Warning,
                default => HealthStatus::Ok,
            },
            (string) $total,
            $lastDay > 0 ? "{$lastDay} fallaron en las últimas 24 h." : null,
        );
    }

    /**
     * La cola huérfana: trabajos encolados que ningún worker atiende.
     *
     * Es el fallo más traicionero de todos, porque no produce ningún error. El job se
     * despacha, entra a Redis y se queda ahí para siempre. Pasó de verdad: el
     * generador de preventivos escribía a la cola `maintenance` y Horizon no tenía
     * supervisor para ella, así que el programa preventivo llevaba meses sin
     * generarse y no había una sola línea de log que lo dijera.
     *
     * @return array<string, mixed>
     */
    private function orphanQueues(): array
    {
        try {
            $watched = $this->supervisedQueues();
            $orphans = [];

            foreach ($this->queuesWithPendingJobs() as $queue => $size) {
                if (! in_array($queue, $watched, strict: true)) {
                    $orphans[] = "{$queue} ({$size})";
                }
            }
        } catch (Throwable $e) {
            return $this->check('orphan_queues', 'Colas sin worker', HealthStatus::Unknown, 'No se pudo leer Redis', $e->getMessage());
        }

        if ($orphans === []) {
            return $this->check(
                'orphan_queues',
                'Colas sin worker',
                HealthStatus::Ok,
                'Ninguna',
                'Todas las colas con trabajos tienen un supervisor que las atiende.',
            );
        }

        return $this->check(
            'orphan_queues',
            'Colas sin worker',
            HealthStatus::Critical,
            implode(', ', $orphans),
            'Hay trabajos encolados que ningún worker de Horizon procesa. Se quedarán ahí para siempre, sin error.',
        );
    }

    /** @return array<string, mixed> */
    private function backups(): array
    {
        $disk = config('backup.backup.destination.disks.0', 'local');
        $name = config('backup.backup.name');

        try {
            $files = collect(Storage::disk($disk)->files($name))
                ->filter(fn (string $path): bool => str_ends_with($path, '.zip'));

            if ($files->isEmpty()) {
                return $this->check(
                    'backups',
                    'Respaldos',
                    HealthStatus::Critical,
                    'Ninguno',
                    "No hay un solo respaldo en el disco «{$disk}».",
                );
            }

            $latest = $files
                ->map(fn (string $path): array => [
                    'path' => $path,
                    'at' => Carbon::createFromTimestamp(Storage::disk($disk)->lastModified($path)),
                ])
                ->sortByDesc('at')
                ->first();

            $hours = (int) $latest['at']->diffInHours(now());
        } catch (Throwable $e) {
            return $this->check('backups', 'Respaldos', HealthStatus::Unknown, 'No se pudo leer el disco', $e->getMessage());
        }

        return $this->check(
            'backups',
            'Respaldos',
            match (true) {
                $hours > 72 => HealthStatus::Critical,
                $hours > 36 => HealthStatus::Warning,
                default => HealthStatus::Ok,
            },
            $hours < 1 ? 'Hace menos de una hora' : "Hace {$hours} h",
            $hours > 36 ? 'El respaldo diario no se está ejecutando.' : null,
        );
    }

    /** @return array<string, mixed> */
    private function storage(): array
    {
        try {
            $probe = 'platform-health/'.uniqid().'.txt';
            Storage::disk('local')->put($probe, 'ok');
            $readable = Storage::disk('local')->get($probe) === 'ok';
            Storage::disk('local')->delete($probe);
        } catch (Throwable $e) {
            return $this->check('storage', 'Almacenamiento', HealthStatus::Critical, 'No se puede escribir', $e->getMessage());
        }

        return $this->check(
            'storage',
            'Almacenamiento',
            $readable ? HealthStatus::Ok : HealthStatus::Critical,
            $readable ? 'Escribe y lee' : 'Escribe pero no lee',
        );
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * Las colas que Horizon declara atender, en todos sus supervisores.
     *
     * @return list<string>
     */
    public function supervisedQueues(): array
    {
        $queues = [];

        foreach ((array) config('horizon.defaults', []) as $supervisor) {
            foreach ((array) ($supervisor['queue'] ?? []) as $queue) {
                $queues[] = $queue;
            }
        }

        return array_values(array_unique($queues));
    }

    /**
     * Las colas que hoy tienen trabajos esperando, leídas de Redis.
     *
     * @return array<string, int> nombre de la cola => trabajos pendientes
     */
    public function queuesWithPendingJobs(): array
    {
        $connection = config('queue.default');

        if ($connection !== 'redis') {
            // Con la cola en base de datos o en `sync` no hay colas huérfanas posibles:
            // no se puede afirmar nada, así que no se afirma nada.
            return [];
        }

        $prefix = (string) config('database.redis.options.prefix', '');
        $sizes = [];

        foreach (Redis::connection(config('queue.connections.redis.connection', 'default'))->keys('queues:*') as $key) {
            $key = str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key;

            // Horizon guarda además `queues:x:delayed`, `:reserved` y `:notify`.
            if (! preg_match('/^queues:([^:]+)$/', $key, $matches)) {
                continue;
            }

            $queue = $matches[1];
            $size = (int) Redis::connection(config('queue.connections.redis.connection', 'default'))->llen($key);

            if ($size > 0) {
                $sizes[$queue] = $size;
            }
        }

        return $sizes;
    }

    /** @return array<string, mixed> */
    private function check(
        string $key,
        string $label,
        HealthStatus $status,
        string $value,
        ?string $detail = null,
    ): array {
        return compact('key', 'label', 'status', 'value', 'detail');
    }
}
