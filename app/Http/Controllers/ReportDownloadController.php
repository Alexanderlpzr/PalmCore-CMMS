<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportDownloadController extends Controller
{
    public function download(Request $request): StreamedResponse
    {
        $path = $request->query('path', '');
        $tenantId = Str::before($path, '/');

        abort_unless(
            $request->user()->tenants()->where('tenants.id', $tenantId)->exists(),
            403,
            'Access denied.'
        );

        $disk = Storage::disk('reports');

        abort_unless($disk->exists($path), 404, 'Report not found or expired.');

        return $disk->download($path, basename($path));
    }
}
