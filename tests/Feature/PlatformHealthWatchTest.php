<?php

use App\Domain\Notifications\PlatformHealthAlertNotification;
use App\Domain\Platform\Services\HealthWatchService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * El panel que hay que abrir no sirve de nada un viernes por la noche.
 *
 * El vigilante avisa solo cuando **cambia** lo que está mal: un correo cada hora
 * diciendo lo mismo es un correo que se aprende a ignorar, y una alerta ignorada es
 * exactamente igual a no tener alerta.
 */
beforeEach(function (): void {
    Notification::fake();
    Cache::forget('platform.health.last_notified_state');

    $this->superAdmin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
    $this->watch = app(HealthWatchService::class);

    // El sistema, sano: el scheduler acaba de latir.
    Cache::put('platform.scheduler.heartbeat', now()->toISOString());
});

/** El respaldo no existe en el entorno de pruebas, así que ese chequeo siempre está mal. */
function breakTheScheduler(): void
{
    Cache::put('platform.scheduler.heartbeat', now()->subHours(3)->toISOString());
}

it('warns the super admin when something breaks', function (): void {
    breakTheScheduler();

    $result = $this->watch->run();

    expect($result['notified'])->toBeTrue();

    Notification::assertSentTo($this->superAdmin, PlatformHealthAlertNotification::class);
});

it('does not send the same warning twice', function (): void {
    breakTheScheduler();

    $this->watch->run();
    Notification::fake();

    // Nada cambió: el problema sigue ahí y ya se avisó. Repetirlo enseña a ignorarlo.
    expect($this->watch->run()['notified'])->toBeFalse();

    Notification::assertNothingSent();
});

it('warns again when a new thing breaks on top', function (): void {
    breakTheScheduler();
    $this->watch->run();

    Notification::fake();

    // Aparece un segundo problema: la huella cambia y el aviso vuelve a salir.
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'redis',
        'queue' => 'maintenance',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\GeneratePreventiveWorkOrdersJob']),
        'exception' => 'RuntimeException: se cayó',
        'failed_at' => now(),
    ]);

    expect($this->watch->run()['notified'])->toBeTrue();

    Notification::assertSentTo($this->superAdmin, PlatformHealthAlertNotification::class);
});

it('says so when everything goes back to normal', function (): void {
    // Un sistema realmente sano necesita también un respaldo reciente, que en el entorno
    // de pruebas no existe: se finge uno, o el «todo bien» sería inalcanzable.
    Storage::fake('local');
    Storage::disk('local')->put(config('backup.backup.name').'/2026-07-13-fake.zip', 'zip');

    breakTheScheduler();
    $this->watch->run();

    Notification::fake();

    // El scheduler vuelve a latir. Saber que se arregló vale tanto como saber que se rompió.
    Cache::put('platform.scheduler.heartbeat', now()->toISOString());

    $result = $this->watch->run();

    expect($result['recovered'])->toBeTrue()
        ->and($result['problems'])->toBe(0);

    Notification::assertSentTo($this->superAdmin, PlatformHealthAlertNotification::class);
});

it('never warns a user who is not a super admin', function (): void {
    $regular = User::factory()->create(['is_super_admin' => false, 'is_active' => true]);

    breakTheScheduler();
    $this->watch->run();

    // No es solo ruido: el aviso dice qué está roto en la infraestructura.
    Notification::assertNotSentTo($regular, PlatformHealthAlertNotification::class);
});

it('never warns a deactivated super admin', function (): void {
    $former = User::factory()->create(['is_super_admin' => true, 'is_active' => false]);

    breakTheScheduler();
    $this->watch->run();

    Notification::assertNotSentTo($former, PlatformHealthAlertNotification::class);
});

// ── El endpoint que ve el monitor externo ────────────────────────────────────

it('answers the outside monitor without telling it what is wrong', function (): void {
    Cache::forget('platform.health.public');

    $response = $this->getJson('/health/platform');

    $response->assertJsonStructure(['status', 'checks' => [['key', 'status']], 'timestamp']);

    // Es público: no puede decir de qué se está muriendo. Ni rutas, ni excepciones,
    // ni nombres de colas — solo el estado de cada chequeo.
    foreach ($response->json('checks') as $check) {
        expect(array_keys($check))->toBe(['key', 'status']);
    }

    expect($response->json())->not->toHaveKey('detail');
});

it('returns 503 only when something is really broken', function (): void {
    Cache::forget('platform.health.public');
    breakTheScheduler();

    // Un scheduler muerto sí es crítico: los preventivos dejaron de generarse.
    $this->getJson('/health/platform')->assertStatus(503);
});
