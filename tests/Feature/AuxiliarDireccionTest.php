<?php

use App\Domain\Commercial\Enums\DispatchStatus;
use App\Domain\Commercial\Services\CommercialNotices;
use App\Domain\Commercial\Services\InvoiceEditor;
use App\Domain\Commercial\Services\InvoiceHistory;
use App\Domain\Notifications\InboxNotification;
use App\Filament\Pages\CostoDePersonal;
use App\Filament\Pages\CuadroCruce;
use App\Filament\Pages\FrutaRecibida;
use App\Filament\Pages\Inicio;
use App\Filament\Pages\PlanAnual;
use App\Filament\Pages\TableroDireccion;
use App\Filament\Pages\TanquesAceite;
use App\Filament\Resources\Commercial\Customer\CustomerResource;
use App\Filament\Resources\Commercial\SalesDispatch\Pages\EditSalesDispatch;
use App\Filament\Resources\Commercial\SalesDispatch\Pages\ListSalesDispatches;
use App\Filament\Resources\Commercial\SalesDispatch\Pages\ViewSalesDispatch;
use App\Filament\Resources\Commercial\SalesDispatch\SalesDispatchResource;
use App\Filament\Resources\Commercial\SalesOffer\SalesOfferResource;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Customer;
use App\Models\Role;
use App\Models\SalesDispatch;
use App\Models\SalesOffer;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * La auxiliar de la Directora Administrativa: ve los despachos y lleva la factura de cada
 * uno (número, fecha, valor y estado). Nada más.
 *
 * Hoy es el 20 de noviembre de 2026.
 */

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00'));

    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);

    $persona = function (string $rol, string $nombre): User {
        $user = User::factory()->create(['is_active' => true, 'name' => $nombre]);
        $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
        $user->assignRole($rol);

        return $user;
    };

    $this->auxiliar = $persona('auxiliar-direccion-administrativa', 'Auxiliar');
    $this->directora = $persona('direccion-administrativa', 'Directora');
    $this->cliente = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'payment_terms_days' => 30]);

    $this->enElPanel = function (User $user): void {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->tenant);
    };
});

it('sees the dispatches and nothing else of the commercial area', function (): void {
    ($this->enElPanel)($this->auxiliar);
    $despacho = SalesDispatch::factory()->invoiced()->create(['customer_id' => $this->cliente->id]);

    expect(Role::humanizeName('auxiliar-direccion-administrativa'))->toBe('Auxiliar de Dirección Administrativa')
        ->and(SalesDispatchResource::canViewAny())->toBeTrue()
        ->and(SalesDispatchResource::canView($despacho))->toBeTrue()
        ->and(SalesDispatchResource::canCreate())->toBeFalse()
        ->and(SalesDispatchResource::canEdit($despacho))->toBeFalse()
        ->and(SalesDispatchResource::canDelete($despacho))->toBeFalse()
        ->and(CuadroCruce::canAccess())->toBeFalse()
        ->and(PlanAnual::canAccess())->toBeFalse()
        ->and(FrutaRecibida::canAccess())->toBeFalse()
        ->and(TanquesAceite::canAccess())->toBeFalse()
        ->and(TableroDireccion::canAccess())->toBeFalse()
        ->and(CostoDePersonal::canAccess())->toBeFalse()
        ->and(CustomerResource::canViewAny())->toBeFalse()
        ->and(SalesOfferResource::canViewAny())->toBeFalse();

    Livewire::test(Inicio::class)->assertRedirect(SalesDispatchResource::getUrl('index'));

    Livewire::test(ListSalesDispatches::class)
        ->assertOk()
        ->assertActionHidden('importar')
        ->assertActionHidden('create');

    Livewire::test(EditSalesDispatch::class, ['record' => $despacho->getRouteKey()])->assertForbidden();
});

it('keeps the director on her cross table', function (): void {
    ($this->enElPanel)($this->directora);

    expect(CuadroCruce::canAccess())->toBeTrue();

    Livewire::test(Inicio::class)->assertRedirect(CuadroCruce::getUrl());
});

it('does not link the assistant to an offer she cannot open', function (): void {
    ($this->enElPanel)($this->auxiliar);
    $oferta = SalesOffer::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->cliente->id, 'number' => 'OM-77']);
    $despacho = SalesDispatch::factory()->invoiced()->create(['customer_id' => $this->cliente->id, 'sales_offer_id' => $oferta->id]);

    Livewire::test(ViewSalesDispatch::class, ['record' => $despacho->getRouteKey()])
        ->assertOk()
        ->assertSee('OM-77')
        ->assertDontSeeHtml(SalesOfferResource::getUrl('view', ['record' => $oferta], isAbsolute: false));
});

