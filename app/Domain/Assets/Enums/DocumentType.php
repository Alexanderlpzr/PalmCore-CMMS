<?php

namespace App\Domain\Assets\Enums;

enum DocumentType: string
{
    case Manual = 'manual';
    case Certificate = 'certificate';
    case Inspection = 'inspection';
    case Warranty = 'warranty';
    case Contract = 'contract';
    case TechnicalSheet = 'technical_sheet';
    case SafetyDataSheet = 'safety_data_sheet';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Manual          => 'Manual',
            self::Certificate     => 'Certificado',
            self::Inspection      => 'Inspección',
            self::Warranty        => 'Garantía',
            self::Contract        => 'Contrato',
            self::TechnicalSheet  => 'Ficha técnica',
            self::SafetyDataSheet => 'Hoja de seguridad',
            self::Other           => 'Otro',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Manual          => 'info',
            self::Certificate     => 'success',
            self::Inspection      => 'warning',
            self::Warranty        => 'primary',
            self::Contract        => 'gray',
            self::TechnicalSheet  => 'info',
            self::SafetyDataSheet => 'danger',
            self::Other           => 'gray',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
