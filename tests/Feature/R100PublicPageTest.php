<?php

use App\Livewire\FruitPurchasing\TeaCalculator;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Ejemplo 3 de la presentación R-100: TEA 20,24 %. A $4.000.000 la tonelada de
 * aceite y 20.000 kg de viaje, vale $16.189.556.
 */
function exampleThreeTrip(): Testable
{
    return Livewire::test(TeaCalculator::class)
        ->set('oilPricePerTon', '4000000')
        ->set('bunchWeight', '18000')
        ->set('ripe', '17890')
        ->set('overripe', '30')
        ->set('abnormal', '80')
        ->set('looseFruitPercent', '10')
        ->call('chooseLooseFruitQuality', '100');
}

it('abre la calculadora R-100 sin iniciar sesión', function () {
    $this->get(route('r100.calculator'))
        ->assertOk()
        ->assertSee('<title>Método R-100', false)
        ->assertSee('¿Cuánto vale su fruta?')
        ->assertSee('Precio de compra de la tonelada de aceite (CPO)')
        ->assertSee('images/r100/maduro.jpg')
        ->assertSee('alt="Extractora El Pajuil"', false)
        ->assertDontSeeText('Fronda');

    expect(auth()->check())->toBeFalse();
});

it('sigue pidiendo inicio de sesión para el resto de la aplicación', function () {
    $this->get('/admin')->assertRedirect();
});

it('arranca con el FACIND oficial y el precio configurado, escritos como en Colombia', function () {
    config(['palmcore.r100.oil_price_per_ton' => 4000000]);

    Livewire::test(TeaCalculator::class)
        ->assertSet('industrialFactorPercent', '4,7')
        ->assertSet('oilPricePerTon', '4.000.000')
        ->assertSee('Escriba cuántos kilos pesaron los racimos.');
});

it('calcula la TEA, el valor del viaje y la calidad', function () {
    exampleThreeTrip()
        ->assertSee('Usted recibiría')
        ->assertSee('$ 16.189.556')
        ->assertSee('20,24 %')
        ->assertSee('$ 809.478')
        ->assertSee('20.000 kg')
        ->assertSee('Excelente')
        ->assertSee('Todo cuadra');
});

it('entiende los puntos de mil, la coma decimal y el signo de pesos', function () {
    Livewire::test(TeaCalculator::class)
        ->set('oilPricePerTon', '$ 4.000.000')
        ->set('bunchWeight', '18.000')
        ->set('ripe', '17.890')
        ->set('overripe', '30')
        ->set('abnormal', '80')
        ->set('looseFruitPercent', '10')
        ->set('industrialFactorPercent', '4,7')
        ->call('chooseLooseFruitQuality', '100')
        ->assertSee('$ 16.189.556');
});

it('muestra la TEA y pide el precio cuando no hay precio', function () {
    exampleThreeTrip()
        ->set('oilPricePerTon', '')
        ->assertSee('Su fruta rindió')
        ->assertSee('20,24 %')
        ->assertSee('Escriba arriba el precio de la tonelada');
});

it('pone como maduro los kilos que faltan por repartir', function () {
    Livewire::test(TeaCalculator::class)
        ->set('bunchWeight', '18.000')
        ->set('overripe', '30')
        ->set('abnormal', '80')
        ->assertSee('Faltan 17.890 kg')
        ->call('fillRipeWithRemainder')
        ->assertSet('ripe', '17.890')
        ->assertSee('Todo cuadra');
});

it('no da resultado mientras los tipos de racimo no cuadren con el peso', function () {
    Livewire::test(TeaCalculator::class)
        ->set('oilPricePerTon', '4000000')
        ->set('bunchWeight', '18000')
        ->set('ripe', '17000')
        ->assertSee('Faltan 1.000 kg')
        ->assertSee('Primero haga cuadrar los kilos')
        ->assertDontSee('Usted recibiría')
        ->set('ripe', '19000')
        ->assertSee('Sobran 1.000 kg');
});

it('calcula cuánto ganaría con aguaterapia a los racimos verdes', function () {
    // 1.000 kg de verde masudo pasan de 11 % a 21 %: 1.000 × 10 % / 18.000 = 0,56 puntos.
    // 0,0055556 × $4.000.000 × 18 t = $400.000.
    Livewire::test(TeaCalculator::class)
        ->set('oilPricePerTon', '4000000')
        ->set('bunchWeight', '18000')
        ->set('ripe', '17000')
        ->set('unripe', '1000')
        ->assertSee('Aguaterapia a los racimos verdes')
        ->assertSee('Podría ganar: + $ 400.000');
});

it('aconseja paloterapia y cortar el tallo cuando hay podrido y pedúnculo largo', function () {
    Livewire::test(TeaCalculator::class)
        ->set('bunchWeight', '18000')
        ->set('ripe', '17600')
        ->set('rotten', '400')
        ->set('longStalkCount', '12')
        ->assertSee('Paloterapia a los sobremaduros y podridos')
        ->assertSee('Corte el tallo al ras')
        ->assertSee('Podría ganar: + 0,10 puntos de TEA');
});

it('califica con los ejemplos de la presentación', function () {
    // Ejemplo 2 (mucho RP y RSM): 18,83 % → Puede mejorar.
    Livewire::test(TeaCalculator::class)
        ->set('bunchWeight', '18694')
        ->set('ripe', '17912,91')
        ->set('overripe', '156,22')
        ->set('unripe', '52,07')
        ->set('abnormal', '190,93')
        ->set('rotten', '381,87')
        ->set('looseFruitPercent', '8,272816')
        ->call('chooseLooseFruitQuality', '95')
        ->assertSee('18,83 %')
        ->assertSee('Puede mejorar');

    // Ejemplo 2 con terapia: 19,60 % → Buena.
    Livewire::test(TeaCalculator::class)
        ->set('bunchWeight', '18156')
        ->set('ripe', '17964,99')
        ->set('abnormal', '191')
        ->set('looseFruitPercent', '8,992481')
        ->call('chooseLooseFruitQuality', '95')
        ->assertSee('19,60 %')
        ->assertSee('Buena');
});

it('arma el mensaje de WhatsApp con el resultado', function () {
    $url = exampleThreeTrip()->instance()->whatsappUrl;

    expect($url)->toStartWith('https://wa.me/?text=')
        ->and(rawurldecode($url))->toContain('*Método R-100*')
        ->not->toContain('Fronda')
        ->toContain('20,24 %')
        ->toContain('$ 16.189.556')
        ->toContain(route('r100.calculator'));
});

it('empieza otro viaje sin borrar el precio', function () {
    exampleThreeTrip()
        ->call('startOver')
        ->assertSet('bunchWeight', '')
        ->assertSet('ripe', '')
        ->assertSet('looseFruitQualityPercent', '100')
        ->assertSet('oilPricePerTon', '4000000')
        ->assertDontSee('Usted recibiría');
});

it('no calcula con datos fuera de rango', function () {
    Livewire::test(TeaCalculator::class)
        ->set('bunchWeight', '18000')
        ->set('ripe', '18000')
        ->set('looseFruitPercent', '120')
        ->assertSee('La fruta suelta debe ser un número entre 0 y 99.')
        ->assertDontSee('Usted recibiría')
        ->set('looseFruitPercent', '')
        ->set('longStalkCount', '2,5')
        ->assertSee('Los racimos con tallo largo se cuentan enteros');
});
