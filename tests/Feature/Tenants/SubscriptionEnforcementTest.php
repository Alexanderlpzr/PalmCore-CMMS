<?php

use App\Domain\Shared\Enums\SubscriptionStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * PR-4 — Subscription Enforcement: access rules, banners, and Gate::before.
 */

// ── Tenant model helpers ──────────────────────────────────────────────────────

it('effectiveSubscriptionStatus returns stored status when subscription has not expired', function () {
    $tenant = Tenant::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_expires_at' => now()->addDays(60),
    ]);

    expect($tenant->effectiveSubscriptionStatus())->toBe(SubscriptionStatus::Active);
});

it('effectiveSubscriptionStatus auto-downgrades to read_only when active subscription expires', function () {
    $tenant = Tenant::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_expires_at' => now()->subDay(),
    ]);

    expect($tenant->effectiveSubscriptionStatus())->toBe(SubscriptionStatus::ReadOnly);
});

it('effectiveSubscriptionStatus auto-downgrades trial to read_only on expiry', function () {
    $tenant = Tenant::factory()->create([
        'subscription_status' => SubscriptionStatus::Trial,
        'subscription_expires_at' => now()->subDay(),
    ]);

    expect($tenant->effectiveSubscriptionStatus())->toBe(SubscriptionStatus::ReadOnly);
});

it('effectiveSubscriptionStatus never auto-downgrades an already-suspended tenant', function () {
    $tenant = Tenant::factory()->create([
        'subscription_status' => SubscriptionStatus::Suspended,
        'subscription_expires_at' => now()->subDay(),
    ]);

    expect($tenant->effectiveSubscriptionStatus())->toBe(SubscriptionStatus::Suspended);
});

it('effectiveSubscriptionStatus defaults to active when subscription_status is null', function () {
    $tenant = Tenant::factory()->create(['subscription_expires_at' => null]);
    $tenant->subscription_status = null;

    expect($tenant->effectiveSubscriptionStatus())->toBe(SubscriptionStatus::Active);
});

it('daysUntilExpiry returns positive value for future expiry', function () {
    $tenant = Tenant::factory()->create([
        'subscription_expires_at' => now()->addDays(15),
    ]);

    expect($tenant->daysUntilExpiry())->toBe(15);
});

it('daysUntilExpiry returns negative value when already expired', function () {
    $tenant = Tenant::factory()->create([
        'subscription_expires_at' => now()->subDays(5),
    ]);

    expect($tenant->daysUntilExpiry())->toBe(-5);
});

it('daysUntilExpiry returns null when no expiry is set', function () {
    $tenant = Tenant::factory()->create(['subscription_expires_at' => null]);

    expect($tenant->daysUntilExpiry())->toBeNull();
});

it('isExpiringSoon returns true within 30 days', function () {
    $tenant = Tenant::factory()->create(['subscription_expires_at' => now()->addDays(7)]);

    expect($tenant->isExpiringSoon())->toBeTrue();
});

it('isExpiringSoon returns false when more than 30 days remain', function () {
    $tenant = Tenant::factory()->create(['subscription_expires_at' => now()->addDays(45)]);

    expect($tenant->isExpiringSoon())->toBeFalse();
});

it('isExpiringSoon returns false when already expired', function () {
    $tenant = Tenant::factory()->create(['subscription_expires_at' => now()->subDay()]);

    expect($tenant->isExpiringSoon())->toBeFalse();
});

// ── SubscriptionStatus enum ───────────────────────────────────────────────────

it('trial and active statuses allow mutations', function () {
    expect(SubscriptionStatus::Trial->allowsMutations())->toBeTrue()
        ->and(SubscriptionStatus::Active->allowsMutations())->toBeTrue();
});

it('read_only and suspended statuses block mutations', function () {
    expect(SubscriptionStatus::ReadOnly->allowsMutations())->toBeFalse()
        ->and(SubscriptionStatus::Suspended->allowsMutations())->toBeFalse();
});

