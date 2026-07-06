<?php

use App\Models\EquipmentDocument;

// Carbon 3 changed diffInX() to return a signed difference by default (Carbon 2
// always returned an absolute value). expires_at->diffInDays(now()) computed
// now() minus expires_at, which is negative for any future date — so
// "negative <= $days" was always true, flagging every document with a future
// expiration as "expiring soon" regardless of how far away it actually was.

it('is not expiring soon when the expiration is far in the future', function () {
    $document = EquipmentDocument::factory()->create([
        'expires_at' => now()->addYear(),
    ]);

    expect($document->isExpiringSoon())->toBeFalse();
});

it('is expiring soon when the expiration is within the given window', function () {
    $document = EquipmentDocument::factory()->create([
        'expires_at' => now()->addDays(10),
    ]);

    expect($document->isExpiringSoon(30))->toBeTrue();
});

it('is not expiring soon once already expired (isExpired handles that case instead)', function () {
    $document = EquipmentDocument::factory()->expired()->create();

    expect($document->isExpiringSoon())->toBeFalse()
        ->and($document->isExpired())->toBeTrue();
});
