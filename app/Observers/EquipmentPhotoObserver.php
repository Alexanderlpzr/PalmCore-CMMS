<?php

namespace App\Observers;

use App\Models\EquipmentPhoto;
use Illuminate\Support\Facades\Storage;

class EquipmentPhotoObserver
{
    /**
     * Delete the physical file only on force-delete.
     * Soft-deleted records retain the file so it can be restored.
     */
    public function forceDeleted(EquipmentPhoto $photo): void
    {
        if ($photo->file_path) {
            Storage::disk('public')->delete($photo->file_path);
        }
    }
}
