<?php

use App\Domain\Commercial\Enums\DispatchStatus;
use App\Domain\Commercial\Services\CommercialNotices;
use App\Domain\Commercial\Services\CommercialPlanReport;
use App\Domain\Commercial\Services\OilTankService;
use App\Domain\Notifications\InboxNotification;
use App\Domain\Notifications\NotificationKind;
use App\Filament\Pages\CuadroCruce;
use App\Filament\Resources\Commercial\SalesDispatch\Pages\ListSalesDispatches;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Customer;
use App\Models\OilTank;
use App\Models\SalesDispatch;
use App\Models\SalesOffer;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * Fase 4 del módulo comercial: la cartera por antigüedad —cada factura vence en su fecha
 * más el plazo de pago del cliente— y los avisos diarios por la campana.
 *
 * Hoy es el 18 de noviembre de 2026.
 */

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-18 12:00'));

    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);

    $persona = function (string $rol): User {
        $user = User::factory()->create(['is_active' => true]);
        $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
        $user->assignRole($rol);

        return $user;
    };

    $this->directora = $persona('direccion-administrativa');
    $this->proceso = $persona('proceso');
    $this->administrador = $persona('administrador-general');

    $this->manuelita = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Manuelita', 'payment_terms_days' => 30]);
    $this->biod = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'BioD', 'payment_terms_days' => 15]);
});

function facturaDel(string $fecha, Customer $cliente, float $valor = 100_000_000): SalesDispatch
{
    return SalesDispatch::factory()->invoiced($valor)->create(['customer_id' => $cliente->id, 'loaded_on' => $fecha, 'invoiced_on' => $fecha]);
}

it('ages the receivables by days past due, with each customer payment terms', function (): void {
    $alDia = facturaDel('2026-11-10', $this->manuelita);
    $vencida18 = facturaDel('2026-10-01', $this->manuelita);
    facturaDel('2026-08-20', $this->manuelita);
    facturaDel('2026-06-01', $this->manuelita, 50_000_000);
    // BioD paga a 15 días: la del 25 de octubre ya venció el 9 de noviembre.
    $biod = facturaDel('2026-10-25', $this->biod);
    // Pagada: no es cartera.
    SalesDispatch::factory()->paid()->create(['customer_id' => $this->manuelita->id, 'invoiced_on' => '2026-01-10']);

    expect($alDia->dueOn()->toDateString())->toBe('2026-12-10')
        ->and($alDia->daysOverdue())->toBe(0)
        ->and($vencida18->daysOverdue())->toBe(18)
        ->and($biod->fresh('customer')->daysOverdue())->toBe(9)
        ->and(SalesDispatch::query()->overdue()->count())->toBe(4);

    $cartera = app(CommercialPlanReport::class)->aging($this->tenant->id);
    expect($cartera[$this->manuelita->id])->toMatchArray([
        'current' => 100_000_000.0,
        'd30' => 100_000_000.0,
        // 60 días justos: el tramo 31–60.
        'd60' => 100_000_000.0,
        'd90' => 0.0,
        'over90' => 50_000_000.0,
        'total' => 350_000_000.0,
        'overdue' => 250_000_000.0,
        'invoices' => 4,
        'oldest_days' => 140,
    ])->and($cartera[$this->biod->id]['d30'])->toBe(100_000_000.0)
        // De la que más debe a la que menos.
        ->and(array_key_first($cartera))->toBe($this->manuelita->id);

    enElPanelDeLaDirectora();

    Livewire::test(CuadroCruce::class)
        ->assertSee('Cartera por antigüedad')
        ->assertSee('140 días vencida')
        ->assertSee('Vencido $350 M');

    Livewire::test(ListSalesDispatches::class, ['activeTab' => DispatchStatus::Facturado->value])
        ->assertSee('vencido $350 M')
        ->filterTable('overdue')
        ->assertCanSeeTableRecords([$vencida18, $biod])
        ->assertCanNotSeeTableRecords([$alDia]);
});

