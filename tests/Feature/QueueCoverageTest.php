<?php

/**
 * Ninguna cola sin worker.
 *
 * Este test existe por un fallo real que estuvo meses en producción sin que nadie lo
 * viera: `GeneratePreventiveWorkOrdersJob` se despachaba a la cola `maintenance` y el
 * worker no la consumía. El job entraba a la cola y se quedaba ahí para siempre. No
 * falló, no hubo excepción, no hubo log — el programa preventivo, que es el corazón
 * del CMMS, simplemente nunca se generó, mientras el tablero decía que los planes
 * estaban «activos».
 *
 * Es el peor tipo de bug que existe: el que no hace ruido. Y es trivial de repetir —
 * basta con que alguien escriba `onQueue('nueva-cola')` y no toque el despliegue.
 *
 * Por eso el contrato se verifica aquí, contra los archivos reales: lo que los jobs
 * declaran tiene que estar en lo que el worker consume. Si vuelven a divergir, este
 * test se pone rojo antes de que el código llegue al servidor.
 */
it('consumes every queue the jobs actually dispatch to', function (): void {
    $declared = queuesDeclaredByJobs();
    $consumed = queuesConsumedByWorker();

    $orphans = array_diff($declared, $consumed);

    expect($orphans)->toBe(
        [],
        'Estas colas reciben trabajos que ningún worker procesa: '.implode(', ', $orphans).
        '. Se quedarán encolados para siempre, sin error. Añádelas al comando queue:work de docker/supervisord.conf.',
    );
});

it('does not watch queues that nobody dispatches to', function (): void {
    // Lo contrario también importa, aunque duela menos: una cola que ya nadie usa es un
    // worker gastando ciclos en el vacío y una lista que miente sobre lo que hace el
    // sistema.
    $stale = array_diff(queuesConsumedByWorker(), [...queuesDeclaredByJobs(), 'default']);

    expect($stale)->toBe(
        [],
        'El worker consume colas a las que ya nadie despacha: '.implode(', ', $stale),
    );
});

it('runs the scheduler that the whole system depends on', function (): void {
    // Sin scheduler no se generan preventivos, no se cierra el mes, no se levantan
    // alertas y no se respalda la base. Y no falla: simplemente no ocurre.
    expect(supervisordConfig())->toContain('schedule:work');
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function supervisordConfig(): string
{
    return file_get_contents(base_path('docker/supervisord.conf'));
}

/**
 * Las colas a las que la aplicación despacha trabajo de verdad: las que los jobs
 * declaran con `onQueue('…')` y la de auditoría, que no la nombra ningún job porque
 * viene de la configuración.
 *
 * @return list<string>
 */
function queuesDeclaredByJobs(): array
{
    $queues = [(string) config('palmcore.audit.queue')];

    $files = [
        ...glob(app_path('Jobs/*.php')),
        ...glob(app_path('Listeners/*.php')),
    ];

    foreach ($files as $file) {
        if (preg_match_all("/onQueue\('([a-z0-9_-]+)'\)/i", (string) file_get_contents($file), $matches)) {
            $queues = [...$queues, ...$matches[1]];
        }
    }

    sort($queues);

    return array_values(array_unique(array_filter($queues)));
}

/**
 * Las colas que el worker de producción consume, leídas del comando real.
 *
 * @return list<string>
 */
function queuesConsumedByWorker(): array
{
    preg_match('/queue:work\s+--queue=([a-z0-9_,-]+)/i', supervisordConfig(), $matches);

    $queues = explode(',', $matches[1] ?? '');

    sort($queues);

    return array_values(array_filter($queues));
}
