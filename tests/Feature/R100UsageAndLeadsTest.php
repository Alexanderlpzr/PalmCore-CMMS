<?php

use App\Livewire\FruitPurchasing\InterestedSignup;
use App\Livewire\FruitPurchasing\OilPriceAdmin;
use App\Livewire\FruitPurchasing\TeaCalculator;
use App\Models\R100DailyUsage;
use App\Models\R100Lead;
use App\Models\User;
use Livewire\Livewire;

/**
 * El piloto R-100: cuántas veces se usa la calculadora (sin datos de la persona)
 * y los correos de los interesados, que solo ve un super admin.
 */
function todayUsage(): ?R100DailyUsage
{
    return R100DailyUsage::query()->whereDate('date', today())->first();
}

// ── Uso ──────────────────────────────────────────────────────────────────────

it('cuenta una visita cada vez que alguien abre la calculadora', function () {
    $this->get(route('r100.calculator'))->assertOk();
    $this->get(route('r100.calculator'))->assertOk();

    expect(todayUsage()->visits)->toBe(2)
        ->and(todayUsage()->calculations)->toBe(0);
});

it('no cuenta a los robots ni a la vista previa de WhatsApp', function (string $userAgent) {
    $this->get(route('r100.calculator'), ['User-Agent' => $userAgent])->assertOk();

    expect(todayUsage()?->visits ?? 0)->toBe(0);
})->with([
    'WhatsApp' => ['WhatsApp/2.23.20.0 A'],
    'Facebook' => ['facebookexternalhit/1.1'],
    'Google' => ['Mozilla/5.0 (compatible; Googlebot/2.1)'],
]);

it('no guarda nada de la persona al contar', function () {
    $this->get(route('r100.calculator'));

    expect(array_keys(todayUsage()->getAttributes()))
        ->toEqualCanonicalizing(['id', 'date', 'visits', 'calculations', 'created_at', 'updated_at']);
});

it('cuenta un viaje calculado una sola vez, aunque siga cambiando números', function () {
    $component = Livewire::test(TeaCalculator::class)
        ->set('netWeight', '18.000')
        ->set('ripe', '17.000');

    expect(todayUsage()->calculations)->toBe(0);

    $component->set('ripe', '18.000');
    expect(todayUsage()->calculations)->toBe(1);

    $component->set('looseFruitKilos', '0')->set('impurityPercent', '5')->set('oilPricePerTon', '4.000.000');
    expect(todayUsage()->calculations)->toBe(1);

    // Otro viaje sí cuenta.
    $component->call('startOver')->set('netWeight', '20.000')->set('ripe', '20.000');
    expect(todayUsage()->calculations)->toBe(2);
});

it('cuenta el viaje cuando se completa con el botón de poner lo que falta como maduro', function () {
    Livewire::test(TeaCalculator::class)
        ->set('netWeight', '18.000')
        ->set('rotten', '200')
        ->call('fillRipeWithRemainder');

    expect(todayUsage()->calculations)->toBe(1);
});

it('no deja que el navegador reinicie el contador del viaje', function () {
    expect(fn () => Livewire::test(TeaCalculator::class)->set('calculationCounted', false))
        ->toThrow(Exception::class);
});

it('oculta la fórmula y deja solo el aviso de que es un estimado', function () {
    $this->get(route('r100.calculator'))
        ->assertDontSee('Fórmula R-100')
        ->assertDontSee('RM·21%')
        ->assertSee('Es un estimado; el pago final sale de la calificación en planta y está sujeto a retenciones de parafiscales palmeros (Cesión, Fondo de Fomento).');
});

// ── Interesados ──────────────────────────────────────────────────────────────

it('muestra la casilla de correo para los interesados al pie de la calculadora', function () {
    $this->get(route('r100.calculator'))
        ->assertSee('¿Quiere saber más?')
        ->assertSee('Ley 1581 de 2012');
});

