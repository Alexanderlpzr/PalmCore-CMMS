<?php

use App\Domain\FruitPurchasing\Services\OilPriceService;
use App\Livewire\FruitPurchasing\TeaCalculator;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * El precio del aceite lo publica un super admin en /r100/admin; la calculadora
 * solo lo lee.
 */
function publishOilPrice(float $pricePerTon = 4_000_000): void
{
    $superAdmin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);

    app(OilPriceService::class)->publish($pricePerTon, $superAdmin);
}

/**
 * Ejemplo 3 de la presentación R-100: viaje neto de 20.000 kg con 2.000 kg de
 * fruta suelta limpia → TPR 18.000 kg. Con el FACIND definitivo de 4,1 % la TEA
 * es 20,84 % y, a $4.000.000 la tonelada de aceite, vale $16.669.556.
 */
function exampleThreeTrip(): Testable
{
    return Livewire::test(TeaCalculator::class)
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
        ->assertSee('¿Cuánto cuesta la tonelada de aceite (CPO) a precio (FEP)?')
        ->assertSee('¿Cuánto pesó su viaje NETO?')
        ->assertSee('Escriba el porcentaje de impureza')
        ->assertSee('images/r100/maduro.jpg')
        ->assertSee('alt="Extractora El Pajuil"', false)
        ->assertDontSeeText('Fronda');

    expect(auth()->check())->toBeFalse();
});

it('pone los ejemplos debajo de las casillas y no dentro, para que no parezcan llenas', function () {
    $this->get(route('r100.calculator'))
        ->assertSee('Ejemplo: 20.000 kg')
        ->assertSee('Ejemplo: 1.600 kg. Si no trajo, déjelo vacío.')
        ->assertSee('Ejemplo: 5 %')
        ->assertDontSee('placeholder=', false);
});

it('muestra cada tipo de racimo en una fila con su foto para ver en grande', function () {
    $response = $this->get(route('r100.calculator'));

    foreach (['ripe', 'overripe', 'unripe', 'abnormal', 'rotten'] as $bunch) {
        $response->assertSee('data-bunch-row="'.$bunch.'"', false);
    }

    $response->assertSee('Ver grande la foto del racimo podrido')
        ->assertSee('Toque en cualquier parte para cerrar');
});

it('marca en verde cada paso que ya está listo', function () {
    $component = Livewire::test(TeaCalculator::class)
        ->assertDontSee('Paso 1 listo');

    expect($component->instance()->completedSteps)->toBe([1 => false, 2 => false, 3 => false]);

    $component->set('netWeight', '20.000')->set('looseFruitKilos', '2.000')
        ->assertSee('Paso 1 listo')
        ->assertSee('Paso 2 listo')
        ->assertDontSee('Paso 3 listo');

    $component->set('ripe', '18.000')
        ->assertSee('Paso 3 listo');

    // Una impureza imposible deja el paso 2 (y por tanto el 3) sin terminar.
    $component->set('impurityPercent', '120');
    expect($component->instance()->completedSteps)->toBe([1 => true, 2 => false, 3 => false]);
});

it('trae la vista previa para cuando el enlace se comparte por WhatsApp', function () {
    $this->get(route('r100.calculator'))
        ->assertSee('<meta property="og:title" content="Método R-100 — ¿Cuánto vale su fruta?">', false)
        ->assertSee('<meta property="og:image" content="'.asset('images/r100/compartir.jpg').'">', false)
        ->assertSee('<meta property="og:url" content="'.route('r100.calculator').'">', false);

    expect(public_path('images/r100/compartir.jpg'))->toBeFile();
});

it('sigue pidiendo inicio de sesión para el resto de la aplicación', function () {
    $this->get('/admin')->assertRedirect();
});

