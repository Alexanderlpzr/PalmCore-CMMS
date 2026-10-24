<?php

use App\Livewire\FruitPurchasing\TeaCalculator;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Ejemplo 3 de la presentación R-100: viaje neto de 20.000 kg con 2.000 kg de
 * fruta suelta limpia → TPR 18.000 kg, TEA 20,24 %. A $4.000.000 la tonelada de
 * aceite vale $16.189.556.
 */
function exampleThreeTrip(): Testable
{
    return Livewire::test(TeaCalculator::class)
        ->set('oilPricePerTon', '4000000')
        ->set('netWeight', '20000')
        ->set('looseFruitKilos', '2000')
        ->set('impurityPercent', '0')
        ->set('ripe', '17890')
        ->set('overripe', '30')
        ->set('abnormal', '80');
}

it('abre la calculadora R-100 sin iniciar sesión', function () {
    $this->get(route('r100.calculator'))
        ->assertOk()
        ->assertSee('<title>Método R-100', false)
        ->assertSee('¿Cuánto vale su fruta?')
        ->assertSee('¿Cuánto pesó su viaje NETO?')
        ->assertSee('Escriba el porcentaje de impureza')
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
        ->assertSee('Escriba cuánto pesó su viaje neto.');
});

it('saca el peso de racimos y el % de fruta suelta del neto y los kilos de fruta suelta', function () {
    $component = Livewire::test(TeaCalculator::class)
        ->set('netWeight', '20.000')
        ->set('looseFruitKilos', '2.000')
        ->assertSee('18.000 kg')
        ->assertSee('10,0 %');

    expect($component->instance()->bunchWeight)->toEqual(18000.0)
        ->and($component->instance()->looseFruitShare)->toEqual(0.10);
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

it('descuenta la impureza de la fruta suelta que dan en tolva', function () {
    // Ejemplo 2 de la presentación: 20.380 kg netos, 1.686 kg de fruta suelta con
    // 5 % de impureza → CFS 95 %, TEA 18,83 %.
    Livewire::test(TeaCalculator::class)
        ->set('netWeight', '20.380')
        ->set('looseFruitKilos', '1.686')
        ->set('impurityPercent', '5')
        ->set('ripe', '17912,91')
        ->set('overripe', '156,22')
        ->set('unripe', '52,07')
        ->set('abnormal', '190,93')
        ->set('rotten', '381,87')
        ->assertSee('18,83 %')
        ->assertSee('Puede mejorar')
        ->assertSee('Envíe la fruta suelta limpia');
});

it('califica como buena la TEA del ejemplo 2 con terapia (19,60 %)', function () {
    Livewire::test(TeaCalculator::class)
        ->set('netWeight', '19.950')
        ->set('looseFruitKilos', '1.794')
        ->set('impurityPercent', '5')
        ->set('ripe', '17964,99')
        ->set('abnormal', '191')
        ->assertSee('19,60 %')
        ->assertSee('Buena');
});

it('entiende los puntos de mil, la coma decimal y el signo de pesos', function () {
    Livewire::test(TeaCalculator::class)
        ->set('oilPricePerTon', '$ 4.000.000')
        ->set('netWeight', '20.000')
        ->set('looseFruitKilos', '2.000')
        ->set('ripe', '17.890')
        ->set('overripe', '30')
        ->set('abnormal', '80')
        ->set('industrialFactorPercent', '4,7')
        ->assertSee('$ 16.189.556');
});

it('muestra la TEA y pide el precio cuando no hay precio', function () {
    exampleThreeTrip()
        ->set('oilPricePerTon', '')
        ->assertSee('Su fruta rindió')
        ->assertSee('20,24 %')
        ->assertSee('Escriba arriba el precio de la tonelada');
});

it('pone como maduro los kilos de racimo que faltan por repartir', function () {
    Livewire::test(TeaCalculator::class)
        ->set('netWeight', '20.000')
        ->set('looseFruitKilos', '2.000')
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
        ->set('netWeight', '18000')
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
        ->set('netWeight', '18000')
        ->set('ripe', '17000')
        ->set('unripe', '1000')
        ->assertSee('Aguaterapia a los racimos verdes')
        ->assertSee('Podría ganar: + $ 400.000');
});

it('aconseja paloterapia y cortar el tallo cuando hay podrido y pedúnculo largo', function () {
    Livewire::test(TeaCalculator::class)
        ->set('netWeight', '18000')
        ->set('ripe', '17600')
        ->set('rotten', '400')
        ->set('longStalkCount', '12')
        ->assertSee('Paloterapia a los sobremaduros y podridos')
        ->assertSee('Corte el tallo al ras')
        ->assertSee('Podría ganar: + 0,10 puntos de TEA');
});

it('arma el mensaje de WhatsApp con el resultado', function () {
    $url = exampleThreeTrip()->instance()->whatsappUrl;

    expect($url)->toStartWith('https://wa.me/?text=')
        ->and(rawurldecode($url))->toContain('*Método R-100*')
        ->not->toContain('Fronda')
        ->toContain('Peso neto del viaje: 20.000 kg')
        ->toContain('20,24 %')
        ->toContain('$ 16.189.556')
        ->toContain(route('r100.calculator'));
});

it('empieza otro viaje sin borrar el precio', function () {
    exampleThreeTrip()
        ->call('startOver')
        ->assertSet('netWeight', '')
        ->assertSet('looseFruitKilos', '')
        ->assertSet('impurityPercent', '')
        ->assertSet('ripe', '')
        ->assertSet('oilPricePerTon', '4000000')
        ->assertDontSee('Usted recibiría');
});

it('no calcula con datos fuera de rango', function () {
    Livewire::test(TeaCalculator::class)
        ->set('netWeight', '18000')
        ->set('ripe', '18000')
        ->set('looseFruitKilos', '18000')
        ->assertSee('La fruta suelta no puede pesar lo mismo o más que todo el viaje.')
        ->assertDontSee('Usted recibiría')
        ->set('looseFruitKilos', '')
        ->set('impurityPercent', '120')
        ->assertSee('El porcentaje de impureza debe estar entre 0 y 100.')
        ->set('impurityPercent', '')
        ->set('longStalkCount', '2,5')
        ->assertSee('Los racimos con tallo largo se cuentan enteros');
});
