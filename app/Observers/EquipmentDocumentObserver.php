<?php

namespace App\Observers;

use App\Models\EquipmentDocument;
use Illuminate\Support\Facades\Storage;

class EquipmentDocumentObserver
{
    /**
     * Delete the physical file only on force-delete.
     * Soft-deleted records retain the file so it can be restored.
     */
    public function forceDeleted(EquipmentDocument $document): void
    {
        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }
    }
}
