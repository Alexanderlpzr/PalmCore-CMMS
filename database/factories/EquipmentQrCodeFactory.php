<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\EquipmentQrCode;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EquipmentQrCode>
 */
class EquipmentQrCodeFactory extends Factory
{
    public function definition(): array
    {
        $tenant    = Tenant::factory()->create();
        $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
        $token     = (string) Str::uuid(); // UUID v4 for randomness

        return [
            'equipment_id'    => $equipment->id,
            'tenant_id'       => $tenant->id,
            'qr_token'        => $token,
            'qr_image_path'   => null,
            'is_active'       => true,
            'generated_at'    => now(),
            'last_scanned_at' => null,
            'scan_count'      => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withImage(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'qr_image_path' => 'equipment-qr/'.$attributes['tenant_id'].'/'.$attributes['qr_token'].'.png',
            ];
        });
    }

    public function scanned(int $times = 1): static
    {
        return $this->state(fn () => [
            'scan_count'      => $times,
            'last_scanned_at' => now(),
        ]);
    }
}
