<?php

namespace App\Models;

use App\Domain\HumanResources\Enums\EmployeeDocumentType;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\EmployeeDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un papel de la carpeta del trabajador. Vive en el disco privado: ver la migración.
 */
#[Fillable([
    'tenant_id',
    'employee_id',
    'document_type',
    'title',
    'file_path',
    'file_name',
    'file_size',
    'mime_type',
    'expires_at',
    'notes',
    'uploaded_by',
])]
class EmployeeDocument extends BaseModel
{
    /** @use HasFactory<EmployeeDocumentFactory> */
    use HasFactory;

    protected $table = 'hr_employee_documents';

    /**
     * La descripción es opcional en el formulario: sin ella, el documento se llama como
     * su tipo. Vive aquí y no en la acción para que crear y editar se comporten igual.
     */
    protected static function booted(): void
    {
        static::saving(function (self $document): void {
            if (blank($document->title) && $document->document_type !== null) {
                $document->title = $document->document_type->label();
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Vence en los próximos 30 días: tiempo de pedir el examen o el curso nuevo. */
    public function isExpiringSoon(): bool
    {
        return $this->expires_at !== null
            && ! $this->isExpired()
            && $this->expires_at->lte(now()->addDays(30));
    }

    protected function casts(): array
    {
        return [
            'document_type' => EmployeeDocumentType::class,
            'expires_at' => 'date',
            'file_size' => 'integer',
        ];
    }
}
