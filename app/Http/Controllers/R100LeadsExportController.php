<?php

namespace App\Http\Controllers;

use App\Models\R100Lead;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga la lista de interesados de la calculadora R-100 en CSV (se abre con
 * Excel). Solo super admin: la ruta lleva auth + super-admin.
 */
class R100LeadsExportController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $filename = 'interesados-r100-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            // BOM para que Excel lea bien las tildes.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Correo', 'Autorizó el', 'Última vez'], ';');

            R100Lead::query()->orderBy('created_at')->each(function (R100Lead $lead) use ($out): void {
                fputcsv($out, [
                    $lead->email,
                    $lead->consented_at->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                    $lead->updated_at->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                ], ';');
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
