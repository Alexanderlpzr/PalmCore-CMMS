<?php

namespace App\Domain\Platform\Services;

use App\Exceptions\BusinessRuleException;
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
     *
     * Se ejecuta aunque el respaldo automático esté apagado: el interruptor decide si
     * corre solo cada noche, no si el superadministrador puede pedir uno cuando quiera.
     * De hecho, respaldar a mano es justo lo que uno hace antes de un despliegue.
     *
     * @throws BusinessRuleException
     */
    public function runNow(): void
    {
        $exitCode = Artisan::call('backup:run', ['--only-db' => true]);

        if ($exitCode !== 0) {
            // Spatie escribe el porqué en la salida del comando y devuelve un código
            // distinto de cero. Tragarse eso y decir «listo» sería la peor mentira que
            // este panel puede contar: creerías tener un respaldo que no existe.
            throw new BusinessRuleException(
                'El respaldo falló: '.trim(Artisan::output()),
            );
        }
    }

    /**
     * El archivo, para descargarlo. Se resuelve dentro del disco de respaldos y nunca
     * con una ruta que venga de fuera: un «descargar» que acepta cualquier ruta es un
     * lector de archivos arbitrarios disfrazado de botón.
     *
     * @throws BusinessRuleException
     */
    public function pathOf(string $name): string
    {
        $backup = collect($this->list())->firstWhere('name', $name);

        if ($backup === null) {
            throw new BusinessRuleException('Ese respaldo no existe.');
        }

        return $backup['path'];
    }

    /** @throws BusinessRuleException */
    public function delete(string $name): void
    {
        Storage::disk($this->disk())->delete($this->pathOf($name));
    }

    public function disk(): string
    {
        return (string) config('backup.backup.destination.disks.0', 'local');
    }
}
