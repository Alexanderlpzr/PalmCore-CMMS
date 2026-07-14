<?php

namespace App\Domain\Platform\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Los respaldos, vistos desde el panel.
 *
 * El respaldo diario ya estaba programado a la 1:00 a. m., pero nadie sabía si se
 * estaba ejecutando: un backup que falla en silencio es exactamente lo mismo que no
 * tener backup, con la diferencia de que uno cree que lo tiene.
 */
class BackupService
{
    /**
     * @return list<array{path: string, name: string, size_mb: float, created_at: Carbon, age_hours: int}>
     */
    public function list(): array
    {
        $disk = $this->disk();
        $name = (string) config('backup.backup.name');

        try {
            $files = Storage::disk($disk)->files($name);
        } catch (Throwable) {
            return [];
        }

        $backups = [];

        foreach ($files as $path) {
            if (! str_ends_with($path, '.zip')) {
                continue;
            }

            $createdAt = Carbon::createFromTimestamp(Storage::disk($disk)->lastModified($path));

            $backups[] = [
                'path' => $path,
                'name' => basename($path),
                'size_mb' => round(Storage::disk($disk)->size($path) / 1024 / 1024, 2),
                'created_at' => $createdAt,
                'age_hours' => (int) $createdAt->diffInHours(now()),
            ];
        }

        usort($backups, fn (array $a, array $b): int => $b['created_at'] <=> $a['created_at']);

        return $backups;
    }

    /**
     * Lanza un respaldo ahora. Solo la base: los archivos de media viven en S3 y tienen
     * su propia redundancia; meterlos aquí haría el respaldo tan lento que nadie lo
     * correría nunca.
     */
    public function runNow(): void
    {
        Artisan::call('backup:run', ['--only-db' => true]);
    }

    public function disk(): string
    {
        return (string) config('backup.backup.destination.disks.0', 'local');
    }
}
