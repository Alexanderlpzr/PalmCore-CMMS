<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/*
 * La política de contraseñas en producción: seis caracteres, sin exigir mayúsculas,
 * números ni símbolos.
 */

it('accepts a short simple password in production and rejects one under six characters', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $valida = fn (string $clave): bool => Validator::make(['clave' => $clave], ['clave' => Password::default()])->passes();

    expect($valida('palma6'))->toBeTrue()
        ->and($valida('123456'))->toBeTrue()
        ->and($valida('abc12'))->toBeFalse();
});