it('edits only the four invoice fields', function (): void {
    ($this->enElPanel)($this->auxiliar);
    $despacho = SalesDispatch::factory()->invoiced(100_000_000)->create([
        'customer_id' => $this->cliente->id,
        'invoice_number' => '1500',
        'invoiced_on' => '2026-11-01',
        'departure_weight_tons' => 35,
    ]);

    Livewire::test(ViewSalesDispatch::class, ['record' => $despacho->getRouteKey()])
        ->assertActionVisible('editarFactura')
        ->assertActionHidden('edit')
        ->callAction('editarFactura', data: [
            'invoice_number' => ' 1501 ',
            'invoiced_on' => '2026-11-02',
            'invoice_amount' => 120_000_000,
            'invoice_status' => DispatchStatus::Pagado->value,
            'paid_on' => '2026-11-19',
            'departure_weight_tons' => 1,
        ])
        ->assertHasNoActionErrors();

    $despacho->refresh();

    expect($despacho->invoice_number)->toBe('1501')
        ->and($despacho->invoiced_on->toDateString())->toBe('2026-11-02')
        ->and((float) $despacho->invoice_amount)->toBe(120_000_000.0)
        ->and($despacho->paid_on->toDateString())->toBe('2026-11-19')
        ->and($despacho->status)->toBe(DispatchStatus::Pagado)
        ->and((float) $despacho->departure_weight_tons)->toBe(35.0);
});

it('registers the invoice of an unloaded dispatch, but not of one still on the road', function (): void {
    ($this->enElPanel)($this->auxiliar);
    $porFacturar = SalesDispatch::factory()->unloaded()->create(['customer_id' => $this->cliente->id]);
    $enTransito = SalesDispatch::factory()->create(['customer_id' => $this->cliente->id]);

    Livewire::test(ViewSalesDispatch::class, ['record' => $enTransito->getRouteKey()])
        ->assertActionHidden('editarFactura');

    Livewire::test(ViewSalesDispatch::class, ['record' => $porFacturar->getRouteKey()])
        ->assertActionHidden('registrarFactura')
        ->callAction('editarFactura', data: [
            'invoice_number' => '1600',
            'invoiced_on' => '2026-11-20',
            'invoice_amount' => 90_000_000,
            'invoice_status' => DispatchStatus::Facturado->value,
        ])
        ->assertHasNoActionErrors();

    expect($porFacturar->refresh()->status)->toBe(DispatchStatus::Facturado);
});

it('does not repeat the register button for the director', function (): void {
    ($this->enElPanel)($this->directora);
    $porFacturar = SalesDispatch::factory()->unloaded()->create(['customer_id' => $this->cliente->id]);

    Livewire::test(ViewSalesDispatch::class, ['record' => $porFacturar->getRouteKey()])
        ->assertActionVisible('registrarFactura')
        ->assertActionHidden('editarFactura');
});

it('returns an imported invoice paid without a date to receivable', function (): void {
    $despacho = SalesDispatch::factory()->invoiced()->create([
        'customer_id' => $this->cliente->id,
        'status' => DispatchStatus::Pagado,
        'overdue_notified_at' => now(),
    ]);

    expect($despacho->status)->toBe(DispatchStatus::Pagado)
        ->and($despacho->isPaidWithoutDate())->toBeTrue();

    app(InvoiceEditor::class)->update($despacho, [
        'invoice_number' => $despacho->invoice_number,
        'invoiced_on' => $despacho->invoiced_on->toDateString(),
        'invoice_amount' => $despacho->invoice_amount,
        'invoice_status' => DispatchStatus::Facturado->value,
    ]);

    $despacho->refresh();

    expect($despacho->status)->toBe(DispatchStatus::Facturado)
        ->and($despacho->paid_on)->toBeNull()
        ->and($despacho->overdue_notified_at)->toBeNull();

    $despacho->update(['notes' => 'Sigue por cobrar']);

    expect($despacho->refresh()->status)->toBe(DispatchStatus::Facturado);
});

it('keeps an imported paid invoice without a date when only its number is corrected', function (): void {
    $despacho = SalesDispatch::factory()->invoiced()->create(['customer_id' => $this->cliente->id, 'status' => DispatchStatus::Pagado]);

    app(InvoiceEditor::class)->update($despacho, [
        'invoice_number' => '1999',
        'invoiced_on' => $despacho->invoiced_on->toDateString(),
        'invoice_amount' => $despacho->invoice_amount,
        'invoice_status' => DispatchStatus::Pagado->value,
        'paid_on' => null,
    ]);

    expect($despacho->refresh()->status)->toBe(DispatchStatus::Pagado)
        ->and($despacho->invoice_number)->toBe('1999')
        ->and($despacho->paid_on)->toBeNull();
});

it('asks for the payment date when an invoice becomes paid', function (): void {
    $despacho = SalesDispatch::factory()->invoiced()->create(['customer_id' => $this->cliente->id]);

    expect(fn () => app(InvoiceEditor::class)->update($despacho, [
        'invoice_number' => $despacho->invoice_number,
        'invoiced_on' => $despacho->invoiced_on->toDateString(),
        'invoice_amount' => $despacho->invoice_amount,
        'invoice_status' => DispatchStatus::Pagado->value,
    ]))->toThrow(ValidationException::class);

    ($this->enElPanel)($this->auxiliar);

    Livewire::test(ViewSalesDispatch::class, ['record' => $despacho->getRouteKey()])
        ->callAction('editarFactura', data: [
            'invoice_status' => DispatchStatus::Pagado->value,
            'paid_on' => null,
        ])
        ->assertHasActionErrors(['paid_on' => 'required']);
});

