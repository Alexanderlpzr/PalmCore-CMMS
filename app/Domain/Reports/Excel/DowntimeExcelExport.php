<?php

namespace App\Domain\Reports\Excel;

use App\Models\EquipmentDowntimeEvent;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Rap2hpoutre\FastExcel\FastExcel;

class DowntimeExcelExport
{
    public function __construct(private readonly string $tenantId) {}

    public function rows(): LazyCollection
    {
        // EquipmentDowntimeEvent has no soft deletes — historical facts are immutable
        return EquipmentDowntimeEvent::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->with(['equipment.plant', 'equipment.area'])
            ->orderBy('started_at', 'desc')
            ->lazy();
    }

    public function map(EquipmentDowntimeEvent $event): array
    {
        return [
            'Equipo' => $event->equipment?->name,
            'Planta' => $event->equipment?->plant?->name,
            'Área' => $event->equipment?->area?->name,
            'N° OT' => $event->work_order_number,
            'Inicio' => $event->started_at?->format('Y-m-d H:i'),
            'Fin' => $event->ended_at?->format('Y-m-d H:i'),
            'Duración (min)' => $event->duration_minutes,
            'Causa' => $event->cause_type?->label(),
            'Planificada' => $event->was_planned ? 'Sí' : 'No',
            'Modo de Falla' => $event->failure_mode,
            'Notas' => $event->notes,
        ];
    }

    public function store(string $path): void
    {
        $disk = Storage::disk('reports');
        $disk->makeDirectory(dirname($path));

        (new FastExcel($this->generate()))
            ->export($disk->path($path));
    }

    private function generate(): \Generator
    {
        foreach ($this->rows() as $row) {
            yield $this->map($row);
        }
    }
}
