<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\EmployeeQrCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El carné QR del trabajador.
 *
 * Calcado de `EquipmentQrCode` en forma, pero con una diferencia que no es un detalle:
 * el QR del equipo abre una página pública porque leer la ficha de una bomba no le hace
 * daño a nadie. Este no abre nada. El token viaja a un endpoint autenticado que solo
 * portería puede llamar, porque quien tenga el token de un compañero podría marcarle la
 * entrada. El carné identifica; no autoriza.
 */
#[Fillable([
    'tenant_id',
    'employee_id',
    'qr_token',
    'qr_image_path',
    'is_active',
    'generated_at',
    'last_scanned_at',
    'scan_count',
])]
class EmployeeQrCode extends BaseModel
{
    /** @use HasFactory<EmployeeQrCodeFactory> */
    use HasFactory;

    protected $table = 'hr_employee_qr_codes';

    // ── Relationships ─────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function imageUrl(): ?string
    {
        if (! $this->qr_image_path) {
            return null;
        }

        return file_signed_url(persistent_disk(), $this->qr_image_path);
    }

    public function recordScan(): void
    {
        $this->increment('scan_count');
        $this->update(['last_scanned_at' => now()]);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'generated_at' => 'datetime',
            'last_scanned_at' => 'datetime',
            'scan_count' => 'integer',
        ];
    }
}
