<?php

namespace App\Domain\HumanResources\Support;

use Carbon\CarbonInterface;
use NumberFormatter;

/**
 * Números y fechas en letras, como los escribe talento humano en sus certificados: «el seis
 * (06) de octubre del año dos mil veintiséis (2026)» y «un millón setecientos cincuenta mil
 * novecientos cinco pesos m/cte. ($ 1.750.905)».
 *
 * Usa el deletreo de intl, al que hay que quitarle los guiones blandos que intercala en las
 * palabras largas («sete­cientos»): en el PDF salen como un guion en mitad de la palabra.
 */
final class SpanishWords
{
    private const SOFT_HYPHEN = "\u{00AD}";

    /** El número en letras: 21 → «veintiuno». */
    public static function number(int $number): string
    {
        return self::spell($number, null);
    }

    /**
     * El valor en pesos en letras y en cifras. El ruleset masculino da «veintiún mil» y «un
     * millón»; el millón redondo lleva «de»: «un millón de pesos».
     */
    public static function money(float $amount): string
    {
        $pesos = (int) round($amount);
        $words = self::spell($pesos, '%spellout-cardinal-masculine');

        if (preg_match('/(millón|millones)$/u', $words)) {
            $words .= ' de';
        }

        return $words.' pesos m/cte. ($ '.number_format($pesos, 0, ',', '.').')';
    }

    /**
     * «seis (06) de octubre del año dos mil veintiséis (2026)». El día 1 es «primero».
     */
    public static function date(CarbonInterface $date): string
    {
        return self::day($date).' de '.self::month($date).' del año '.self::number($date->year).' ('.$date->year.')';
    }

    /** «los treinta (30) días del mes de octubre de dos mil veintiséis (2026)». */
    public static function daysOfMonth(CarbonInterface $date): string
    {
        return 'los '.self::day($date).' días del mes de '.self::month($date).' de '.self::number($date->year).' ('.$date->year.')';
    }

    /** «seis (06)», «primero (01)». */
    public static function day(CarbonInterface $date): string
    {
        $words = $date->day === 1 ? 'primero' : self::number($date->day);

        return $words.' ('.$date->format('d').')';
    }

    public static function month(CarbonInterface $date): string
    {
        return mb_strtolower($date->locale('es')->translatedFormat('F'));
    }

    /** La primera letra en mayúscula, sin tocar el resto. */
    public static function capitalize(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    private static function spell(int $number, ?string $ruleset): string
    {
        $formatter = new NumberFormatter('es', NumberFormatter::SPELLOUT);

        if ($ruleset !== null) {
            $formatter->setTextAttribute(NumberFormatter::DEFAULT_RULESET, $ruleset);
        }

        return str_replace(self::SOFT_HYPHEN, '', (string) $formatter->format($number));
    }
}
