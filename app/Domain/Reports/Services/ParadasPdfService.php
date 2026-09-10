<?php

namespace App\Domain\Reports\Services;

use App\Domain\Assets\Enums\PlantSection;
use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Enums\StoppageReason;
use App\Domain\Reports\Contracts\PeriodReport;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Los paros que la pantalla está mostrando, en PDF.
 *
 * Recibe la **consulta ya filtrada** de la tabla, no un rango de fechas, y de ahí salen
 * tanto el listado como los gráficos. Esa es toda la idea: quien filtra por «Sección:
 * Prensado» quiere un informe de prensado, y si los gráficos se calcularan aparte —con el
 * servicio de analítica sobre el período— el documento traería una tabla de prensado y
 * unas tortas de toda la planta. Se contradiría a sí mismo, y quien lo lea no tiene cómo
 * notarlo.
 *
 * No implementa {@see PeriodReport} a propósito: ese contrato
 * pide una planta y dos fechas, y aquí lo que manda es la consulta. Es la misma razón por
 * la que `LostHoursPdfService` en su día no encajó en `PdfReport`.
 */
class ParadasPdfService
{
    public function __construct(private readonly ReportBrandingService $branding) {}

    /**
     * @param  Builder<EquipmentDowntimeEvent>  $query  la consulta de la tabla, con sus filtros
     * @param  list<string>  $filtros  los filtros aplicados, tal como los muestra la pantalla
     */
    public function generate(?Plant $plant, Builder $query, array $filtros = []): string
    {
        // Una sola lectura de la base: los gráficos y la tabla salen de la misma colección,
        // así que no pueden discrepar entre sí ni con la pantalla.
        $paros = (clone $query)
            ->with(['equipment:id,code,name'])
            ->orderBy('started_at')
            ->get();

        $tenant = $plant !== null ? Tenant::withoutGlobalScopes()->find($plant->tenant_id) : null;
        $documentNumber = $this->branding->generateDocumentNumber('PAR');

        return Pdf::loadView('reports.paradas-filtradas', [
            'plant' => $plant,
            'filtros' => $filtros,
            'paros' => $paros,
            'horasTotales' => round((float) $paros->sum(fn (EquipmentDowntimeEvent $p): float => $this->horas($p)), 2),
            'porTipo' => $this->agrupar($paros, fn (EquipmentDowntimeEvent $p): ?string => $p->reported_type?->value, ReportedStoppageType::class),
            'porCategoria' => $this->agrupar($paros, fn (EquipmentDowntimeEvent $p): ?string => $p->stoppage_category?->value, StoppageCategory::class),
            'porCausa' => $this->agrupar($paros, fn (EquipmentDowntimeEvent $p): ?string => $p->stoppage_reason?->value, StoppageReason::class),
            'porSeccion' => $this->agrupar($paros, fn (EquipmentDowntimeEvent $p): ?string => $p->section?->value, PlantSection::class),
            'porEquipo' => $this->porEquipo($paros),
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
        return 'PAROS-'.now()->format('Y-m-d').'.pdf';
    }

    /** Las horas que duró un paro; los que siguen abiertos cuentan hasta ahora. */
    private function horas(EquipmentDowntimeEvent $paro): float
    {
        return round($paro->started_at->diffInMinutes($paro->ended_at ?? now()) / 60, 2);
    }

    /**
     * Horas agrupadas por una columna de enumeración, con su etiqueta legible.
     *
     * Un paro sin clasificar no se reparte entre las categorías conocidas ni se descarta:
     * va a «Sin clasificar», que es información —dice que alguien no lo tipificó— y no
     * un hueco.
     *
     * @param  Collection<int, EquipmentDowntimeEvent>  $paros
     * @param  class-string  $enum
     * @return array<string, float>
     */
    private function agrupar($paros, callable $valor, string $enum): array
    {
        $totales = [];

        foreach ($paros as $paro) {
            $bruto = $valor($paro);
            $etiqueta = $bruto === null
                ? 'Sin clasificar'
                : ($enum::tryFrom($bruto)?->label() ?? $bruto);

            $totales[$etiqueta] = round(($totales[$etiqueta] ?? 0) + $this->horas($paro), 2);
        }

        arsort($totales);

        return $totales;
    }

    /**
     * @param  Collection<int, EquipmentDowntimeEvent>  $paros
     * @return array<string, float>
     */
    private function porEquipo($paros): array
    {
        $totales = [];

        foreach ($paros as $paro) {
            $etiqueta = $paro->equipment?->name ?? 'Sin equipo asignado';
            $totales[$etiqueta] = round(($totales[$etiqueta] ?? 0) + $this->horas($paro), 2);
        }

        arsort($totales);

        return $totales;
    }
}
