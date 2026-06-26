<?php

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * PR-5 — 2FA Enforcement: UserFactory state and TwoFactorAuthenticatable trait.
 */
it('withTwoFactor factory state produces a confirmed 2FA user', function () {
    $user = User::factory()->withTwoFactor()->create();

    expect($user->two_factor_confirmed_at)->not->toBeNull()
        ->and($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_recovery_codes)->not->toBeNull();
});

it('hasEnabledTwoFactorAuthentication returns true for withTwoFactor user', function () {
    $user = User::factory()->withTwoFactor()->create();

    expect($user->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

it('hasEnabledTwoFactorAuthentication returns false for standard user', function () {
    $user = User::factory()->create();

    expect($user->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('two_factor_secret is stored encrypted and differs from plaintext', function () {
    $user = User::factory()->withTwoFactor()->create();

    $raw = User::find($user->id)->getAttributes()['two_factor_secret'];

    expect($raw)->not->toBe('JBSWY3DPEHPK3PXP');
});

it('two_factor_confirmed_at is cast to a Carbon date', function () {
    $user = User::factory()->withTwoFactor()->create();

    expect($user->two_factor_confirmed_at)->toBeInstanceOf(CarbonInterface::class);
});

it('standard factory creates user with null two_factor columns', function () {
    $user = User::factory()->create();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();
});
