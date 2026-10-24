<?php

namespace App\Domain\HumanResources\Support;

use Carbon\CarbonInterface;
use NumberFormatter;

/**
 * Números y fechas en letras, como los escriben los formatos de talento humano: «seis (06)
 * de octubre del año dos mil veintiséis (2026)» y «Un millón setecientos cincuenta mil
 * novecientos cinco» pesos.
 *
 * Usa el deletreo de intl, al que hay que quitarle los guiones blandos que intercala en las
 * palabras largas («sete­cientos»): en el documento salen como un guion en mitad de la palabra.
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
     * El valor en pesos en letras, para ir delante de «pesos m/cte.». El ruleset masculino da
     * «veintiún mil» y «un millón»; el millón redondo lleva «de»: «un millón de» pesos.
     */
    public static function amount(float $amount): string
    {
        $words = self::spell((int) round($amount), '%spellout-cardinal-masculine');

        return preg_match('/(millón|millones)$/u', $words) ? $words.' de' : $words;
    }

    /** La cifra con puntos de mil: 1750905 → «1.750.905». */
    public static function figure(float $amount): string
    {
        return number_format((int) round($amount), 0, ',', '.');
    }

    /** «seis (06) de octubre del año dos mil veintiséis (2026)»; sin ceros, «dos (2) de marzo…». */
    public static function date(CarbonInterface $date, bool $padded = true): string
    {
        return self::day($date, $padded).' de '.self::month($date).' del año '.self::number($date->year).' ('.$date->year.')';
    }

    /** «los treinta (30) días del mes de octubre de dos mil veintiséis (2026)». */
    public static function daysOfMonth(CarbonInterface $date, bool $padded = true): string
    {
        return 'los '.self::day($date, $padded).' días del mes de '.self::month($date).' de '.self::number($date->year).' ('.$date->year.')';
    }

    /** «seis (06)», o «seis (6)» sin el cero. */
    public static function day(CarbonInterface $date, bool $padded = true): string
    {
        return self::dayWords($date).' ('.($padded ? $date->format('d') : $date->day).')';
    }

    /** El día en letras; el 1 es «primero». */
    public static function dayWords(CarbonInterface $date): string
    {
        return $date->day === 1 ? 'primero' : self::number($date->day);
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
