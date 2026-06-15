<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenancePlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_number' => $this->plan_number,
            'name' => $this->name,
            'description' => $this->description,
            'trigger_source' => $this->trigger_source?->value,
            'time_frequency' => $this->time_frequency?->value,
            'meter_interval' => $this->meter_interval,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'frequency_label' => $this->frequencyLabel(),
            'is_active' => $this->is_active,
            'last_generated_at' => $this->last_generated_at?->toISOString(),
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment ? [
                'id' => $this->equipment->id,
                'code' => $this->equipment->code,
                'name' => $this->equipment->name,
            ] : null),
            'responsible_user' => $this->whenLoaded('responsibleUser', fn () => $this->responsibleUser ? [
                'id' => $this->responsibleUser->id,
                'name' => $this->responsibleUser->name,
            ] : null),
            'schedule' => $this->whenLoaded('schedule', fn () => $this->schedule ? [
                'next_due_at' => $this->schedule->next_due_at?->toISOString(),
                'next_due_meter' => $this->schedule->next_due_meter,
                'last_completed_at' => $this->schedule->last_completed_at?->toISOString(),
                'last_completed_meter' => $this->schedule->last_completed_meter,
                'times_executed' => $this->schedule->times_executed,
                'is_overdue' => $this->schedule->isOverdueByTime(),
            ] : null),
            'tasks' => $this->whenLoaded('tasks', fn () => $this->tasks->map(fn ($t) => [
                'id' => $t->id,
                'sort_order' => $t->sort_order,
                'title' => $t->title,
                'description' => $t->description,
                'estimated_minutes' => $t->estimated_minutes,
            ])->values()),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
