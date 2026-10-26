<?php

namespace App\Domain\FruitPurchasing\Support;

/**
 * Números como se escriben en Colombia: punto para los miles y coma decimal.
 */
final class ColombianNumber
{
    /**
     * «18.000» son dieciocho mil y «4,7» es cuatro coma siete. También acepta
     * «18000», «4.7» y «$ 4.000.000». Vacío o texto → null.
     */
    public static function parse(string $value): ?float
    {
        $normalized = str_replace([' ', '$', "\u{00A0}"], '', trim($value));

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(['.', ','], ['', '.'], $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $normalized) === 1) {
            $normalized = str_replace('.', '', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    /**
     * 18000 → «18.000», 4.7 → «4,7», 17890.5 → «17.890,5».
     */
    public static function format(float $value): string
    {
        if (floor($value) === $value) {
            return number_format($value, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
