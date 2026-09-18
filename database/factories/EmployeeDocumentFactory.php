<?php

namespace Database\Factories;

use App\Domain\HumanResources\Enums\EmployeeDocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeDocument>
 */
class EmployeeDocumentFactory extends Factory
{
    protected $model = EmployeeDocument::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'tenant_id' => fn (array $attributes): string => Employee::find($attributes['employee_id'])->tenant_id,
            'document_type' => EmployeeDocumentType::Cedula,
            'title' => 'Copia de la cédula',
            'file_path' => 'employee-documents/'.fake()->uuid().'/cedula.pdf',
            'file_name' => 'cedula.pdf',
            'file_size' => 120_000,
            'mime_type' => 'application/pdf',
            'expires_at' => null,
            'notes' => null,
            'uploaded_by' => null,
        ];
    }
}