it('clears the overdue notice when the invoice date moves', function (): void {
    $despacho = SalesDispatch::factory()->invoiced()->create(['customer_id' => $this->cliente->id, 'invoiced_on' => '2026-09-01', 'overdue_notified_at' => now()]);

    app(InvoiceEditor::class)->update($despacho, [
        'invoice_number' => $despacho->invoice_number,
        'invoiced_on' => '2026-11-15',
        'invoice_amount' => $despacho->invoice_amount,
        'invoice_status' => DispatchStatus::Facturado->value,
    ]);

    expect($despacho->refresh()->overdue_notified_at)->toBeNull();
});

it('warns about a repeated invoice number but saves it', function (): void {
    ($this->enElPanel)($this->auxiliar);
    SalesDispatch::factory()->invoiced()->count(2)->create(['customer_id' => $this->cliente->id, 'invoice_number' => '1210']);
    $despacho = SalesDispatch::factory()->invoiced()->create(['customer_id' => $this->cliente->id, 'invoice_number' => '1300']);

    expect(app(InvoiceEditor::class)->otherLoadsWithNumber($despacho, '1210'))->toBe(2)
        ->and(app(InvoiceEditor::class)->otherLoadsWithNumber($despacho, '1300'))->toBe(0);

    Livewire::test(ViewSalesDispatch::class, ['record' => $despacho->getRouteKey()])
        ->mountAction('editarFactura')
        ->fillForm(['invoice_number' => '1210'])
        ->assertMountedActionModalSee('ya está en otros 2 cargues')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($despacho->refresh()->invoice_number)->toBe('1210');
});

it('does not let the director erase the number of a paid invoice', function (): void {
    ($this->enElPanel)($this->directora);
    $despacho = SalesDispatch::factory()->paid()->create(['customer_id' => $this->cliente->id]);

    Livewire::test(EditSalesDispatch::class, ['record' => $despacho->getRouteKey()])
        ->fillForm(['invoice_number' => null, 'invoice_amount' => null, 'invoiced_on' => null])
        ->call('save')
        ->assertHasFormErrors(['invoice_number' => 'required']);
});

it('shows the director who changed what, and not the assistant', function (): void {
    ($this->enElPanel)($this->auxiliar);
    $despacho = SalesDispatch::factory()->invoiced(100_000_000)->create([
        'customer_id' => $this->cliente->id,
        'invoice_number' => '1500',
        'invoiced_on' => '2026-11-01',
    ]);

    app(InvoiceEditor::class)->update($despacho, [
        'invoice_number' => '1501',
        'invoiced_on' => '2026-11-02',
        'invoice_amount' => 120_000_000,
        'invoice_status' => DispatchStatus::Pagado->value,
        'paid_on' => '2026-11-19',
    ]);
    $despacho->update(['notes' => 'Un cambio que no es de la factura']);
    app()->terminate();

    $lineas = app(InvoiceHistory::class)->lines($despacho->refresh());

    expect($lineas)->toHaveCount(1)
        ->and($lineas[0])->toContain('Auxiliar')
        ->toContain('Número: 1500 → 1501')
        ->toContain('Fecha: 01/11/2026 → 02/11/2026')
        ->toContain('Valor: $100.000.000 → $120.000.000')
        ->toContain('Estado: Por cobrar → Pagado')
        ->toContain('Fecha de pago: — → 19/11/2026');

    Livewire::test(ViewSalesDispatch::class, ['record' => $despacho->getRouteKey()])
        ->assertDontSee('Historial de la factura');

    ($this->enElPanel)($this->directora);

    Livewire::test(ViewSalesDispatch::class, ['record' => $despacho->getRouteKey()])
        ->assertSee('Historial de la factura')
        ->assertSee('Número: 1500 → 1501');
});

it('gets the invoice notices but not the offer ones', function (): void {
    Notification::fake();

    SalesDispatch::factory()->invoiced()->create(['customer_id' => $this->cliente->id, 'invoiced_on' => '2026-09-01']);
    SalesDispatch::factory()->unloaded()->create(['customer_id' => $this->cliente->id, 'unloaded_on' => '2026-11-10']);

    app(CommercialNotices::class)->send();

    Notification::assertSentTo($this->auxiliar, InboxNotification::class, fn (InboxNotification $n): bool => $n->title === 'Se venció una factura sin pagar');
    Notification::assertSentTo($this->auxiliar, InboxNotification::class, fn (InboxNotification $n): bool => $n->title === 'Un cargue recibido sin facturar');
    Notification::assertSentTo($this->directora, InboxNotification::class, fn (InboxNotification $n): bool => $n->title === 'Se venció una factura sin pagar');
    Notification::assertNotSentTo($this->auxiliar, InboxNotification::class, fn (InboxNotification $n): bool => str_contains($n->title, 'oferta'));
});
