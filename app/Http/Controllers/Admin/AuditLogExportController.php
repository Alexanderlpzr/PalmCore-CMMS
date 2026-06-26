<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogExportController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $query = AuditLog::query()
            ->with(['user:id,name', 'tenant:id,name'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->auditable_type);
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date('date_from')->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date('date_to')->endOfDay());
        }

        $filename = 'audit_logs_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Fecha',
                'Accion',
                'Modelo',
                'ID Registro',
                'Usuario',
                'Empresa',
                'IP',
                'Valores Anteriores',
                'Valores Nuevos',
            ]);

            $query->chunk(500, function ($records) use ($handle): void {
                foreach ($records as $record) {
                    fputcsv($handle, [
                        $record->created_at?->format('Y-m-d H:i:s'),
                        $record->event,
                        class_basename($record->auditable_type),
                        $record->auditable_id,
                        $record->user?->name ?? 'Sistema',
                        $record->tenant?->name ?? '—',
                        $record->ip_address ?? '—',
                        $record->old_values ? json_encode($record->old_values) : '',
                        $record->new_values ? json_encode($record->new_values) : '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