it('trae el precio publicado ya escrito en la casilla, con su fecha', function () {
    $this->travelTo('2026-10-25 09:00');
    publishOilPrice(4_250_000);

    $this->get(route('r100.calculator'))
        ->assertOk()
        ->assertSee('id="oilPricePerTon"', false)
        ->assertSee('Precio publicado por la extractora el 25/10/2026')
        ->assertSee('$ 4.250.000')
        ->assertSee('Ejemplo: 4.000.000')
        ->assertSee('Precio de compra de la tonelada de aceite (CPO)');

    Livewire::test(TeaCalculator::class)->assertSet('oilPricePerTon', '4.250.000');
});

it('deja que el proveedor escriba su propio precio del aceite', function () {
    publishOilPrice(4_250_000);

    exampleThreeTrip()
        ->set('oilPricePerTon', '4.000.000')
        ->assertSee('$ 16.669.556');
});

it('avisa sin bloquear cuando el precio parece ser el del kilo', function () {
    exampleThreeTrip()
        ->set('oilPricePerTon', '4000')
        ->assertSee('Ese precio parece ser el del kilo.')
        ->assertSee('Usted recibiría')
        ->set('oilPricePerTon', '4.000.000')
        ->assertDontSee('Ese precio parece ser el del kilo.');
});

it('arranca con la casilla del precio vacía si nadie lo ha publicado', function () {
    Livewire::test(TeaCalculator::class)->assertSet('oilPricePerTon', '');
});

it('usa el precio de la configuración mientras nadie publique uno', function () {
    config(['palmcore.r100.oil_price_per_ton' => 4000000]);

    Livewire::test(TeaCalculator::class)
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
    publishOilPrice();

    exampleThreeTrip()
        ->assertSee('Usted recibiría')
        ->assertSee('$ 16.669.556')
        ->assertSee('20,84 %')
        ->assertSee('$ 833.478')
        ->assertSee('20.000 kg')
        ->assertSee('Excelente')
        ->assertSee('Todo cuadra');
});

it('descuenta la impureza de la fruta suelta que dan en tolva', function () {
    // Ejemplo 2 de la presentación: 20.380 kg netos, 1.686 kg de fruta suelta con
    // 5 % de impureza → CFS 95 %. TEA 18,83 % con FACIND 4,7 %; 19,43 % con 4,1 %.
    // La calificación no cambia con el FACIND: sigue en «Puede mejorar».
    Livewire::test(TeaCalculator::class)
        ->set('netWeight', '20.380')
        ->set('looseFruitKilos', '1.686')
        ->set('impurityPercent', '5')
        ->set('ripe', '17912,91')
        ->set('overripe', '156,22')
        ->set('unripe', '52,07')
        ->set('abnormal', '190,93')
        ->set('rotten', '381,87')
        ->assertSee('19,43 %')
        ->assertSee('Puede mejorar')
        ->assertSee('Envíe la fruta suelta limpia');
});

it('califica como buena la fruta del ejemplo 2 con terapia (20,20 % con FACIND 4,1 %)', function () {
    Livewire::test(TeaCalculator::class)
        ->set('netWeight', '19.950')
        ->set('looseFruitKilos', '1.794')
        ->set('impurityPercent', '5')
        ->set('ripe', '17964,99')
        ->set('abnormal', '191')
        ->assertSee('20,20 %')
        ->assertSee('Buena');
});

it('entiende los puntos de mil y la coma decimal', function () {
    publishOilPrice();

    Livewire::test(TeaCalculator::class)
        ->set('netWeight', '20.000')
        ->set('looseFruitKilos', '2.000')
        ->set('ripe', '17.890')
        ->set('overripe', '30')
        ->set('abnormal', '80,0')
        ->assertSee('$ 16.669.556');
});

it('no muestra el FACIND y el desglose sigue cuadrando con la TEA', function () {
    // Ejemplo 3: racimos 20,94 % − FACIND 4,10 % = 16,84 %; + fruta suelta 4,00 % = 20,84 %.
    exampleThreeTrip()
        ->assertDontSee('Costo de la planta')
        ->assertDontSee('FACIND)')
        ->assertSee('+ 16,84 %')
        ->assertSee('+ 4,00 %')
        ->assertSee('20,84 %');
});

