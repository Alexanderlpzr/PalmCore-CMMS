<?php

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\SuperAdminGuard;
use Illuminate\Support\Facades\DB;

/**
 * Sprint SAFE-1 — guarantees at least one active Super Admin always exists.
 */

/** @return list<User> */
function makeSuperAdmins(int $count): array
{
    return User::factory()
        ->count($count)
        ->create(['is_super_admin' => true, 'is_active' => true])
        ->all();
}

function guard(): SuperAdminGuard
{
    return app(SuperAdminGuard::class);
}

// ── Delete ──────────────────────────────────────────────────────────────────

it('allows deleting a super admin when another active one remains', function () {
    [$a] = makeSuperAdmins(2);

    $a->delete();

    expect($a->fresh()->trashed())->toBeTrue();
});

it('blocks deleting the last active super admin (programmatic / API path)', function () {
    [$solo] = makeSuperAdmins(1);

    expect(fn () => $solo->delete())
        ->toThrow(BusinessRuleException::class, SuperAdminGuard::MESSAGE_DELETE);

    expect($solo->fresh()->trashed())->toBeFalse();
});

it('blocks force-deleting the last active super admin', function () {
    [$solo] = makeSuperAdmins(1);

    expect(fn () => $solo->forceDelete())
        ->toThrow(BusinessRuleException::class, SuperAdminGuard::MESSAGE_DELETE);

    expect(User::withTrashed()->whereKey($solo->getKey())->exists())->toBeTrue();
});

// ── Deactivate ────────────────────────────────────────────────────────────────

it('allows deactivating a super admin when another active one remains', function () {
    [$a] = makeSuperAdmins(2);

    $a->update(['is_active' => false]);

    expect($a->fresh()->is_active)->toBeFalse();
});

it('blocks deactivating the last active super admin', function () {
    [$solo] = makeSuperAdmins(1);

    expect(fn () => $solo->update(['is_active' => false]))
        ->toThrow(BusinessRuleException::class, SuperAdminGuard::MESSAGE_DEACTIVATE);

    expect($solo->fresh()->is_active)->toBeTrue();
});

// ── Demote (remove super admin flag) ─────────────────────────────────────────

it('allows demoting a super admin when another active one remains', function () {
    [$a] = makeSuperAdmins(2);

    $a->forceFill(['is_super_admin' => false])->save();

    expect($a->fresh()->is_super_admin)->toBeFalse();
});

it('blocks removing the super admin flag from the last active super admin', function () {
    [$solo] = makeSuperAdmins(1);

    expect(fn () => $solo->forceFill(['is_super_admin' => false])->save())
        ->toThrow(BusinessRuleException::class, SuperAdminGuard::MESSAGE_DEMOTE);

    expect($solo->fresh()->is_super_admin)->toBeTrue();
});

// ── A deactivated super admin no longer counts as the safety net ──────────────

it('treats a deactivated super admin as not protecting the last active one', function () {
    [$active, $inactive] = makeSuperAdmins(2);
    $inactive->update(['is_active' => false]);

    // Only $active remains active → it is now the last one and is protected.
    expect(fn () => $active->fresh()->delete())
        ->toThrow(BusinessRuleException::class, SuperAdminGuard::MESSAGE_DELETE);
});

// ── Policy layer (defense for non-super-admin actors) ─────────────────────────

it('denies delete via policy when the target is the last active super admin', function () {
    [$solo] = makeSuperAdmins(1);
    $actor = User::factory()->create();

    expect(app(UserPolicy::class)->delete($actor, $solo))->toBeFalse()
        ->and(app(UserPolicy::class)->forceDelete($actor, $solo))->toBeFalse();
});

// ── Filament layer (same predicate used to hide/disable controls) ─────────────

it('reports the last active super admin to the Filament layer', function () {
    [$a, $b] = makeSuperAdmins(2);

    expect(guard()->isLastActiveSuperAdmin($a))->toBeFalse();

    $b->update(['is_active' => false]);

    expect(guard()->isLastActiveSuperAdmin($a->fresh()))->toBeTrue();
});

// ── Concurrency / race safety ─────────────────────────────────────────────────

it('serializes concurrent removals with a pessimistic row lock', function () {
    [$a] = makeSuperAdmins(2);

    DB::enableQueryLog();
    guard()->assertAnotherActiveSuperAdminExists($a, 'msg');
    $sql = collect(DB::getQueryLog())->pluck('query')->implode(' | ');
    DB::disableQueryLog();

    // The FOR UPDATE lock is what makes two simultaneous transactions serialize
    // on the same active-super-admin rows instead of both passing the check.
    expect(strtolower($sql))->toContain('for update');
});

it('keeps the invariant across sequential removals (post-condition of the lock)', function () {
    [$a, $b] = makeSuperAdmins(2);

    $a->delete(); // allowed — $b still active

    // $b is now the last active super admin: every removal path is blocked.
    expect(fn () => $b->fresh()->delete())
        ->toThrow(BusinessRuleException::class)
        ->and(fn () => $b->fresh()->update(['is_active' => false]))
        ->toThrow(BusinessRuleException::class)
        ->and(fn () => $b->fresh()->forceFill(['is_super_admin' => false])->save())
        ->toThrow(BusinessRuleException::class);
});

// ── Non-super-admin users are unaffected ──────────────────────────────────────

it('does not interfere with deleting or deactivating regular users', function () {
    makeSuperAdmins(1); // keep one super admin around
    $regular = User::factory()->create(['is_active' => true]);

    $regular->update(['is_active' => false]);
    $regular->delete();

    expect($regular->fresh()->trashed())->toBeTrue();
});
