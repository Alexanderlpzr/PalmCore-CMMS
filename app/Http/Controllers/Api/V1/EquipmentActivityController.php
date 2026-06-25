<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\EquipmentDowntimeEvent;
use App\Models\EquipmentIssueReport;
use App\Models\EquipmentMeterReading;
use App\Models\EquipmentPhoto;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EquipmentActivityController extends Controller
{
    public function index(Request $request, string $id): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('equipment.read') && ! $request->user()->tokenCan('*'), 403);

        Equipment::findOrFail($id);

        $perPage = min((int) ($request->per_page ?? 30), 100);
        $page = max((int) ($request->page ?? 1), 1);

        $events = collect();

        // ── 1. Work Orders ────────────────────────────────────────────────────

        $workOrders = WorkOrder::where('equipment_id', $id)
            ->select([
                'id', 'work_order_number', 'work_order_type', 'title',
                'status', 'priority', 'maintenance_plan_id',
                'created_at', 'completed_at', 'closed_at',
            ])
            ->get();

        foreach ($workOrders as $wo) {
            $isPreventive = $wo->maintenance_plan_id !== null;

            $events->push([
                'id' => "wo_created_{$wo->id}",
                'type' => 'work_order_created',
                'at' => $wo->created_at->toISOString(),
                'title' => "OT #{$wo->work_order_number} — {$wo->title}",
                'meta' => [
                    'ref_id' => $wo->id,
                    'ref_number' => $wo->work_order_number,
                    'work_order_type' => $wo->work_order_type?->value,
                    'status' => $wo->status?->value,
                    'priority' => $wo->priority?->value,
                    'is_preventive' => $isPreventive,
                ],
            ]);

            $closedAt = $wo->closed_at ?? $wo->completed_at;

            if ($closedAt && $isPreventive) {
                $events->push([
                    'id' => "preventive_executed_{$wo->id}",
                    'type' => 'preventive_executed',
                    'at' => $closedAt->toISOString(),
                    'title' => "Preventivo ejecutado — OT #{$wo->work_order_number}",
                    'meta' => [
                        'ref_id' => $wo->id,
                        'ref_number' => $wo->work_order_number,
                        'priority' => $wo->priority?->value,
                    ],
                ]);
            } elseif ($wo->closed_at) {
                $events->push([
                    'id' => "wo_closed_{$wo->id}",
                    'type' => 'work_order_closed',
                    'at' => $wo->closed_at->toISOString(),
                    'title' => "OT #{$wo->work_order_number} cerrada",
                    'meta' => [
                        'ref_id' => $wo->id,
                        'ref_number' => $wo->work_order_number,
                        'work_order_type' => $wo->work_order_type?->value,
                        'priority' => $wo->priority?->value,
                    ],
                ]);
            }
        }

        // ── 2. Repuestos consumidos ───────────────────────────────────────────

        $woIds = $workOrders->pluck('id');

        if ($woIds->isNotEmpty()) {
            $partsData = DB::table('work_order_parts')
                ->join('work_orders', 'work_order_parts.work_order_id', '=', 'work_orders.id')
                ->whereIn('work_order_parts.work_order_id', $woIds)
                ->whereNotNull(DB::raw('COALESCE(work_orders.closed_at, work_orders.completed_at)'))
                ->select([
                    'work_orders.id as wo_id',
                    'work_orders.work_order_number',
                    DB::raw('COALESCE(work_orders.closed_at, work_orders.completed_at) as event_at'),
                    'work_order_parts.part_code',
                    'work_order_parts.description',
                    'work_order_parts.quantity',
                    'work_order_parts.unit',
                ])
                ->get()
                ->groupBy('wo_id');

            foreach ($partsData as $woId => $parts) {
                $first = $parts->first();
                $events->push([
                    'id' => "parts_{$woId}",
                    'type' => 'parts_consumed',
                    'at' => $first->event_at,
                    'title' => $parts->count().' repuesto(s) consumido(s) en OT #'.$first->work_order_number,
                    'meta' => [
                        'ref_id' => $woId,
                        'ref_number' => $first->work_order_number,
                        'parts' => $parts->map(fn ($p) => [
                            'part_code' => $p->part_code,
                            'description' => $p->description,
                            'quantity' => (float) $p->quantity,
                            'unit' => $p->unit,
                        ])->values()->all(),
                    ],
                ]);
            }
        }

        // ── 3. Downtime events ────────────────────────────────────────────────

        EquipmentDowntimeEvent::where('equipment_id', $id)
            ->select(['id', 'cause_type', 'was_planned', 'duration_minutes', 'notes', 'started_at'])
            ->get()
            ->each(function ($dt) use ($events): void {
                $events->push([
                    'id' => "downtime_{$dt->id}",
                    'type' => 'downtime',
                    'at' => $dt->started_at->toISOString(),
                    'title' => 'Tiempo fuera de servicio registrado',
                    'meta' => [
                        'cause_type' => $dt->cause_type?->value,
                        'was_planned' => $dt->was_planned,
                        'duration_minutes' => $dt->duration_minutes,
                        'notes' => $dt->notes,
                    ],
                ]);
            });

        // ── 4. Meter readings ─────────────────────────────────────────────────

        EquipmentMeterReading::where('equipment_id', $id)
            ->select(['id', 'reading_value', 'reading_unit', 'recorded_at', 'notes'])
            ->get()
            ->each(function ($r) use ($events): void {
                $unit = $r->reading_unit?->value ?? $r->reading_unit;
                $events->push([
                    'id' => "meter_{$r->id}",
                    'type' => 'meter_reading',
                    'at' => $r->recorded_at->toISOString(),
                    'title' => "Lectura de medidor: {$r->reading_value} {$unit}",
                    'meta' => [
                        'value' => $r->reading_value,
                        'unit' => $unit,
                        'notes' => $r->notes,
                    ],
                ]);
            });

        // ── 5. Photos ─────────────────────────────────────────────────────────

        EquipmentPhoto::where('equipment_id', $id)
            ->select(['id', 'caption', 'file_path', 'is_primary', 'created_at'])
            ->get()
            ->each(function ($photo) use ($events): void {
                $events->push([
                    'id' => "photo_{$photo->id}",
                    'type' => 'photo_added',
                    'at' => $photo->created_at->toISOString(),
                    'title' => $photo->caption ?? 'Foto agregada',
                    'meta' => [
                        'ref_id' => $photo->id,
                        'url' => file_signed_url(persistent_disk(), $photo->file_path),
                        'is_primary' => $photo->is_primary,
                    ],
                ]);
            });

        // ── 6. Documents ──────────────────────────────────────────────────────

        EquipmentDocument::where('equipment_id', $id)
            ->select(['id', 'title', 'file_name', 'document_type', 'created_at'])
            ->get()
            ->each(function ($doc) use ($events): void {
                $events->push([
                    'id' => "doc_{$doc->id}",
                    'type' => 'document_added',
                    'at' => $doc->created_at->toISOString(),
                    'title' => $doc->title ?? $doc->file_name ?? 'Documento agregado',
                    'meta' => [
                        'ref_id' => $doc->id,
                        'file_name' => $doc->file_name,
                        'document_type' => $doc->document_type?->value,
                    ],
                ]);
            });

        // ── 7. Failure reports ────────────────────────────────────────────────

        EquipmentIssueReport::where('equipment_id', $id)
            ->select(['id', 'description', 'severity', 'reporter_name', 'created_at'])
            ->get()
            ->each(function ($issue) use ($events): void {
                $events->push([
                    'id' => "issue_{$issue->id}",
                    'type' => 'failure_reported',
                    'at' => $issue->created_at->toISOString(),
                    'title' => 'Falla reportada',
                    'meta' => [
                        'severity' => $issue->severity?->value,
                        'description' => mb_substr($issue->description ?? '', 0, 150),
                        'reporter_name' => $issue->reporter_name,
                    ],
                ]);
            });

        // ── Sort + paginate ───────────────────────────────────────────────────

        $sorted = $events->sortByDesc('at')->values();
        $total = $sorted->count();
        $offset = ($page - 1) * $perPage;

        return response()->json([
            'data' => $sorted->slice($offset, $perPage)->values(),
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / max($perPage, 1)),
                'has_more' => $offset + $perPage < $total,
            ],
        ]);
    }
}
