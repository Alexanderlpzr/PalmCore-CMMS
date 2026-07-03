<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    /** @return array<int, array{id: string, code: string|null, name: string}> */
    private function buildAncestors(): array
    {
        $ancestors = [];
        $current = $this->parent;
        while ($current) {
            array_unshift($ancestors, [
                'id' => $current->id,
                'code' => $current->code,
                'name' => $current->name,
            ]);
            $current = $current->relationLoaded('parent') ? $current->parent : null;
        }

        return $ancestors;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'asset_tag' => $this->asset_tag,
            'notes' => $this->notes,
            'status' => $this->status?->value,
            'criticality' => $this->criticality?->value,
            'priority' => $this->priority?->value,
            'is_active' => $this->is_active,
            'location_notes' => $this->location_notes,
            'technical_specs' => $this->technical_specs,
            'current_meter_reading' => $this->current_meter_reading,
            'meter_unit' => $this->meter_unit,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'installation_date' => $this->installation_date?->toDateString(),
            'commissioning_date' => $this->commissioning_date?->toDateString(),
            'warranty_expiry_date' => $this->warranty_expiry_date?->toDateString(),
            'useful_life_years' => $this->useful_life_years,
            'purchase_price' => $this->purchase_price,
            'replacement_cost' => $this->replacement_cost,
            'currency_code' => $this->currency_code,
            'last_failure_at' => $this->last_failure_at?->toISOString(),
            'retired_at' => $this->retired_at?->toISOString(),
            'retired_reason' => $this->retired_reason,
            'ancestors' => $this->whenLoaded('parent', fn () => $this->buildAncestors()),
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'id' => $this->parent->id,
                'code' => $this->parent->code,
                'name' => $this->parent->name,
            ] : null),
            'plant' => $this->whenLoaded('plant', fn () => [
                'id' => $this->plant->id,
                'code' => $this->plant->code,
                'name' => $this->plant->name,
            ]),
            'area' => $this->whenLoaded('area', fn () => $this->area ? [
                'id' => $this->area->id,
                'code' => $this->area->code,
                'name' => $this->area->name,
            ] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'manufacturer' => $this->whenLoaded('manufacturer', fn () => $this->manufacturer ? [
                'id' => $this->manufacturer->id,
                'name' => $this->manufacturer->name,
            ] : null),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null),
            'primary_photo_url' => $this->whenLoaded('primaryPhoto', fn () => $this->primaryPhoto
                ? file_signed_url(persistent_disk(), $this->primaryPhoto->file_path)
                : null
            ),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($p) => [
                'id' => $p->id,
                'url' => file_signed_url(persistent_disk(), $p->file_path),
                'caption' => $p->caption,
                'is_primary' => $p->is_primary,
            ])->values()),
            'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($d) => [
                'id' => $d->id,
                'title' => $d->title,
                'name' => $d->file_name,
                'url' => file_signed_url(persistent_disk(), $d->file_path),
                'type' => $d->document_type?->value,
                'size' => $d->file_size,
                'expires_at' => $d->expires_at?->toDateString(),
            ])->values()),
            'kpi' => $this->whenLoaded('kpi', fn () => $this->kpi ? [
                'mtbf_hours' => $this->kpi->mtbf_hours,
                'mttr_hours' => $this->kpi->mttr_hours,
                'availability_percentage' => $this->kpi->availability_percentage,
                'failure_count' => $this->kpi->failure_count,
                'downtime_hours' => $this->kpi->downtime_hours,
                'last_failure_at' => $this->kpi->last_failure_at?->toISOString(),
                'is_stale' => $this->kpi->is_stale,
            ] : null),
            'children' => $this->whenLoaded('children', fn () => $this->children->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'model' => $c->model,
                'serial_number' => $c->serial_number,
                'status' => $c->status?->value,
                'criticality' => $c->criticality?->value,
                'category' => $c->category ? [
                    'id' => $c->category->id,
                    'name' => $c->category->name,
                    'color' => $c->category->color,
                ] : null,
                'primary_photo_url' => $c->primaryPhoto
                    ? file_signed_url(persistent_disk(), $c->primaryPhoto->file_path)
                    : null,
                'kpi' => $c->kpi ? [
                    'failure_count' => $c->kpi->failure_count,
                    'availability_percentage' => $c->kpi->availability_percentage,
                    'mtbf_hours' => $c->kpi->mtbf_hours,
                ] : null,
                'last_work_order_at' => $c->lastWorkOrder?->created_at?->toISOString(),
                'next_due_at' => $c->relationLoaded('maintenancePlans')
                    ? $c->maintenancePlans
                        ->where('is_active', true)
                        ->filter(fn ($p) => $p->schedule?->next_due_at !== null)
                        ->sortBy(fn ($p) => $p->schedule->next_due_at)
                        ->first()
                        ?->schedule
                        ?->next_due_at
                        ?->toISOString()
                    : null,
            ])->values()),
            'active_work_orders_count' => $this->whenCounted('workOrders', fn () => $this->active_work_orders_count),
            'has_overdue_preventives' => $this->resource->offsetExists('overdue_preventives_flag')
                ? (bool) $this->overdue_preventives_flag
                : $this->whenLoaded('maintenancePlans',
                    fn () => $this->maintenancePlans
                        ->where('is_active', true)
                        ->filter(fn ($p) => $p->schedule?->next_due_at !== null && $p->schedule->next_due_at->isPast())
                        ->isNotEmpty(),
                    false
                ),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
