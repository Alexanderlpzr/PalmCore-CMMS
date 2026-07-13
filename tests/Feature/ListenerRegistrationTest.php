<?php

use App\Contracts\WebhookableEvent;
use App\Events\AlertCreated;
use Illuminate\Support\Facades\Event;

/**
 * Los listeners de app/Listeners los registra el autodescubrimiento de Laravel a
 * partir del type-hint de handle(). Registrarlos ADEMÁS en AppServiceProvider los
 * ataba dos veces: cada alerta notificaba dos veces al técnico y cada webhook se
 * entregaba dos veces al endpoint del cliente.
 */
it('binds every listener exactly once', function (string $event): void {
    $listeners = Event::getRawListeners()[$event] ?? [];

    expect($listeners)->toHaveCount(1);
})->with([
    'alertas' => AlertCreated::class,
    'webhooks' => WebhookableEvent::class,
]);
