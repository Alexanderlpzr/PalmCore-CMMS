<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\EquipmentPhoto;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentPhoto>
 */
class EquipmentPhotoFactory extends Factory
{
    public function definition(): array
    {
        $tenant    = Tenant::factory()->create();
        $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

        return [
            'equipment_id' => $equipment->id,
            'tenant_id'    => $tenant->id,
            'file_path'    => 'equipment-photos/'.$tenant->id.'/'.$equipment->id.'/'.$this->faker->uuid().'.jpg',
            'file_name'    => $this->faker->word().'.jpg',
            'file_size'    => $this->faker->optional()->numberBetween(51200, 2097152),
            'mime_type'    => 'image/jpeg',
            'caption'      => $this->faker->optional()->sentence(),
            'is_primary'   => false,
            'sort_order'   => $this->faker->numberBetween(0, 100),
            'uploaded_by'  => null,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true, 'sort_order' => 0]);
    }
}
