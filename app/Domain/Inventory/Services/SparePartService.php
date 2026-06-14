<?php

namespace App\Domain\Inventory\Services;

use App\Models\SparePart;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SparePartService
{
    public function create(array $data, User $createdBy): SparePart
    {
        return DB::transaction(function () use ($data, $createdBy): SparePart {
            return SparePart::create([
                ...$data,
                'created_by' => $createdBy->id,
                'is_active' => true,
            ]);
        });
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
