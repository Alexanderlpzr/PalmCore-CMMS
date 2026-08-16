<?php

namespace App\Http\Controllers;

use App\Models\WorkOrderAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve un adjunto de OT desde el disco privado.
 *
 * Hace falta un controlador y no un enlace directo porque `private_disk` es un disco
 * local en producción: no expone URL, así que `Storage::url()` lanza excepción y el
 * archivo, aun estando guardado, era inalcanzable desde la interfaz. Es la misma
 * razón por la que las fotos del antes y el después se fueron al disco público.
 *
 * Aquí el archivo se transmite, no se publica: la autorización es la pertenencia al
 * tenant dueño del adjunto, comprobada contra el usuario de la sesión y no contra el
 * id que venga en la URL.
 */
class WorkOrderAttachmentDownloadController extends Controller
{
    public function __invoke(Request $request, WorkOrderAttachment $attachment): StreamedResponse
    {
        abort_unless(
            $request->user()->tenants()->where('tenants.id', $attachment->tenant_id)->exists(),
            403,
            'Access denied.'
        );

        $disk = Storage::disk(private_files_disk());

        abort_unless($disk->exists($attachment->file_path), 404, 'El archivo ya no está disponible.');

        return $disk->download($attachment->file_path, $attachment->file_name);
    }
}
