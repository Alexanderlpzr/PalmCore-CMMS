<?php

namespace App\Console\Commands;

use App\Models\EquipmentQrCode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes QR images under equipment-qr/ that no equipment_qr_codes row points to.
 *
 * Two sources produce them:
 *
 * 1. Test runs from before the suite faked the persistent disk. Every test creating
 *    an Equipment fired EquipmentObserver -> GenerateEquipmentQrCode, writing a real
 *    PNG under the factory tenant's UUID while RefreshDatabase rolled the row back.
 * 2. QrCodeService::regenerate() interrupted after its transaction commits, which
 *    leaves the superseded image behind.
 *
 * Soft-deleted rows count as referenced: they keep the audit trail of retired
 * tokens, so their images must survive. Reports only unless --force is passed.
 */
#[Signature('qr:prune-orphans
    {--force : Actually delete. Without it the command only reports.}
    {--prefix=equipment-qr : Directory on the persistent disk to scan}')]
#[Description('Delete equipment QR images on the persistent disk that no equipment_qr_codes row references')]
class PruneOrphanQrImages extends Command
{
    public function handle(): int
    {
        $diskName = persistent_disk();
        $disk = Storage::disk($diskName);
        $prefix = trim((string) $this->option('prefix'), '/');

        if (! $disk->exists($prefix)) {
            $this->info("Nothing to do: '{$prefix}' does not exist on disk '{$diskName}'.");

            return self::SUCCESS;
        }

        $this->info("Scanning '{$prefix}' on disk '{$diskName}'…");

        /** @var array<string, true> $referenced */
        $referenced = EquipmentQrCode::withTrashed()
            ->whereNotNull('qr_image_path')
            ->pluck('qr_image_path')
            ->mapWithKeys(fn (string $path): array => [ltrim($path, '/') => true])
            ->all();

        $files = $disk->allFiles($prefix);

        $orphans = array_values(array_filter(
            $files,
            fn (string $path): bool => ! isset($referenced[ltrim($path, '/')])
        ));

        $keptCount = count($files) - count($orphans);

        $this->line('  files on disk: '.count($files));
        $this->line('  referenced rows (incl. soft-deleted): '.count($referenced));
        $this->line('  keep: '.$keptCount);
        $this->line('  orphans: '.count($orphans));

        if ($orphans === []) {
            $this->info('No orphans found.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('Dry run — nothing deleted. Re-run with --force to delete the '.count($orphans).' orphan(s).');

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach (array_chunk($orphans, 500) as $chunk) {
            $disk->delete($chunk);
            $deleted += count($chunk);
            $this->line("  deleted {$deleted}/".count($orphans));
        }

        $this->pruneEmptyDirectories($diskName, $prefix);

        $this->newLine();
        $this->info("Done. Deleted {$deleted} orphan image(s); kept {$keptCount}.");

        return self::SUCCESS;
    }

    /**
     * Removes tenant directories left empty once their images are gone. The leak
     * created one directory per test tenant, so the inode count matters as much
     * as the bytes.
     */
    private function pruneEmptyDirectories(string $diskName, string $prefix): void
    {
        $disk = Storage::disk($diskName);
        $removed = 0;

        foreach ($disk->directories($prefix) as $directory) {
            if ($disk->allFiles($directory) === []) {
                $disk->deleteDirectory($directory);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->line("  removed {$removed} empty tenant directory(ies).");
        }
    }
}
