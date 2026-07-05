<?php

use App\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Storage;

if (! function_exists('current_tenant_id')) {
    function current_tenant_id(): ?string
    {
        return CurrentTenant::id();
    }
}

if (! function_exists('current_tenant')) {
    function current_tenant(): mixed
    {
        return CurrentTenant::get();
    }
}

if (! function_exists('persistent_disk')) {
    /**
     * Disk for publicly-referenced persistent assets (photos, logos, QR, docs).
     * Local "public" in development, Cloudflare R2 in production.
     */
    function persistent_disk(): string
    {
        return config('filesystems.persistent_disk', 'public');
    }
}

if (! function_exists('private_files_disk')) {
    /**
     * Disk for access-controlled persistent files (work order media, etc.).
     */
    function private_files_disk(): string
    {
        return config('filesystems.private_disk', 'work_orders_private');
    }
}

if (! function_exists('format_hours_minutes')) {
    /**
     * Formats a decimal hour count as a human "Xh Ymin" string instead of a
     * raw decimal (e.g. 2.5 → "2h 30min", 0.75 → "45min", 3.0 → "3h").
     */
    function format_hours_minutes(null|int|float $hours): ?string
    {
        if ($hours === null) {
            return null;
        }

        $totalMinutes = (int) round($hours * 60);

        if ($totalMinutes <= 0) {
            return null;
        }

        $wholeHours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        return match (true) {
            $wholeHours > 0 && $minutes > 0 => "{$wholeHours}h {$minutes}min",
            $wholeHours > 0 => "{$wholeHours}h",
            default => "{$minutes}min",
        };
    }
}

if (! function_exists('file_signed_url')) {
    /**
     * Build a URL for a stored file. On object storage (S3/R2) a time-limited
     * signed URL is returned so private files are never publicly exposed; on
     * local disks the plain public URL is returned (preserving dev behaviour).
     */
    function file_signed_url(string $disk, ?string $path, int $minutes = 5): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $driver = config("filesystems.disks.{$disk}.driver");

        if ($driver === 's3') {
            return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($minutes));
        }

        return Storage::disk($disk)->url($path);
    }
}