it('warns the director once about new overdue invoices, loads left uninvoiced and offers with one truck left', function (): void {
    Notification::fake();

    facturaDel('2026-10-01', $this->manuelita);
    facturaDel('2026-10-25', $this->biod, 80_000_000);
    facturaDel('2026-11-10', $this->manuelita);
    // Recibido hace 8 días sin factura: se avisa; el de ayer todavía no.
    SalesDispatch::factory()->unloaded(34)->create(['customer_id' => $this->biod->id, 'loaded_on' => '2026-11-09', 'unloaded_on' => '2026-11-10']);
    SalesDispatch::factory()->unloaded(34)->create(['customer_id' => $this->biod->id, 'loaded_on' => '2026-11-16', 'unloaded_on' => '2026-11-17']);
    // Ofertas: a una le queda una tractomula; a otra, mucho.
    $casi = SalesOffer::factory()->create(['customer_id' => $this->manuelita->id, 'number' => 'O-1', 'contracted_tons' => 102]);
    SalesDispatch::factory()->unloaded(34)->create(['customer_id' => $this->manuelita->id, 'sales_offer_id' => $casi->id]);
    SalesDispatch::factory()->unloaded(34)->create(['customer_id' => $this->manuelita->id, 'sales_offer_id' => $casi->id]);
    SalesOffer::factory()->create(['customer_id' => $this->manuelita->id, 'number' => 'O-2', 'contracted_tons' => 500]);

    $enviados = app(CommercialNotices::class)->send();

    expect($enviados)->toBe(['overdue' => 2, 'uninvoiced' => 1, 'offers' => 1, 'tanks' => 0]);

    Notification::assertSentTo($this->directora, InboxNotification::class, fn (InboxNotification $n): bool => $n->title === 'Se vencieron 2 facturas sin pagar'
        && $n->kind === NotificationKind::Commercial
        && str_contains((string) $n->body, '$180,0 M')
        && str_contains((string) $n->body, 'Manuelita (1), BioD (1)'));
    Notification::assertSentTo($this->directora, InboxNotification::class, fn (InboxNotification $n): bool => $n->title === 'Un cargue recibido sin facturar');
    Notification::assertSentTo($this->directora, InboxNotification::class, fn (InboxNotification $n): bool => $n->title === 'A una oferta le queda un cargue'
        && str_contains((string) $n->body, 'O-1 (Manuelita): quedan 34,00 tn'));
    // Las ventas son de la Directora: ni proceso ni el administrador general.
    Notification::assertNotSentTo([$this->proceso, $this->administrador], InboxNotification::class);

    // Al día siguiente no se repite nada.
    expect(app(CommercialNotices::class)->send())->toBe(['overdue' => 0, 'uninvoiced' => 0, 'offers' => 0, 'tanks' => 0]);
});

it('warns the director, not process, when the tanks go unmeasured and when the acidity is high, once each', function (): void {
    Notification::fake();
    $tanque = OilTank::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Tanque 1']);
    $tanques = app(OilTankService::class);

    $tanques->record($this->tenant->id, CarbonImmutable::parse('2026-11-10'), [$tanque->id => ['level' => 300, 'acidity' => 4]], null);

    expect(app(CommercialNotices::class)->send()['tanks'])->toBe(1)
        ->and(app(CommercialNotices::class)->send()['tanks'])->toBe(0);
    Notification::assertSentTo($this->directora, InboxNotification::class, fn (InboxNotification $n): bool => $n->title === 'Los tanques llevan 8 días sin medir'
        && $n->kind === NotificationKind::Production);
    // Proceso ya no ve producción (2026-11-19).
    Notification::assertNotSentTo($this->proceso, InboxNotification::class);

    // Se midió hoy, con la acidez alta.
    $tanques->record($this->tenant->id, CarbonImmutable::parse('2026-11-18'), [$tanque->id => ['level' => 320, 'acidity' => '5,6']], null);

    expect(app(CommercialNotices::class)->send()['tanks'])->toBe(1)
        ->and(app(CommercialNotices::class)->send()['tanks'])->toBe(0);
    Notification::assertSentTo($this->directora, InboxNotification::class, fn (InboxNotification $n): bool => $n->title === 'Acidez alta en los tanques'
        && str_contains((string) $n->body, 'Tanque 1 5,60 %'));
});

it('runs every morning from the scheduler', function (): void {
    $this->artisan('commercial:notify')->assertSuccessful();
});

function enElPanelDeLaDirectora(): void
{
    test()->actingAs(test()->directora);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant(test()->tenant);
}
