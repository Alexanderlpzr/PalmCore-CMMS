<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off, manually-invoked migration of existing local files to the configured
 * cloud disk (Cloudflare R2). It COPIES only — local files are never deleted, so
 * the operation is safe to re-run and to roll back. Each file is verified by
 * comparing the MD5 checksum of the source and the written target.
 *
 * Never wired into deploys; run by an operator once R2 env vars are set.
 */
class MigrateStorageToCloud extends Command
{
    protected $signature = 'storage:migrate-to-cloud
        {--scope=all : Which set to migrate: persistent, private, or all}
        {--dry-run : List what would be copied without writing anything}
        {--force : Overwrite target files even if a matching checksum already exists}';

    protected $description = 'Copy persistent local files to the configured cloud disk (R2), verifying checksums. Does not delete local files.';

    public function handle(): int
    {
        $scope = (string) $this->option('scope');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        /** @var array<string, array{source: string, target: string}> $pairs */
        $pairs = [];

        if (in_array($scope, ['all', 'persistent'], true)) {
            $pairs['persistent'] = ['source' => 'public', 'target' => persistent_disk()];
        }

        if (in_array($scope, ['all', 'private'], true)) {
            $pairs['private'] = ['source' => 'work_orders_private', 'target' => private_files_disk()];
        }

        if ($pairs === []) {
            $this->error('Invalid --scope. Use: persistent, private, or all.');

            return self::FAILURE;
        }

        $copied = $skipped = $failed = 0;

        foreach ($pairs as $label => $pair) {
            ['source' => $source, 'target' => $target] = $pair;

            if ($source === $target) {
                $this->warn("[{$label}] target disk equals source ('{$source}') — set the cloud disk via env (PERSISTENT_DISK/PRIVATE_DISK) before migrating. Skipping.");

                continue;
            }

            $sourceDisk = Storage::disk($source);
            $targetDisk = Storage::disk($target);
            $files = $sourceDisk->allFiles();

            $this->info("[{$label}] '{$source}' → '{$target}': ".count($files).' file(s)'.($dryRun ? ' (dry-run)' : ''));

            foreach ($files as $path) {
                try {
                    $contents = $sourceDisk->get($path);
                    $sourceHash = md5((string) $contents);

                    if (! $force && $targetDisk->exists($path) && md5((string) $targetDisk->get($path)) === $sourceHash) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $this->line("  would copy: {$path}");
                        $copied++;

                        continue;
                    }

                    $targetDisk->put($path, $contents);

                    if (md5((string) $targetDisk->get($path)) !== $sourceHash) {
                        $this->error("  checksum mismatch (left local intact): {$path}");
                        $failed++;

                        continue;
                    }

                    $copied++;
                } catch (\Throwable $e) {
                    $this->error("  failed: {$path} — {$e->getMessage()}");
                    $failed++;
                }
            }
        }

        $this->newLine();
        $this->info("Done. Copied: {$copied} · Skipped (already present): {$skipped} · Failed: {$failed}".($dryRun ? ' (dry-run)' : ''));
        $this->line('Local files were not deleted.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
