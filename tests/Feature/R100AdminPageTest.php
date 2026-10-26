<?php

use App\Domain\FruitPurchasing\Services\OilPriceService;
use App\Livewire\FruitPurchasing\OilPriceAdmin;
use App\Models\PlatformSetting;
use App\Models\User;
use Livewire\Livewire;

/**
 * /r100/admin: solo un super admin publica el precio de la tonelada de aceite
 * que la calculadora pública usa para todos los proveedores.
 */
beforeEach(function (): void {
    $this->superAdmin = User::factory()->create(['is_super_admin' => true, 'is_active' => true, 'name' => 'Alex']);
});

it('manda al login a quien no ha iniciado sesión', function () {
    $this->get(route('r100.admin'))->assertRedirect();

    expect(auth()->check())->toBeFalse();
});

it('no deja entrar a un usuario que no es super admin', function () {
    $this->actingAs(User::factory()->create(['is_super_admin' => false]))
        ->get(route('r100.admin'))
        ->assertForbidden();
});

it('no deja entrar a un super admin desactivado', function () {
    $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => false]))
        ->get(route('r100.admin'))
        ->assertForbidden();
});

it('abre la página para un super admin', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('r100.admin'))
        ->assertOk()
        ->assertSee('Publicar un precio nuevo')
        ->assertSee('Todavía no hay precio publicado');
});

it('publica el precio escrito con puntos de mil y guarda quién y cuándo', function () {
    $this->travelTo('2026-10-25 09:30');
    $this->actingAs($this->superAdmin);

    Livewire::test(OilPriceAdmin::class)
        ->set('newPrice', '4.250.000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('newPrice', '')
        ->assertSet('saved', true)
        ->assertSee('$ 4.250.000')
        ->assertSee('Publicado el 25/10/2026')
        ->assertSee('por Alex');

    $setting = PlatformSetting::find(OilPriceService::SETTING_KEY);

    expect($setting->value['price'])->toEqual(4250000)
        ->and($setting->updated_by)->toBe($this->superAdmin->id);

    // La calculadora pública ya lo usa.
    $this->get(route('r100.calculator'))->assertSee('$ 4.250.000');
});

it('actualiza la fecha aunque se vuelva a publicar el mismo precio', function () {
    $this->actingAs($this->superAdmin);
    $this->travelTo('2026-10-25 09:00');
    app(OilPriceService::class)->publish(4_000_000, $this->superAdmin);

    $this->travelTo('2026-10-27 08:00');
    Livewire::test(OilPriceAdmin::class)
        ->set('newPrice', '4.000.000')
        ->call('save')
        ->assertSee('Publicado el 27/10/2026');
});

it('rechaza precios que parecen errores de digitación', function (string $typed, string $message) {
    $this->actingAs($this->superAdmin);

    Livewire::test(OilPriceAdmin::class)
        ->set('newPrice', $typed)
        ->call('save')
        ->assertHasErrors('newPrice')
        ->assertSee($message)
        ->assertSet('saved', false);

    expect(PlatformSetting::find(OilPriceService::SETTING_KEY))->toBeNull();
})->with([
    'el precio del kilo' => ['4000', '¿Escribió el precio del kilo?'],
    'un cero de más' => ['400.000.000', 'Revise que no tenga un cero de más.'],
    'texto' => ['cuatro millones', 'Escriba el precio en pesos'],
]);

it('no deja publicar desde Livewire a quien no es super admin', function () {
    $this->actingAs(User::factory()->create(['is_super_admin' => false]));

    Livewire::test(OilPriceAdmin::class)->assertForbidden();

    expect(PlatformSetting::find(OilPriceService::SETTING_KEY))->toBeNull();
});
