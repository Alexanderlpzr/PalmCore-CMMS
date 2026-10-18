<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\SparePartCategoryType;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class SparePartService
{
    /**
     * Crea el repuesto. Sin código, el sistema le asigna el siguiente de su categoría
     * (MEC-0001, ELE-0001…): en el levantamiento nadie tiene que inventarse uno.
     */
    public function create(array $data, User $createdBy): SparePart
    {
        $autoCode = blank($data['code'] ?? null);

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $createdBy, $autoCode): SparePart {
                    return SparePart::create([
                        ...$data,
                        'code' => $autoCode
                            ? $this->nextCode($data['tenant_id'], $this->categoryOf($data['category_type'] ?? null))
                            : $data['code'],
                        'created_by' => $createdBy->id,
                        'is_active' => true,
                    ]);
                });
            } catch (UniqueConstraintViolationException $e) {
                // Dos altas a la vez tomaron el mismo número: se reintenta con el siguiente.
                if (! $autoCode || $attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    /** El siguiente código libre de la categoría, contando también los repuestos borrados. */
    public function nextCode(string $tenantId, SparePartCategoryType $category): string
    {
        $prefix = $category->codePrefix();

        $highest = SparePart::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', 'like', "{$prefix}-%")
            ->pluck('code')
            ->map(fn (string $code): int => preg_match('/^'.$prefix.'-(\d+)$/', $code, $match) ? (int) $match[1] : 0)
            ->max() ?? 0;

        return sprintf('%s-%04d', $prefix, $highest + 1);
    }

    private function categoryOf(SparePartCategoryType|string|null $category): SparePartCategoryType
    {
        if ($category instanceof SparePartCategoryType) {
            return $category;
        }

        return SparePartCategoryType::tryFrom((string) $category) ?? SparePartCategoryType::Other;
    }

    public function update(SparePart $sparePart, array $data, User $updatedBy): SparePart
    {
        return DB::transaction(function () use ($sparePart, $data, $updatedBy): SparePart {
            $sparePart->update([
                ...$data,
                'updated_by' => $updatedBy->id,
            ]);

            return $sparePart->refresh();
        });
    }

    public function deactivate(SparePart $sparePart, User $updatedBy): SparePart
    {
        $sparePart->update(['is_active' => false, 'updated_by' => $updatedBy->id]);

        return $sparePart;
    }

    public function activate(SparePart $sparePart, User $updatedBy): SparePart
    {
        $sparePart->update(['is_active' => true, 'updated_by' => $updatedBy->id]);

        return $sparePart;
    }
}
