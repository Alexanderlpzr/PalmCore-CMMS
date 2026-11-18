<?php

namespace App\Domain\Commercial\Services;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Commercial\Enums\PlanVersion;
use App\Models\AnnualPlan;
use App\Models\AnnualPlanFruit;
use App\Models\AnnualPlanMonth;
use App\Models\AnnualPlanSale;
use App\Models\FruitReception;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Lleva el plan comercial de cada año y la fruta recibida.
 *
 * El plan nace como «original» y se llena hasta aprobarlo. Al aprobarlo queda congelado y
 * se copia a la «reproyección», que es la que se ajusta durante el año. Cada celda se
 * guarda sola, como en una hoja de cálculo: vacía o en cero, se borra.
 */
class AnnualPlanService
{
    /** Las tasas fijas que la Directora usaba para el subproducto. */
    public const DEFAULT_KERNEL_PERCENT = 5.0;

    public const DEFAULT_PKO_PERCENT = 43.0;

    /** Fila del plan de ventas sin cliente todavía. */
    public const UNASSIGNED = 'por-concretar';

    public function find(string $tenantId, int $year, PlanVersion $version): ?AnnualPlan
    {
        return AnnualPlan::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->where('version', $version->value)
            ->first();
    }

    /** La que manda: la reproyección si ya existe, si no el original. */
    public function current(string $tenantId, int $year): ?AnnualPlan
    {
        return $this->find($tenantId, $year, PlanVersion::Reproyeccion) ?? $this->find($tenantId, $year, PlanVersion::Original);
    }

