<?php

namespace Database\Factories;

use App\Domain\Assets\Enums\DocumentType;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentDocument>
 */
class EquipmentDocumentFactory extends Factory
{
    public function definition(): array
    {
        $tenant    = Tenant::factory()->create();
        $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

        return [
            'equipment_id'  => $equipment->id,
            'tenant_id'     => $tenant->id,
            'document_type' => $this->faker->randomElement(DocumentType::cases())->value,
            'title'         => $this->faker->sentence(4),
            'description'   => $this->faker->optional()->paragraph(),
            'file_path'     => 'equipment-documents/'.$tenant->id.'/'.$equipment->id.'/'.$this->faker->uuid().'.pdf',
            'file_name'     => $this->faker->word().'.pdf',
            'file_size'     => $this->faker->optional()->numberBetween(10240, 5242880),
            'mime_type'     => 'application/pdf',
            'version'       => $this->faker->optional()->numerify('v#.#'),
            'expires_at'    => $this->faker->optional()->dateTimeBetween('now', '+2 years'),
            'is_active'     => true,
            'uploaded_by'   => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }

    public function withVersion(string $version = 'v1.0'): static
    {
        return $this->state(fn () => ['version' => $version]);
    }
}
