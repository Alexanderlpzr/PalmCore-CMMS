<?php

namespace App\Domain\Reports\Services;

use App\Models\Tenant;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Las órdenes de trabajo que la pantalla está mostrando, en PDF.
 *
 * Mismo principio que {@see ParadasPdfService}: recibe la **consulta ya filtrada** de la
 * tabla —con la pestaña, el rango de fechas y los demás filtros aplicados— y de ahí salen
 * el listado y los gráficos. Si se armara aparte con un rango de fechas, un PDF filtrado
 * por «Sección: Prensado» traería tortas de toda la planta.
 *
 * No sustituye a {@see PendingWorkOrdersPdfService}: ese es un informe fijo —todas las
 * pendientes— que también se pide desde el centro de informes, sin pantalla detrás.
 */
class OrdenesTrabajoPdfService
{
    public function __construct(private readonly ReportBrandingService $branding) {}

    /**
     * @param  Builder<WorkOrder>  $query  la consulta de la tabla, con sus filtros
     * @param  list<string>  $filtros  los filtros aplicados, tal como los muestra la pantalla
     */
    public function generate(string $tenantId, Builder $query, array $filtros = []): string
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        $documentNumber = $this->branding->generateDocumentNumber('OT');

        return Pdf::loadView('reports.ordenes-trabajo-filtradas', [
            ...$this->datos($query, $filtros),
            'tenant' => $tenant,
            'logoBase64' => $this->branding->logoBase64($tenant),
            'documentNumber' => $documentNumber,
            'documentVersion' => ReportBrandingService::DOCUMENT_VERSION,
            'qrBase64' => $this->branding->qrBase64($this->branding->documentIdentityPayload($documentNumber, $tenant)),
            'generatedAt' => now(),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'dpi' => 96])
            ->output();
    }

    public function filename(): string
    {
        return 'OT-'.now()->format('Y-m-d').'.pdf';
    }

    /**
     * Lo que la vista necesita, sin pasar por DomPDF. Público para que los tests miren el
     * HTML con la misma preparación que usa el PDF, y no con una armada a mano.
     *
     * @param  Builder<WorkOrder>  $query
     * @param  list<string>  $filtros
     * @return array<string, mixed>
     */
    public function datos(Builder $query, array $filtros = []): array
    {
        // Una sola lectura: tabla y gráficos salen de la misma colección.
        $ordenes = (clone $query)
            ->with(['equipment.area'])
            ->reorder()
            ->orderBy('planned_start_at')
            ->orderBy('work_order_number')
            ->get();

        $estados = $ordenes->map(fn (WorkOrder $ot): string => $ot->status->label())->unique()->values();

        return [
            'filtros' => $filtros,
            'ordenes' => $ordenes,
            'conEquipoParado' => $ordenes->where('equipment_stopped', true)->count(),
            // Estado solo como columna cuando distingue algo. Con todas las OT en el mismo
            // estado —lo normal en la pestaña Abiertas— la columna repetiría la misma
            // palabra en cada fila, que es por lo que se quitó del PDF de pendientes; se
            // dice una vez arriba. En el Histórico, donde conviven cerradas y canceladas,
            // sí hace falta.
            'estados' => $estados,
            'mostrarEstado' => $estados->count() > 1,
            // Igual con la fecha de ejecución: en las abiertas no existe todavía.
            'mostrarEjecutada' => $ordenes->contains(fn (WorkOrder $ot): bool => $ot->actual_end_at !== null),
            'porTipo' => $this->contar($ordenes, fn (WorkOrder $ot): ?string => $ot->work_order_type?->label()),
            'porClase' => $this->contar($ordenes, fn (WorkOrder $ot): ?string => $ot->maintenance_area?->label()),
            'porSeccion' => $this->contar($ordenes, fn (WorkOrder $ot): ?string => $ot->equipment?->area?->name),
        ];
    }

    /**
     * Cuántas OT caen en cada categoría. Sin clasificar va aparte y a la vista: dice que
     * alguien no lo llenó, y eso también es información.
     *
     * @param  Collection<int, WorkOrder>  $ordenes
     * @return array<string, int>
     */
    private function contar(Collection $ordenes, callable $etiqueta): array
    {
        $totales = [];

        foreach ($ordenes as $ot) {
            $clave = $etiqueta($ot) ?? 'Sin clasificar';
            $totales[$clave] = ($totales[$clave] ?? 0) + 1;
        }

        arsort($totales);

        return $totales;
    }
}
