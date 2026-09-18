<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve un documento de la carpeta del trabajador desde el disco privado.
 *
 * Mismo motivo que {@see WorkOrderAttachmentDownloadController}: el disco privado no
 * expone URL. Aquí además se pide el permiso de ver documentos y no solo pertenecer al
 * tenant, porque portería ve al trabajador pero no tiene por qué abrir su examen médico.
 */
class EmployeeDocumentDownloadController extends Controller
{
    public function __invoke(Request $request, EmployeeDocument $document): StreamedResponse
    {
        abort_unless(
            $request->user()->tenants()->where('tenants.id', $document->tenant_id)->exists(),
            403,
            'Access denied.'
        );

        setPermissionsTeamId($document->tenant_id);
        Gate::forUser($request->user())->authorize('viewDocuments', $document->employee);

        $disk = Storage::disk(private_files_disk());

        abort_unless($disk->exists($document->file_path), 404, 'El archivo ya no está disponible.');

        return $disk->download($document->file_path, $document->file_name);
    }
}
