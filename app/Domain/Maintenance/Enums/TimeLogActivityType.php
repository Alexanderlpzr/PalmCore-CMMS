<?php

namespace App\Domain\Maintenance\Enums;

/**
 * What the técnico was actually doing during a block of time on an OT.
 *
 * Without this, «horas reales» is one undifferentiated number and the MTTR built
 * on it is a lie: an OT that took nine hours because the rodamiento arrived at 4pm
 * reads exactly like an OT that took nine hours of work. The gap between wrench
 * time and waiting is the finding — it is what justifies the critical spares stock.
 */
enum TimeLogActivityType: string
{
    case Diagnosis = 'diagnosis';
    case Repair = 'repair';
    case WaitingParts = 'waiting_parts';
    case WaitingThirdParty = 'waiting_third_party';

    public function label(): string
    {
        return match ($this) {
            self::Diagnosis => 'Diagnóstico',
            self::Repair => 'Reparación',
            self::WaitingParts => 'Espera de repuesto',
            self::WaitingThirdParty => 'Espera de terceros',
        };
    }

    /**
     * Time with a wrench in hand. This — and only this — is what MTTR measures:
     * how long maintenance takes to repair, not how long the plant was down.
     */
    public function isWrenchTime(): bool
    {
        return match ($this) {
            self::Diagnosis, self::Repair => true,
            self::WaitingParts, self::WaitingThirdParty => false,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $options, self $case): array => [...$options, $case->value => $case->label()],
            [],
        );
    }
}
