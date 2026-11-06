<?php

namespace App\Domain\Safety\Enums;

use App\Domain\Assets\Enums\IssueSeverity;

/**
 * La prioridad de un hallazgo de SST y el plazo para corregirlo, contado desde la fecha de
 * reporte: alta 8 días, media 15 y baja 30 (decisión de la empresa, 2026-11-05).
 */
enum FindingPriority: string
{
    case Alta = 'alta';
    case Media = 'media';
    case Baja = 'baja';

    public function label(): string
    {
        return match ($this) {
            self::Alta => 'Alta',
            self::Media => 'Media',
            self::Baja => 'Baja',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Alta => 'danger',
            self::Media => 'warning',
            self::Baja => 'gray',
        };
    }

    /** Los días que tiene para corregirse. */
    public function days(): int
    {
        return match ($this) {
            self::Alta => 8,
            self::Media => 15,
            self::Baja => 30,
        };
    }

    /** La gravedad del reporte de novedad que le llega a mantenimiento. */
    public function issueSeverity(): IssueSeverity
    {
        return match ($this) {
            self::Alta => IssueSeverity::High,
            self::Media => IssueSeverity::Medium,
            self::Baja => IssueSeverity::Low,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label().' ('.$case->days().' días)'])->all();
    }

    /** La del cuadro de Excel: «BAJA», «BAJO», «ALTA», «ALTO», «MEDIA»… */
    public static function fromText(?string $text): self
    {
        $text = mb_strtolower(trim((string) $text));

        return match (true) {
            str_starts_with($text, 'alt') => self::Alta,
            str_starts_with($text, 'med') => self::Media,
            default => self::Baja,
        };
    }
}
