<?php

use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\DB;

it('saves secret encrypted and retrieves the original plaintext on read', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $plainSecret = bin2hex(random_bytes(32)); // 64-char hex — same format as Filament generates

    $subscription = WebhookSubscription::create([
        'tenant_id' => $tenant->id,
        'url' => 'https://hooks.example.com/test',
        'events' => [WebhookEvent::WorkOrderCreated->value],
        'secret' => $plainSecret,
        'is_active' => true,
        'failure_count' => 0,
        'created_by' => $user->id,
    ]);

    // The raw value stored in the DB must be encrypted (longer than the 64-char plain secret)
    $rawStored = DB::table('webhook_subscriptions')
        ->where('id', $subscription->id)
        ->value('secret');

    expect($rawStored)->not->toBe($plainSecret)
        ->and(strlen($rawStored))->toBeGreaterThan(64);

    // Reading through the model must decrypt back to the original value
    $fresh = WebhookSubscription::withoutGlobalScopes()->find($subscription->id);
    expect($fresh->secret)->toBe($plainSecret);

    // The HMAC produced with the retrieved secret must match one produced with the original
    $payload = json_encode(['event' => 'test'], JSON_UNESCAPED_UNICODE);
    $expectedSig = 'sha256='.hash_hmac('sha256', $payload, $plainSecret);
    $actualSig = 'sha256='.hash_hmac('sha256', $payload, $fresh->secret);
    expect($actualSig)->toBe($expectedSig);
});
