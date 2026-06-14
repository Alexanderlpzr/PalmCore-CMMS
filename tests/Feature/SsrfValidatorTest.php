<?php

use App\Security\SsrfValidator;

// 203.0.113.x = TEST-NET-3 (RFC 5737) — documentation range, not private, safe for tests.
beforeEach(function () {
    SsrfValidator::setDnsResolver(fn (string $host) => match (true) {
        str_contains($host, 'localhost') => ['127.0.0.1'],
        $host === 'webhook.example.com' => ['203.0.113.50'],
        default => ['203.0.113.50'],
    });
});

afterEach(function () {
    SsrfValidator::setDnsResolver(null);
});

// ── URLs válidas ───────────────────────────────────────────────────────────────

it('permite URLs públicas HTTPS', function () {
    expect(fn () => SsrfValidator::validate('https://webhook.example.com/hook'))
        ->not->toThrow(InvalidArgumentException::class);
});

it('permite URLs públicas HTTP', function () {
    expect(fn () => SsrfValidator::validate('http://webhook.example.com/hook'))
        ->not->toThrow(InvalidArgumentException::class);
});

// ── Esquemas bloqueados ────────────────────────────────────────────────────────

it('rechaza esquema ftp', function () {
    expect(fn () => SsrfValidator::validate('ftp://example.com/file'))
        ->toThrow(InvalidArgumentException::class);
});

it('rechaza esquema file', function () {
    expect(fn () => SsrfValidator::validate('file:///etc/passwd'))
        ->toThrow(InvalidArgumentException::class);
});

// ── IPs privadas y reservadas ──────────────────────────────────────────────────

it('rechaza loopback 127.0.0.1', function () {
    expect(fn () => SsrfValidator::validate('http://127.0.0.1/secret'))
        ->toThrow(InvalidArgumentException::class);
});

it('rechaza localhost (resuelve a 127.0.0.1)', function () {
    expect(fn () => SsrfValidator::validate('http://localhost/admin'))
        ->toThrow(InvalidArgumentException::class);
});

it('rechaza rango privado 10.x', function () {
    expect(fn () => SsrfValidator::validate('http://10.0.0.1/'))
        ->toThrow(InvalidArgumentException::class);
});

it('rechaza rango privado 192.168.x', function () {
    expect(fn () => SsrfValidator::validate('http://192.168.1.100/'))
        ->toThrow(InvalidArgumentException::class);
});

it('rechaza rango privado 172.16.x', function () {
    expect(fn () => SsrfValidator::validate('http://172.16.0.1/'))
        ->toThrow(InvalidArgumentException::class);
});

it('rechaza AWS metadata endpoint 169.254.169.254', function () {
    expect(fn () => SsrfValidator::validate('http://169.254.169.254/latest/meta-data/'))
        ->toThrow(InvalidArgumentException::class);
});

it('rechaza IPv6 loopback [::1]', function () {
    expect(fn () => SsrfValidator::validate('http://[::1]/'))
        ->toThrow(InvalidArgumentException::class);
});

it('rechaza espacio de direcciones compartido RFC 6598 (100.64.x)', function () {
    expect(fn () => SsrfValidator::validate('http://100.64.0.1/'))
        ->toThrow(InvalidArgumentException::class);
});

// ── Puertos bloqueados ─────────────────────────────────────────────────────────

it('rechaza puerto SSH 22', function () {
    expect(fn () => SsrfValidator::validate('https://example.com:22/hook'))
        ->toThrow(InvalidArgumentException::class);
});

it('rechaza puerto PostgreSQL 5432', function () {
    expect(fn () => SsrfValidator::validate('http://example.com:5432/'))
        ->toThrow(InvalidArgumentException::class);
});

it('rechaza puerto Redis 6379', function () {
    expect(fn () => SsrfValidator::validate('http://example.com:6379/'))
        ->toThrow(InvalidArgumentException::class);
});

it('rechaza puerto MySQL 3306', function () {
    expect(fn () => SsrfValidator::validate('http://example.com:3306/'))
        ->toThrow(InvalidArgumentException::class);
});

// ── URLs malformadas ───────────────────────────────────────────────────────────

it('rechaza URL sin host', function () {
    expect(fn () => SsrfValidator::validate('not-a-url'))
        ->toThrow(InvalidArgumentException::class);
});
