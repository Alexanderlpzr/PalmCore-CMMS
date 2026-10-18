<?php

use App\Domain\Platform\Services\LogReaderService;
use App\Filament\Pages\Auth\Login;
use App\Filament\Platform\Pages\BackupsPage;
use App\Filament\Platform\Pages\GlobalLogsPage;
use App\Filament\Platform\Pages\ObservabilityPage;
use App\Filament\Platform\Pages\PlatformDashboard;
use App\Models\FailedJob;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * El panel de plataforma es la única pantalla del sistema que puede ver a todas las
 * empresas a la vez. Que solo entre quien debe no es una preferencia: es lo que separa
 * un panel de administración de una fuga de datos entre clientes.
 */
beforeEach(function (): void {
    $this->superAdmin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->tenant = Tenant::factory()->create(['name' => 'Extractora El Pajuil']);

    Filament::setCurrentPanel(Filament::getPanel('platform'));
});

// ── Quién entra ──────────────────────────────────────────────────────────────

it('lets the super admin into the machine room', function (): void {
    // `fresh()` trae el modelo entero de la base: la factory no escribe `deleted_at`, y
    // el modo estricto de Eloquent revienta al leer un atributo que nunca se cargó.
    $this->actingAs($this->superAdmin->fresh())
        ->followingRedirects()
        ->get('/platform')
        ->assertSuccessful();
});

it('keeps a normal user out of the platform panel, sending them to their own', function (): void {
    $user = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);
    $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    // No es una pantalla más: desde aquí se ven los datos de todos los clientes. Antes
    // recibía un 403 en blanco y se quedaba ahí; ahora va a su panel sin ver nada.
    $this->actingAs($user->fresh())
        ->get('/platform/platform-dashboard')
        ->assertRedirect(url('admin'));

    $this->get('/platform/tenants')->assertRedirect(url('admin'));
});

it('still answers 403 to anything but opening a page', function (): void {
    // La redirección es solo para visitas; detrás sigue EnsureSuperAdmin.
    $user = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);
    $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    $this->actingAs($user->fresh())
        ->getJson('/platform/tenants')
        ->assertForbidden();
});

it('sends a company admin who signs in on the platform login to their own panel', function (): void {
    // El caso del 403: salir de la plataforma deja en su pantalla de entrada, y ahí
    // entraba después el administrador de una empresa.
    $user = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);
    $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    expect(auth()->id())->toBe($user->id);

    $this->get('/platform/platform-dashboard')->assertRedirect(url('admin'));
});

// ── Dashboard ────────────────────────────────────────────────────────────────

it('shows every company and whether it is still alive', function (): void {
    $this->actingAs($this->superAdmin);

    Livewire::test(PlatformDashboard::class)
        ->assertOk()
        ->assertSee('Extractora El Pajuil')
        // Nadie ha entrado nunca a esta empresa recién creada, y el panel lo dice.
        ->assertSee('Nunca entró nadie');
});

it('shouts when the scheduler stopped running', function (): void {
    Cache::put('platform.scheduler.heartbeat', now()->subHours(2)->toISOString());

    $this->actingAs($this->superAdmin);

    Livewire::test(PlatformDashboard::class)
        ->assertOk()
        ->assertSee('Hay algo roto que necesita atención ahora.')
        ->assertSee('El scheduler dejó de correr');
});

// ── Colas y trabajos fallidos ────────────────────────────────────────────────

it('lists the failed jobs nobody would ever have read', function (): void {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'redis',
        'queue' => 'maintenance',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\GeneratePreventiveWorkOrdersJob']),
        'exception' => "RuntimeException: el plan no existe\n#0 /app/foo.php(1)",
        'failed_at' => now(),
    ]);

    $this->actingAs($this->superAdmin);

    Livewire::test(ObservabilityPage::class)
        ->assertOk()
        ->assertSee('GeneratePreventiveWorkOrdersJob')
        ->assertSee('el plan no existe');
});

it('can discard a failed job from the panel', function (): void {
    $uuid = (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'redis',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\DeliverWebhookJob']),
        'exception' => 'RuntimeException: 500 del endpoint',
        'failed_at' => now(),
    ]);

    $this->actingAs($this->superAdmin);

    $job = FailedJob::query()->firstOrFail();

    Livewire::test(ObservabilityPage::class)
        ->callAction(TestAction::make('forget')->table($job))
        ->assertHasNoActionErrors();

    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse();
});

it('names the queues Horizon watches', function (): void {
    $this->actingAs($this->superAdmin);

    Livewire::test(ObservabilityPage::class)
        ->assertOk()
        // La cola del generador de preventivos, que durante meses no tuvo worker.
        ->assertSee('maintenance');
});

// ── Respaldos y errores ──────────────────────────────────────────────────────

it('does not pretend a missing backup is fine', function (): void {
    $this->actingAs($this->superAdmin);

    // Sin respaldo no hay un estado neutro: si la base se cae hoy, se pierde todo.
    Livewire::test(BackupsPage::class)
        ->assertOk()
        ->assertSee('No hay ningún respaldo');
});

it('renders the recent errors page', function (): void {
    $this->actingAs($this->superAdmin);

    Livewire::test(GlobalLogsPage::class)->assertOk();
});

it('shows the log times in Colombia time, not the UTC they are written in', function (): void {
    $this->actingAs($this->superAdmin);

    $this->mock(LogReaderService::class)
        ->shouldReceive('recent')
        ->andReturn([[
            'at' => Carbon::parse('2026-10-07 18:25:18', 'UTC'),
            'level' => 'ERROR',
            'message' => 'Algo se rompió',
            'context' => 'Algo se rompió',
        ]]);

    Livewire::test(GlobalLogsPage::class)
        ->assertSee('07/10/2026 13:25:18')
        ->assertDontSee('07/10/2026 18:25:18')
        ->assertSee('Horas en hora de Colombia.');
});

it('does not report Livewire requests tampered with by hand', function (): void {
    Exceptions::fake();

    // Un escáner que escribe en una propiedad bloqueada o manda un número como notificación.
    report(new CannotUpdateLockedPropertyException('userUndertakingMultiFactorAuthentication'));
    report(new TypeError('Filament\Notifications\Collection::{closure:Filament\Notifications\Collection::fromLivewire():32}(): Argument #1 ($notification) must be of type array, int given'));

    // Un TypeError de verdad, de nuestro propio código, sí tiene que llegar al log.
    report(new TypeError('App\Domain\Maintenance\Services\Foo::bar(): Argument #1 must be of type int'));

    Exceptions::assertNotReported(CannotUpdateLockedPropertyException::class);
    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(fn (TypeError $e): bool => str_contains($e->getMessage(), 'Foo::bar'));
});
