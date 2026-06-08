<?php

namespace App\Domain\Maintenance\Enums;

enum WorkOrderSignatureType: string
{
    case TechnicianCompletion  = 'technician_completion';
    case SupervisorVerification = 'supervisor_verification';

    public function label(): string
    {
        return match ($this) {
            self::TechnicianCompletion   => 'Firma de Técnico',
            self::SupervisorVerification => 'Firma de Supervisor',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TechnicianCompletion   => 'info',
            self::SupervisorVerification => 'success',
        };
    }

    public static function options(): array
    {
        return array_column(
            array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'value'
        );
    }
}
