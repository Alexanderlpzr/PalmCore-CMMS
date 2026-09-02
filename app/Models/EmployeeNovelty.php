<?php

namespace App\Models;

use App\Domain\HumanResources\Enums\NoveltyType;
use App\Domain\Shared\Models\BaseModel;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeNoveltyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un tramo de días que no fueron días trabajados.
 *
 * Se guarda por rango y no por cantidad: «seis días de vacaciones» no dice cuáles, y sin
 * saber cuáles no se puede comprobar que no chocan con un turno que el reloj registró ni
 * repartir una incapacidad que cruza dos meses.
 */
#[Fillable([
    'tenant_id',
    'employee_id',
    'type',
    'starts_on',
    'ends_on',
    'reference',
    'notes',
    'created_by',
])]
class EmployeeNovelty extends BaseModel
{
    /** @use HasFactory<EmployeeNoveltyFactory> */
    use HasFactory;

    protected $table = 'hr_employee_novelties';

    // ── Relationships ─────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** Las novedades que tocan el período, aunque empiecen antes o terminen después. */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query
            ->whereDate('starts_on', '<=', $to)
            ->whereDate('ends_on', '>=', $from);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Cuántos días de esta novedad caen dentro del período.
     *
     * Se recorta contra el período porque una incapacidad de quince días a caballo entre
     * dos meses no puede cobrarse entera en ninguno de los dos.
     */
    public function daysWithin(CarbonInterface $from, CarbonInterface $to): int
    {
        $start = $this->starts_on->greaterThan($from) ? $this->starts_on : $from;
        $end = $this->ends_on->lessThan($to) ? $this->ends_on : $to;

        if ($end->lessThan($start)) {
            return 0;
        }

        return (int) $start->startOfDay()->diffInDays($end->startOfDay()) + 1;
    }

    public function totalDays(): int
    {
        return (int) $this->starts_on->diffInDays($this->ends_on) + 1;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'type' => NoveltyType::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
