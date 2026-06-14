<?php

use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Domain\Webhooks\Services\WebhookDispatcher;
use App\Jobs\DeliverWebhookJob;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\Queue;

// ── Helpers ────────────────────────────────────────────────────────────────────

function webhookTenant(): Tenant
{
    return Tenant::factory()->create();
}

function activeSubscription(string $tenantId, array $events = ['alert.created']): WebhookSubscription
{
    $user = User::factory()->create(['is_active' => true]);

    return WebhookSubscription::forceCreate([
        'tenant_id' => $tenantId,
        'url' => 'https://example.com/webhook',
        'events' => $events,
        'secret' => bin2hex(random_bytes(32)),
        'is_active' => true,
        'failure_count' => 0,
        'created_by' => $user->id,
    ]);
}

// ── dispatch() ─────────────────────────────────────────────────────────────────

it('enqueue DeliverWebhookJob para cada subscripción activa que coincide con el evento', function () {
    Queue::fake();

    $tenant = webhookTenant();
    activeSubscription($tenant->id, ['alert.created', 'alert.resolved']);
    activeSubscription($tenant->id, ['alert.created']);

    app(WebhookDispatcher::class)->dispatch(
        WebhookEvent::AlertCreated->value,
        ['id' => 'abc', 'title' => 'Test'],
        $tenant->id,
    );

    Queue::assertPushed(DeliverWebhookJob::class, 2);
});

it('no enqueue nada si ninguna subscripción coincide con el evento', function () {
    Queue::fake();

    $tenant = webhookTenant();
    activeSubscription($tenant->id, ['work_order.created']);

    app(WebhookDispatcher::class)->dispatch(
        WebhookEvent::AlertCreated->value,
        ['id' => 'abc'],
        $tenant->id,
    );

    Queue::assertNotPushed(DeliverWebhookJob::class);
});

it('no enqueue subscripciones inactivas', function () {
    Queue::fake();

    $tenant = webhookTenant();
    $user = User::factory()->create(['is_active' => true]);

    WebhookSubscription::forceCreate([
        'tenant_id' => $tenant->id,
        'url' => 'https://example.com/webhook',
        'events' => ['alert.created'],
        'secret' => bin2hex(random_bytes(32)),
        'is_active' => false,
        'failure_count' => 0,
        'created_by' => $user->id,
    ]);

    app(WebhookDispatcher::class)->dispatch(
        WebhookEvent::AlertCreated->value,
        [],
        $tenant->id,
    );

    Queue::assertNotPushed(DeliverWebhookJob::class);
});

it('aislamiento de tenant: solo enqueue subscripciones del tenant correcto', function () {
    Queue::fake();

    $tenantA = webhookTenant();
    $tenantB = webhookTenant();

    activeSubscription($tenantA->id, ['alert.created']);
    activeSubscription($tenantB->id, ['alert.created']);

    app(WebhookDispatcher::class)->dispatch(
        WebhookEvent::AlertCreated->value,
        [],
        $tenantA->id,
    );

    Queue::assertPushed(DeliverWebhookJob::class, 1);
});

it('el job se encola en la queue webhooks', function () {
    Queue::fake();

    $tenant = webhookTenant();
    activeSubscription($tenant->id, ['alert.created']);

    app(WebhookDispatcher::class)->dispatch(WebhookEvent::AlertCreated->value, [], $tenant->id);

    Queue::assertPushedOn('webhooks', DeliverWebhookJob::class);
});