it('no deja que el proveedor cambie el FACIND', function () {
    $this->get(route('r100.calculator'))
        ->assertDontSee('id="industrialFactorPercent"', false)
        ->assertDontSee('Datos de la planta');

    expect(fn () => Livewire::test(TeaCalculator::class)->set('industrialFactorPercent', '0'))
        ->toThrow(Exception::class);
});

it('muestra solo la TEA mientras no haya precio en la casilla', function () {
    exampleThreeTrip()
        ->assertSee('Escriba arriba el precio de la tonelada de aceite para ver cuánta plata es.')
        ->assertSee('Su fruta rindió')
        ->assertSee('20,84 %')
        ->assertDontSee('Usted recibiría');
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
    publishOilPrice();

    Livewire::test(TeaCalculator::class)
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
    publishOilPrice();

    Livewire::test(TeaCalculator::class)
        ->set('netWeight', '18000')
        ->set('ripe', '17000')
        ->set('unripe', '1000')
        ->assertSee('Aguaterapia a los racimos verdes')
        ->assertSee('Podría ganar: + $ 400.000');
});

it('aconseja paloterapia y cortar el tallo cuando hay podrido y pedúnculo largo', function () {
    // Sin precio, una ganancia que redondea a 0,00 puntos no se muestra.
    Livewire::test(TeaCalculator::class)
        ->set('netWeight', '18000')
        ->set('ripe', '17600')
        ->set('rotten', '400')
        ->set('longStalkCount', '12')
        ->assertSee('Paloterapia a los sobremaduros y podridos')
        ->assertSee('Corte el tallo al ras')
        ->assertDontSee('0,00 puntos de TEA');

    // Pedúnculo como en el Excel: 12 × 1,5 / 18.000 / 100 = 0,00001 de TEA.
    // 0,00001 × $4.000.000 × 18 t = $720.
    publishOilPrice();

    Livewire::test(TeaCalculator::class)
        ->set('netWeight', '18000')
        ->set('ripe', '17600')
        ->set('rotten', '400')
        ->set('longStalkCount', '12')
        ->assertSee('Podría ganar: + $ 720');
});

it('arma el mensaje de WhatsApp con el resultado y el precio usado', function () {
    $this->travelTo('2026-10-25 09:00');
    publishOilPrice();

    $url = exampleThreeTrip()->instance()->whatsappUrl;

    expect($url)->toStartWith('https://wa.me/?text=')
        ->and(rawurldecode($url))->toContain('*Método R-100*')
        ->not->toContain('Fronda')
        ->toContain('Peso neto del viaje: 20.000 kg')
        ->toContain('20,84 %')
        ->toContain('Precio del aceite usado: $ 4.000.000 la tonelada (publicado por la extractora el 25/10/2026)')
        ->toContain('$ 16.669.556')
        ->toContain(route('r100.calculator'));
});

it('no dice «publicado por la extractora» si el proveedor escribió otro precio', function () {
    publishOilPrice(4_250_000);

    $url = exampleThreeTrip()->set('oilPricePerTon', '4.000.000')->instance()->whatsappUrl;

    expect(rawurldecode($url))->toContain('Precio del aceite usado: $ 4.000.000 la tonelada')
        ->not->toContain('publicado por la extractora');
});

it('empieza otro viaje sin borrar el precio', function () {
    publishOilPrice();

    exampleThreeTrip()
        ->call('startOver')
        ->assertSet('netWeight', '')
        ->assertSet('looseFruitKilos', '')
        ->assertSet('impurityPercent', '')
        ->assertSet('ripe', '')
        ->assertSet('oilPricePerTon', '4.000.000')
        ->assertDontSee('Usted recibiría');
});

it('dice fruta y no pepa', function () {
    exampleThreeTrip()
        ->assertSee('La fruta que llega suelta, sin racimo.')
        ->assertSee('Cada fruto cuenta')
        ->assertDontSee('pepa');
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