it('bannerMessage returns null for active status', function () {
    expect(SubscriptionStatus::Active->bannerMessage())->toBeNull();
});

it('bannerMessage returns non-null messages for trial, read_only, and suspended', function () {
    expect(SubscriptionStatus::Trial->bannerMessage())->not->toBeNull()
        ->and(SubscriptionStatus::ReadOnly->bannerMessage())->not->toBeNull()
        ->and(SubscriptionStatus::Suspended->bannerMessage())->not->toBeNull();
});

// ── Gate::before enforcement ──────────────────────────────────────────────────

it('allows create ability when subscription status is active', function () {
    $user = User::factory()->create(['is_super_admin' => false]);
    app()->instance('subscription.status', SubscriptionStatus::Active);
    // Define a permissive rule so the Gate::before null (defer) resolves to allow.
    Gate::define('create', fn () => true);

    expect(Gate::forUser($user)->allows('create'))->toBeTrue();
});

it('allows create ability when subscription status is trial', function () {
    $user = User::factory()->create(['is_super_admin' => false]);
    app()->instance('subscription.status', SubscriptionStatus::Trial);
    Gate::define('create', fn () => true);

    expect(Gate::forUser($user)->allows('create'))->toBeTrue();
});

it('blocks create ability when subscription status is read_only', function () {
    $user = User::factory()->create(['is_super_admin' => false]);
    app()->instance('subscription.status', SubscriptionStatus::ReadOnly);

    expect(Gate::forUser($user)->denies('create'))->toBeTrue();
});

it('blocks update ability when subscription status is suspended', function () {
    $user = User::factory()->create(['is_super_admin' => false]);
    app()->instance('subscription.status', SubscriptionStatus::Suspended);

    expect(Gate::forUser($user)->denies('update'))->toBeTrue();
});

it('blocks delete ability when subscription status is read_only', function () {
    $user = User::factory()->create(['is_super_admin' => false]);
    app()->instance('subscription.status', SubscriptionStatus::ReadOnly);

    expect(Gate::forUser($user)->denies('delete'))->toBeTrue();
});

it('super admin bypasses subscription gate and can create when tenant is read_only', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    app()->instance('subscription.status', SubscriptionStatus::ReadOnly);

    expect(Gate::forUser($admin)->allows('create'))->toBeTrue();
});

it('super admin bypasses subscription gate when tenant is suspended', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    app()->instance('subscription.status', SubscriptionStatus::Suspended);

    expect(Gate::forUser($admin)->allows('delete'))->toBeTrue();
});

it('view and viewAny abilities are not in the blocked abilities list', function () {
    expect(in_array('view', SubscriptionStatus::BLOCKED_ABILITIES, strict: true))->toBeFalse()
        ->and(in_array('viewAny', SubscriptionStatus::BLOCKED_ABILITIES, strict: true))->toBeFalse();
});

it('read abilities pass through when subscription is read_only with a permissive policy', function () {
    $user = User::factory()->create(['is_super_admin' => false]);
    app()->instance('subscription.status', SubscriptionStatus::ReadOnly);
    Gate::define('view', fn () => true);

    expect(Gate::forUser($user)->allows('view'))->toBeTrue();
});

// ── Tenant model casts ────────────────────────────────────────────────────────

it('stores and retrieves subscription_status as SubscriptionStatus enum', function () {
    $tenant = Tenant::factory()->create([
        'subscription_status' => SubscriptionStatus::ReadOnly,
    ]);

    $fresh = Tenant::find($tenant->id);

    expect($fresh->subscription_status)->toBe(SubscriptionStatus::ReadOnly);
});

it('stores and retrieves subscription_expires_at as a Carbon date', function () {
    $date = now()->addDays(90)->startOfDay();

    $tenant = Tenant::factory()->create([
        'subscription_expires_at' => $date,
    ]);

    $fresh = Tenant::find($tenant->id);

    expect($fresh->subscription_expires_at)->not->toBeNull()
        ->and($fresh->subscription_expires_at->format('Y-m-d'))->toBe($date->format('Y-m-d'));
});