    /**
     * El plan original del año, con sus doce meses. Toma la capacidad y la TEA del plan
     * del año anterior, si lo hay, para no empezar en blanco.
     */
    public function createOriginal(string $tenantId, int $year, ?User $user): AnnualPlan
    {
        if ($this->find($tenantId, $year, PlanVersion::Original) !== null) {
            throw new \DomainException("El plan de {$year} ya existe.");
        }

        $previous = $this->current($tenantId, $year - 1)?->months()->get()->keyBy('month');

        return DB::transaction(function () use ($tenantId, $year, $user, $previous): AnnualPlan {
            $plan = AnnualPlan::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'year' => $year,
                'version' => PlanVersion::Original->value,
                'created_by' => $user?->id,
            ]);

            foreach (range(1, 12) as $month) {
                AnnualPlanMonth::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'annual_plan_id' => $plan->id,
                    'month' => $month,
                    'plant_capacity_tons' => $previous?->get($month)?->plant_capacity_tons,
                    'tea_percent' => $previous?->get($month)?->tea_percent,
                    'kernel_percent' => $previous?->get($month)?->kernel_percent ?? self::DEFAULT_KERNEL_PERCENT,
                    'pko_percent' => $previous?->get($month)?->pko_percent ?? self::DEFAULT_PKO_PERCENT,
                ]);
            }

            return $plan;
        });
    }

    /** Congela el original y abre la reproyección como copia suya. */
    public function approve(AnnualPlan $plan, ?User $user): AnnualPlan
    {
        if ($plan->version !== PlanVersion::Original || $plan->approved_at !== null) {
            throw new \DomainException('Solo se aprueba un plan original que aún no esté aprobado.');
        }

        return DB::transaction(function () use ($plan, $user): AnnualPlan {
            $plan->update(['approved_at' => now(), 'approved_by' => $user?->id]);

            $copy = $plan->replicate(['approved_at', 'approved_by']);
            $copy->version = PlanVersion::Reproyeccion;
            $copy->created_by = $user?->id;
            $copy->save();

            foreach ([$plan->months()->get(), $plan->fruit()->get(), $plan->sales()->get()] as $rows) {
                foreach ($rows as $row) {
                    $clone = $row->replicate();
                    $clone->annual_plan_id = $copy->id;
                    $clone->save();
                }
            }

            return $copy;
        });
    }

    public function setFruit(AnnualPlan $plan, string $supplierId, int $month, mixed $tons): void
    {
        $this->assertEditable($plan);

        $this->setCell(
            AnnualPlanFruit::withoutGlobalScopes()->where('annual_plan_id', $plan->id)->where('fruit_supplier_id', $supplierId)->where('month', $month),
            ['tenant_id' => $plan->tenant_id, 'annual_plan_id' => $plan->id, 'fruit_supplier_id' => $supplierId, 'month' => $month],
            $tons,
        );
    }

    /** $customerId en null o «por-concretar»: la fila de lo que aún no tiene comprador. */
    public function setSale(AnnualPlan $plan, ?string $customerId, CommercialProduct $product, int $month, mixed $tons): void
    {
        $this->assertEditable($plan);
        $customerId = $customerId === self::UNASSIGNED ? null : $customerId;

        $this->setCell(
            AnnualPlanSale::withoutGlobalScopes()->where('annual_plan_id', $plan->id)->where('product', $product->value)->where('month', $month)
                ->when($customerId === null, fn ($query) => $query->whereNull('customer_id'), fn ($query) => $query->where('customer_id', $customerId)),
            ['tenant_id' => $plan->tenant_id, 'annual_plan_id' => $plan->id, 'customer_id' => $customerId, 'product' => $product->value, 'month' => $month],
            $tons,
        );
    }

    /** Un parámetro de la planta en un mes: capacidad, TEA, % de almendra o % de PKO. */
    public function setMonthParameter(AnnualPlan $plan, int $month, string $field, mixed $value): void
    {
        $this->assertEditable($plan);

        if (! in_array($field, ['plant_capacity_tons', 'tea_percent', 'kernel_percent', 'pko_percent'], true)) {
            throw new \InvalidArgumentException("Campo desconocido: {$field}");
        }

        $number = self::number($value);

        if ($number !== null && str_ends_with($field, '_percent') && ($number < 0 || $number > 100)) {
            throw new \DomainException('Un porcentaje va entre 0 y 100.');
        }

        AnnualPlanMonth::withoutGlobalScopes()->updateOrCreate(
            ['annual_plan_id' => $plan->id, 'month' => $month],
            ['tenant_id' => $plan->tenant_id, $field => $number ?? match ($field) {
                'kernel_percent' => self::DEFAULT_KERNEL_PERCENT,
                'pko_percent' => self::DEFAULT_PKO_PERCENT,
                default => null,
            }],
        );
    }

    public function setOpeningOil(AnnualPlan $plan, mixed $tons): void
    {
        $this->assertEditable($plan);
        $plan->update(['opening_oil_tons' => self::number($tons)]);
    }

    public function setReception(string $tenantId, string $supplierId, int $year, int $month, mixed $tons, ?User $user): void
    {
        $this->setCell(
            FruitReception::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('fruit_supplier_id', $supplierId)->where('year', $year)->where('month', $month),
            ['tenant_id' => $tenantId, 'fruit_supplier_id' => $supplierId, 'year' => $year, 'month' => $month, 'recorded_by' => $user?->id],
            $tons,
            FruitReception::class,
        );
    }

    private function assertEditable(AnnualPlan $plan): void
    {
        if (! $plan->isEditable()) {
            throw new \DomainException('El plan original ya está aprobado: los ajustes van en la reproyección.');
        }
    }

    /**
     * Guarda una celda: con número la crea o la cambia; vacía o en cero, la borra del todo
     * (no queda en la papelera: la siguiente cifra del mismo mes ocuparía su lugar).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $keys
     */
    private function setCell($query, array $keys, mixed $tons, ?string $model = null): void
    {
        $number = self::number($tons);

        if ($number !== null && $number < 0) {
            throw new \DomainException('Las toneladas no pueden ser negativas.');
        }

        // Sin ámbitos globales: tampoco el de la papelera, así que aquí sale aunque esté borrada.
        $existing = (clone $query)->first();

        if ($number === null || $number == 0.0) {
            $existing?->forceDelete();

            return;
        }

        if ($existing !== null) {
            $existing->forceFill(['tons' => $number, 'deleted_at' => null])->save();

            return;
        }

        $model ??= get_class($query->getModel());
        $model::withoutGlobalScopes()->create([...$keys, 'tons' => $number]);
    }

    /** «1.234,5», «1234.5» o «1234,5» → 1234.5. */
    public static function number(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = str_replace([' ', '%', '$'], '', trim((string) $value));

        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $text)) {
            $text = str_replace(['.', ','], ['', '.'], $text);
        } else {
            $text = str_replace(',', '.', $text);
        }

        if (! is_numeric($text)) {
            throw new \DomainException("«{$value}» no es un número.");
        }

        return round((float) $text, 2);
    }
}
