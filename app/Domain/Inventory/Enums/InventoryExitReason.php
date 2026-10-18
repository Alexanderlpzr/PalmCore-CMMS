<?php

namespace App\Domain\Inventory\Enums;

/**
 * Por qué sale un repuesto sin orden de trabajo: lo que pide el vale de salida. Los
 * motivos de consumo exigen saber quién se lo llevó; los de baja o devolución no.
 */
enum InventoryExitReason: string
{
    case PlantConsumption = 'plant_consumption';
    case ProtectiveEquipment = 'protective_equipment';
    case DamageOrLoss = 'damage_or_loss';
    case Obsolescence = 'obsolescence';
    case SupplierReturn = 'supplier_return';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PlantConsumption => 'Consumo en planta',
            self::ProtectiveEquipment => 'Dotación y EPP',
            self::DamageOrLoss => 'Daño o pérdida',
            self::Obsolescence => 'Baja por obsoleto',
            self::SupplierReturn => 'Devolución al proveedor',
            self::Other => 'Otro',
        };
    }

    /** Lo que se le entrega a alguien: el vale lleva su nombre. */
    public function requiresRecipient(): bool
    {
        return in_array($this, [self::PlantConsumption, self::ProtectiveEquipment, self::Other], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
