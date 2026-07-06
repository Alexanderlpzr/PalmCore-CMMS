<?php

namespace App\Domain\Maintenance\Enums;

/**
 * Standardised failure-mode catalogue for corrective/emergency work.
 *
 * Free-text failure_cause/root_cause describe *this* failure; the failure_mode
 * classifies it into a bucket so reliability analysis (Pareto by mode) can point
 * at the dominant physical cause across the plant — bearings, seals, electrical,
 * etc. — instead of only "which machine failed most".
 */
enum FailureMode: string
{
    case MechanicalWear = 'mechanical_wear';
    case Bearing = 'bearing';
    case Seal = 'seal';
    case Lubrication = 'lubrication';
    case Electrical = 'electrical';
    case Overheating = 'overheating';
    case Vibration = 'vibration';
    case Alignment = 'alignment';
    case Corrosion = 'corrosion';
    case Leak = 'leak';
    case Blockage = 'blockage';
    case Instrumentation = 'instrumentation';
    case Structural = 'structural';
    case OperationError = 'operation_error';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MechanicalWear => 'Desgaste mecánico',
            self::Bearing => 'Rodamiento / cojinete',
            self::Seal => 'Sello / empaque',
            self::Lubrication => 'Lubricación',
            self::Electrical => 'Eléctrico',
            self::Overheating => 'Sobrecalentamiento',
            self::Vibration => 'Vibración / desbalance',
            self::Alignment => 'Desalineación',
            self::Corrosion => 'Corrosión',
            self::Leak => 'Fuga',
            self::Blockage => 'Obstrucción / atascamiento',
            self::Instrumentation => 'Instrumentación / control',
            self::Structural => 'Estructural',
            self::OperationError => 'Error de operación',
            self::Other => 'Otro',
        };
    }

    /** @return array<string, string> value => label, for Filament selects. */
    public static function options(): array
    {
        return array_column(
            array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'value'
        );
    }
}
