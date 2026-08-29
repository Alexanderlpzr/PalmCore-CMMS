<?php

use App\Domain\Analytics\Support\DashboardPeriod;

it('resolve returns [null, null] for the default preset', function () {
    expect(DashboardPeriod::resolve(null))->toBe([null, null])
        ->and(DashboardPeriod::resolve(['preset' => 'last_12_months']))->toBe([null, null]);
});

it('resolve returns the full calendar year for the year preset', function () {
    [$from, $to] = DashboardPeriod::resolve(['preset' => 'year', 'year' => 2025]);

    expect($from->format('Y-m-d'))->toBe('2025-01-01')
        ->and($to->format('Y-m-d'))->toBe('2025-12-01');
});

it('resolve returns a single month for the month preset', function () {
    [$from, $to] = DashboardPeriod::resolve(['preset' => 'month', 'year' => 2025, 'month' => 3]);

    expect($from->format('Y-m-d'))->toBe('2025-03-01')
        ->and($to->format('Y-m-d'))->toBe('2025-03-01');
});

it('resolve returns a month range for the range preset (e.g. January to October)', function () {
    [$from, $to] = DashboardPeriod::resolve([
        'preset' => 'range', 'range_year' => 2025, 'range_from_month' => 1, 'range_to_month' => 10,
    ]);

    expect($from->format('Y-m-d'))->toBe('2025-01-01')
        ->and($to->format('Y-m-d'))->toBe('2025-10-01');
});

it('resolve swaps from/to when the range is entered backwards', function () {
    [$from, $to] = DashboardPeriod::resolve([
        'preset' => 'range', 'range_year' => 2025, 'range_from_month' => 10, 'range_to_month' => 1,
    ]);

    expect($from->format('Y-m-d'))->toBe('2025-01-01')
        ->and($to->format('Y-m-d'))->toBe('2025-10-01');
});

it('label describes each preset in Spanish', function () {
    expect(DashboardPeriod::label(null))->toBe('últimos 12 meses')
        ->and(DashboardPeriod::label(['preset' => 'year', 'year' => 2025]))->toBe('año 2025')
        ->and(DashboardPeriod::label(['preset' => 'month', 'year' => 2025, 'month' => 3]))->toBe('Marzo 2025')
        ->and(DashboardPeriod::label([
            'preset' => 'range', 'range_year' => 2025, 'range_from_month' => 1, 'range_to_month' => 10,
        ]))->toBe('Enero – Octubre 2025');
});

it('yearOptions returns the current year and the requested span going backwards', function () {
    $options = DashboardPeriod::yearOptions(3);

    expect($options)->toHaveCount(3)
        ->and(array_key_first($options))->toBe((int) now()->year);
});

it('monthOptions returns all 12 months', function () {
    expect(DashboardPeriod::monthOptions())->toHaveCount(12);
});

// ── El mes vacío que retrocedía un año ───────────────────────────────────────

/**
 * Un desplegable de Filament sin selección llega como cadena vacía, y `??` no la
 * sustituye: solo actúa sobre null o ausente. `(int) ''` es 0, y Carbon::create(2026, 0, 1)
 * no falla — devuelve el 1 de diciembre de 2025.
 *
 * La pantalla acabó sumando diciembre del año anterior con enero mientras el rótulo decía
 * «Enero – 2026». El número no era el que nadie había pedido, y nada chirriaba.
 */
it('trata el mes vacío como ausente, no como el mes cero', function () {
    [$from, $to] = DashboardPeriod::resolve([
        'preset' => 'range',
        'range_year' => 2026,
        'range_from_month' => 1,
        'range_to_month' => '',
    ]);

    // Antes: 2025-12-01 → 2026-01-01. El año anterior colándose en el período.
    expect($from->format('Y-m-d'))->toBe('2026-01-01')
        ->and($from->year)->toBe(2026)
        ->and($to->year)->toBe(2026);
});

it('no deja que un mes fuera de rango llegue a Carbon', function () {
    foreach ([0, 13, -1, 99] as $mesImposible) {
        [$from] = DashboardPeriod::resolve([
            'preset' => 'month', 'year' => 2026, 'month' => $mesImposible,
        ]);

        expect($from->year)->toBe(2026, "el mes {$mesImposible} se salió del año");
    }
});

it('rotula el mes vacío sin dejar el hueco', function () {
    $rotulo = DashboardPeriod::label([
        'preset' => 'range',
        'range_year' => 2026,
        'range_from_month' => 1,
        'range_to_month' => '',
    ]);

    // Antes decía «Enero –  2026», con el segundo mes en blanco.
    expect($rotulo)->not->toContain('–  ')
        ->and($rotulo)->toContain('2026');
});

it('el rótulo describe el mismo período que los datos', function () {
    // Es la garantía de fondo: los dos salen de la misma normalización, así que un rango
    // invertido se corrige en un solo sitio y no puede describirse al revés.
    $filtros = [
        'preset' => 'range',
        'range_year' => 2026,
        'range_from_month' => 8,
        'range_to_month' => 3,
    ];

    [$from, $to] = DashboardPeriod::resolve($filtros);

    expect($from->format('Y-m-d'))->toBe('2026-03-01')
        ->and($to->format('Y-m-d'))->toBe('2026-08-01')
        // Y el rótulo dice marzo–agosto, no agosto–marzo.
        ->and(DashboardPeriod::label($filtros))->toBe('Marzo – Agosto 2026');
});

it('dice un solo mes cuando el rango es de un mes', function () {
    expect(DashboardPeriod::label([
        'preset' => 'range', 'range_year' => 2026,
        'range_from_month' => 5, 'range_to_month' => 5,
    ]))->toBe('Mayo 2026');
});

it('cae al preset por defecto cuando llega vacío', function () {
    expect(DashboardPeriod::resolve(['preset' => '']))->toBe([null, null])
        ->and(DashboardPeriod::label(['preset' => '']))->toBe('últimos 12 meses')
        ->and(DashboardPeriod::labelForSnapshot(['preset' => '']))->toBe('este mes');
});

it('ignora un año imposible en vez de irse a ese año', function () {
    [$from] = DashboardPeriod::resolve(['preset' => 'year', 'year' => '']);

    expect($from->year)->toBe((int) now()->year);
});
