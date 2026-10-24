<?php

use App\Livewire\FruitPurchasing\TeaCalculator;
use Livewire\Livewire;

it('abre la calculadora R-100 sin iniciar sesión', function () {
    $this->get(route('r100.calculator'))
        ->assertOk()
        ->assertSee('¿Cuánto vale su fruta?')
        ->assertSee('Precio de compra de la tonelada de aceite (CPO)');

    expect(auth()->check())->toBeFalse();
});

it('sigue pidiendo inicio de sesión para el resto de la aplicación', function () {
    $this->get('/admin')->assertRedirect();
});

it('arranca con el FACIND oficial y el precio configurado', function () {
    config(['palmcore.r100.oil_price_per_ton' => 4000000]);

    Livewire::test(TeaCalculator::class)
        ->assertSet('industrialFactorPercent', '4.7')
        ->assertSet('oilPricePerTon', '4000000')
        ->assertSee('Digite el total peso de racimos (TPR).');
});

it('calcula la TEA y el valor del viaje con los datos del proveedor', function () {
    Livewire::test(TeaCalculator::class)
        ->set('oilPricePerTon', '4000000')
        ->set('bunchWeight', '18000')
        ->set('ripe', '17890')
        ->set('overripe', '30')
        ->set('abnormal', '80')
        ->set('looseFruitPercent', '10')
        ->set('looseFruitQualityPercent', '100')
        ->assertSee('20,24 %')
        ->assertSee('$ 16.189.556')
        ->assertSee('$ 809.478')
        ->assertSee('20.000 kg')
        ->assertDontSee('Revise los pesos');
});

it('acepta coma decimal en lo digitado', function () {
    Livewire::test(TeaCalculator::class)
        ->set('bunchWeight', '18000')
        ->set('ripe', '17890')
        ->set('overripe', '30')
        ->set('abnormal', '80')
        ->set('looseFruitPercent', '10')
        ->set('industrialFactorPercent', '4,7')
        ->assertSee('20,24 %')
        ->assertSee('Falta el precio');
});

it('avisa cuando los tipos de racimo no cuadran con el TPR', function () {
    Livewire::test(TeaCalculator::class)
        ->set('bunchWeight', '18000')
        ->set('ripe', '17000')
        ->assertSee('Revise los pesos');
});

it('no calcula con datos fuera de rango', function () {
    Livewire::test(TeaCalculator::class)
        ->set('bunchWeight', '18000')
        ->set('ripe', '18000')
        ->set('looseFruitPercent', '120')
        ->set('longStalkCount', '2.5')
        ->assertSee('El % de fruta suelta debe estar entre 0 y 99.')
        ->assertSee('La cantidad de pedúnculo largo debe ser un número entero, sin decimales.')
        ->assertDontSee('Valor de su viaje');
});