it('guarda el correo del interesado cuando autoriza', function () {
    Livewire::test(InterestedSignup::class)
        ->set('email', '  Juan.Perez@Gmail.com ')
        ->set('consent', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('¡Gracias! Quedó registrado')
        ->assertSee('juan.perez@gmail.com');

    $lead = R100Lead::query()->sole();

    expect($lead->email)->toBe('juan.perez@gmail.com')
        ->and($lead->consented_at)->not->toBeNull();
});

it('no guarda el correo sin la autorización', function () {
    Livewire::test(InterestedSignup::class)
        ->set('email', 'juan@gmail.com')
        ->call('save')
        ->assertHasErrors(['consent' => 'accepted'])
        ->assertSee('necesitamos que marque la autorización');

    expect(R100Lead::query()->count())->toBe(0);
});

it('avisa cuando el correo no es válido', function () {
    Livewire::test(InterestedSignup::class)
        ->set('email', 'juan arroba gmail')
        ->set('consent', true)
        ->call('save')
        ->assertHasErrors('email')
        ->assertSee('Ese correo no parece válido');

    expect(R100Lead::query()->count())->toBe(0);
});

it('no repite el correo si la misma persona lo deja dos veces', function () {
    foreach (['ana@gmail.com', 'ANA@gmail.com'] as $email) {
        Livewire::test(InterestedSignup::class)->set('email', $email)->set('consent', true)->call('save');
    }

    expect(R100Lead::query()->count())->toBe(1);
});

it('no guarda lo que llenan los robots en el campo trampa', function () {
    Livewire::test(InterestedSignup::class)
        ->set('email', 'robot@spam.com')
        ->set('consent', true)
        ->set('website', 'http://spam.example')
        ->call('save')
        ->assertSee('¡Gracias! Quedó registrado');

    expect(R100Lead::query()->count())->toBe(0);
});

it('frena a quien manda muchos correos seguidos', function () {
    foreach (range(1, 5) as $i) {
        Livewire::test(InterestedSignup::class)->set('email', "persona{$i}@gmail.com")->set('consent', true)->call('save');
    }

    Livewire::test(InterestedSignup::class)
        ->set('email', 'persona6@gmail.com')
        ->set('consent', true)
        ->call('save')
        ->assertHasErrors('email');

    expect(R100Lead::query()->count())->toBe(5);
});

// ── /r100/admin ──────────────────────────────────────────────────────────────

it('muestra al super admin el uso y la lista de interesados', function () {
    $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]));
    R100DailyUsage::factory()->create(['date' => today(), 'visits' => 12, 'calculations' => 7]);
    R100DailyUsage::factory()->create(['date' => today()->subDays(3), 'visits' => 3, 'calculations' => 1]);
    R100Lead::factory()->create(['email' => 'pedro@gmail.com']);

    Livewire::test(OilPriceAdmin::class)
        ->assertSee('Uso de la calculadora')
        ->assertSeeHtml('data-testid="total-visits">15<')
        ->assertSeeHtml('data-testid="total-calculations">8<')
        ->assertSee('pedro@gmail.com')
        ->assertSee('Descargar lista (CSV)');
});

it('deja borrar un correo de la lista', function () {
    $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]));
    $lead = R100Lead::factory()->create(['email' => 'borrar@gmail.com']);

    Livewire::test(OilPriceAdmin::class)
        ->call('deleteLead', $lead->id)
        ->assertDontSee('borrar@gmail.com');

    expect(R100Lead::query()->count())->toBe(0);
});

it('descarga la lista de interesados en CSV solo para super admin', function () {
    R100Lead::factory()->create(['email' => 'maria@gmail.com']);

    $this->get(route('r100.admin.leads'))->assertRedirect();

    $this->actingAs(User::factory()->create(['is_super_admin' => false]))
        ->get(route('r100.admin.leads'))
        ->assertForbidden();

    $response = $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]))
        ->get(route('r100.admin.leads'));

    $response->assertOk()->assertDownload('interesados-r100-'.now()->format('Y-m-d').'.csv');

    expect($response->streamedContent())->toStartWith("\xEF\xBB\xBF")
        ->toContain('Correo;')
        ->toContain('maria@gmail.com');
});
