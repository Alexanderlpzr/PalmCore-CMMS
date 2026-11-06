<?php

namespace App\Domain\Safety\Services;

use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Maintenance\Enums\IssueReportStatus;
use App\Domain\Notifications\InboxNotification;
use App\Domain\Notifications\NotificationKind;
use App\Domain\Notifications\Recipients;
use App\Domain\Safety\Enums\FindingPriority;
use App\Domain\Safety\Enums\FindingStatus;
use App\Domain\Safety\Enums\ResponsibleArea;
use App\Filament\Resources\Safety\SstFindingResource;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\EquipmentIssueReport;
use App\Models\SstFinding;
use App\Models\SstFindingTask;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Throwable;

/**
 * Las reglas de los hallazgos de SST.
 *
 * SST reporta el hallazgo y elige las áreas que lo corrigen; cada una lleva su parte. La
 * de mantenimiento entra como reporte de novedad del equipo —o, si el hallazgo no es de
 * un equipo, del «Infraestructura — {Sección}» de su sección—, y de ahí sigue el flujo de
 * siempre: mantenimiento lo revisa, lo vuelve OT y al cerrarla su parte queda hecha. Las
 * demás áreas la marcan a mano. Cuando todas terminan el hallazgo queda «Ejecutado», y SST
 * lo verifica.
 *
 * Avisa por la campana de Fronda: al reportar, al vencerse, al quedar listo para
 * verificar y al verificarse.
 */
class SafetyFindingService
{
    /** El cargo con que firma sus reportes de novedad: así no se avisa dos veces a mantenimiento. */
    public const REPORTER_POSITION = 'Seguridad y Salud en el Trabajo';

    /**
     * @param  array{reported_on: string|CarbonImmutable, description?: ?string, area_id?: ?string, equipment_id?: ?string, location_detail?: ?string, priority: string|FindingPriority, action_plan?: ?string, areas: list<string|ResponsibleArea>, photo_before_path?: ?string, photo_after_path?: ?string, number?: ?int}  $data
     */
    public function create(string $tenantId, array $data, ?User $createdBy, bool $notify = true, bool $reportToMaintenance = true): SstFinding
    {
        $finding = DB::transaction(function () use ($tenantId, $data, $createdBy, $reportToMaintenance): SstFinding {
            $priority = $data['priority'] instanceof FindingPriority ? $data['priority'] : FindingPriority::from($data['priority']);
            $reportedOn = CarbonImmutable::parse($data['reported_on'])->startOfDay();

            $finding = SstFinding::query()->create([
                'tenant_id' => $tenantId,
                'number' => $data['number'] ?? $this->nextNumber($tenantId),
                'reported_on' => $reportedOn,
                'description' => $data['description'] ?? null,
                'area_id' => $data['area_id'] ?? null,
                'equipment_id' => $data['equipment_id'] ?? null,
                'location_detail' => $data['location_detail'] ?? null,
                'priority' => $priority,
                'action_plan' => $data['action_plan'] ?? null,
                'due_on' => $reportedOn->addDays($priority->days()),
                'status' => FindingStatus::Pendiente,
                'photo_before_path' => $data['photo_before_path'] ?? null,
                'photo_after_path' => $data['photo_after_path'] ?? null,
                'created_by' => $createdBy?->id,
            ]);

            foreach ($this->areasOf($data['areas']) as $area) {
                $task = $finding->tasks()->create([
                    'tenant_id' => $tenantId,
                    'area' => $area,
                    'status' => FindingStatus::Pendiente,
                ]);

                if ($area === ResponsibleArea::Mantenimiento && $reportToMaintenance) {
                    $this->reportToMaintenance($finding, $task, $createdBy);
                }
            }

            return $finding;
        });

        if ($notify) {
            $this->notifyCreated($finding, $createdBy);
        }

        return $finding;
    }

    /** El área empezó a corregir su parte. */
    public function start(SstFindingTask $task, ?User $user = null): void
    {
        if ($task->status !== FindingStatus::Pendiente) {
            return;
        }

        $task->update(['status' => FindingStatus::EnEjecucion, 'started_at' => now()]);

        $this->refreshStatus($this->findingOf($task));
    }

    /**
     * El área terminó su parte. La fecha de corrección es el día en que quedó corregido;
     * la del hallazgo, la de la última área en terminar.
     */
    public function execute(SstFindingTask $task, ?User $user, ?CarbonImmutable $on = null, ?string $notes = null, ?string $photoAfterPath = null): void
    {
        DB::transaction(function () use ($task, $user, $on, $notes, $photoAfterPath): void {
            $task->update([
                'status' => FindingStatus::Ejecutado,
                'started_at' => $task->started_at ?? now(),
                'executed_on' => ($on ?? SstFinding::today($task->tenant_id))->toDateString(),
                'executed_by' => $user?->id,
                'notes' => $notes ?? $task->notes,
            ]);

            if ($photoAfterPath) {
                $this->findingOf($task)->update(['photo_after_path' => $photoAfterPath]);
            }
        });

        $this->refreshStatus($this->findingOf($task));
    }

    /** SST revisó el arreglo: el hallazgo se cierra. */
    public function verify(SstFinding $finding, User $user, ?string $notes = null, ?string $photoAfterPath = null): void
    {
        if ($finding->status !== FindingStatus::Ejecutado) {
            throw new \DomainException('Solo se verifica un hallazgo ejecutado por todas sus áreas.');
        }

        $finding->update([
            'status' => FindingStatus::Verificado,
            'verified_at' => now(),
            'verified_by' => $user->id,
            'verification_notes' => $notes,
            'photo_after_path' => $photoAfterPath ?: $finding->photo_after_path,
        ]);

        $this->send(
            $this->recipients($finding, [...$this->taskRoles($finding), 'sst'], except: $user)->push($finding->createdBy)->filter()->unique('id')->reject(fn (User $recipient): bool => $recipient->is($user)),
            "Hallazgo {$finding->code()} verificado",
            Str::limit((string) ($finding->description ?: $finding->locationLabel()), 120).' — SST revisó la corrección y lo cerró.',
            'success',
            $finding,
        );
    }

    /**
     * Lo que pasa en mantenimiento llega a su parte: el reporte de novedad se volvió OT
     * («En ejecución») o la OT se cerró y lo resolvió («Ejecutado»).
     */
    public function syncFromIssueReport(EquipmentIssueReport $report): void
    {
        $task = SstFindingTask::withoutGlobalScopes()->where('issue_report_id', $report->id)->first();

        if ($task === null || $task->status === FindingStatus::Ejecutado) {
            return;
        }

        match ($report->status) {
            IssueReportStatus::ConvertedToWO => $this->start($task),
            IssueReportStatus::Resolved => $this->execute(
                $task,
                ($workOrder = $report->workOrder()->first())?->completed_by ? User::withoutGlobalScopes()->find($workOrder->completed_by) : null,
                SstFinding::today($report->tenant_id),
                $workOrder ? "OT {$workOrder->work_order_number}" : null,
            ),
            default => null,
        };
    }

    /** El estado del hallazgo sale de sus partes. Uno ya verificado no se mueve. */
    public function refreshStatus(SstFinding $finding): void
    {
        $finding->refresh();

        if ($finding->status === FindingStatus::Verificado) {
            return;
        }

        $tasks = $finding->tasks()->get();
        $wasExecuted = $finding->status === FindingStatus::Ejecutado;

        $status = match (true) {
            $tasks->isNotEmpty() && $tasks->every(fn (SstFindingTask $task): bool => $task->status === FindingStatus::Ejecutado) => FindingStatus::Ejecutado,
            $tasks->contains(fn (SstFindingTask $task): bool => $task->status !== FindingStatus::Pendiente) => FindingStatus::EnEjecucion,
            default => FindingStatus::Pendiente,
        };

        $finding->update([
            'status' => $status,
            'executed_on' => $status === FindingStatus::Ejecutado ? $tasks->max(fn (SstFindingTask $task) => $task->executed_on?->toDateString()) : null,
        ]);

        if ($status === FindingStatus::Ejecutado && ! $wasExecuted) {
            $this->send(
                $this->recipients($finding, ['sst']),
                "Hallazgo {$finding->code()} listo para verificar",
                'Todas las áreas responsables lo corrigieron. Revise el arreglo y verifíquelo.',
                'info',
                $finding,
            );
        }
    }

    /**
     * Avisa una sola vez de cada hallazgo que se venció sin corregirse: a las áreas que lo
     * tienen pendiente y a SST.
     */
    public function notifyOverdue(?CarbonImmutable $today = null): int
    {
        $count = 0;

        SstFinding::withoutGlobalScopes()
            ->overdue($today)
            ->whereNull('overdue_notified_at')
            ->with('tasks')
            ->each(function (SstFinding $finding) use (&$count): void {
                $pending = $finding->tasks->filter(fn (SstFindingTask $task): bool => $task->status !== FindingStatus::Ejecutado);
                $roles = [...$pending->map(fn (SstFindingTask $task): string => $task->area->role())->all(), 'sst'];

                $this->send(
                    $this->recipients($finding, $roles),
                    "Hallazgo {$finding->code()} vencido",
                    'Prioridad '.mb_strtolower($finding->priority->label()).', vencía el '.$finding->due_on->format('d/m/Y').'. Falta: '
                        .$pending->map(fn (SstFindingTask $task): string => $task->area->label())->implode(', ').'.',
                    'danger',
                    $finding,
                );

                $finding->update(['overdue_notified_at' => now()]);
                $count++;
            });

        return $count;
    }

    /** Quién puede mover la parte de un área: esa área, SST o la administración. */
    public function canActOn(User $user, SstFindingTask $task): bool
    {
        if ($user->is_super_admin || $user->hasPermissionTo('sst-findings.manage')) {
            return true;
        }

        // La parte de mantenimiento se mueve con su OT.
        if ($task->area === ResponsibleArea::Mantenimiento && $task->issue_report_id !== null) {
            return false;
        }

        return $user->hasPermissionTo('sst-findings.execute') && $user->hasRole($task->area->role());
    }

    /**
     * El equipo al que va el reporte de novedad: el del hallazgo, o el de infraestructura
     * de su sección, que se crea la primera vez. Sin sección, el de infraestructura general.
     */
    public function maintenanceEquipment(SstFinding $finding): Equipment
    {
        if ($finding->equipment_id && ($equipment = Equipment::withoutGlobalScopes()->find($finding->equipment_id))) {
            return $equipment;
        }

        $area = $finding->area_id ? Area::withoutGlobalScopes()->find($finding->area_id) : null;
        $area ??= Area::withoutGlobalScopes()->where('tenant_id', $finding->tenant_id)->orderBy('sort_order')->orderBy('name')->first()
            ?? throw new \DomainException('La empresa no tiene secciones donde ubicar el hallazgo.');

        $code = $finding->area_id ? 'INF-'.Str::upper(Str::limit((string) ($area->code ?: Str::slug($area->name)), 40, '')) : 'INF-GEN';
        $name = $finding->area_id ? "Infraestructura — {$area->name}" : 'Infraestructura — General';

        $equipment = Equipment::withoutGlobalScopes()->withTrashed()->where('tenant_id', $finding->tenant_id)->where('code', $code)->first();

        if ($equipment?->trashed()) {
            $equipment->restore();
        }

        return $equipment ?? Equipment::query()->create([
            'tenant_id' => $finding->tenant_id,
            'plant_id' => $area->plant_id,
            'area_id' => $area->id,
            'code' => $code,
            'name' => $name,
            'status' => EquipmentStatus::Active,
            'notes' => 'Creado por Fronda para los hallazgos de SST que no son de un equipo: pisos, barandas, señalización, techos.',
            'is_active' => true,
        ]);
    }

    /** Las áreas que tienen algo pendiente en estos hallazgos: para filtrar «De mi área». */
    public static function areasOfUser(User $user): array
    {
        return collect(ResponsibleArea::cases())
            ->filter(fn (ResponsibleArea $area): bool => $user->hasRole($area->role()))
            ->map(fn (ResponsibleArea $area): string => $area->value)
            ->values()
            ->all();
    }

    private function reportToMaintenance(SstFinding $finding, SstFindingTask $task, ?User $createdBy): void
    {
        $equipment = $this->maintenanceEquipment($finding);

        $report = EquipmentIssueReport::query()->create([
            'tenant_id' => $finding->tenant_id,
            'equipment_id' => $equipment->id,
            'description' => trim("Hallazgo SST {$finding->code()}: ".($finding->description ?: 'ver foto')
                .($finding->location_detail ? " ({$finding->location_detail})" : '')
                .($finding->action_plan ? "\nPlan de acción: {$finding->action_plan}" : '')
                ."\nVence el {$finding->due_on->format('d/m/Y')}."),
            'photo_path' => $finding->photo_before_path,
            'severity' => $finding->priority->issueSeverity(),
            'reporter_name' => $createdBy?->name ?? 'SST',
            'reporter_position' => self::REPORTER_POSITION,
            'reporter_user_id' => $createdBy?->id,
            'status' => IssueReportStatus::Open,
        ]);

        $task->update(['issue_report_id' => $report->id]);
    }

    private function notifyCreated(SstFinding $finding, ?User $createdBy): void
    {
        // Proceso los recibe todos (pedido de la empresa); las demás áreas, los suyos.
        $roles = [...$this->taskRoles($finding), 'proceso', 'sst'];

        $this->send(
            $this->recipients($finding, $roles, except: $createdBy),
            "Nuevo hallazgo SST {$finding->code()} · prioridad ".mb_strtolower($finding->priority->label()),
            Str::limit((string) ($finding->description ?: 'Hallazgo con foto'), 120).' — '.$finding->locationLabel()
                .'. Responsables: '.$finding->tasks()->get()->map(fn (SstFindingTask $task): string => $task->area->label())->implode(', ')
                .'. Vence el '.$finding->due_on->format('d/m/Y').'.',
            $finding->priority === FindingPriority::Alta ? 'danger' : 'warning',
            $finding,
        );
    }

    /** @return list<string> */
    private function taskRoles(SstFinding $finding): array
    {
        return $finding->tasks()->get()->map(fn (SstFindingTask $task): string => $task->area->role())->unique()->values()->all();
    }

    /**
     * Los usuarios activos de la empresa con alguno de esos roles.
     *
     * @param  list<string>  $roles
     * @return Collection<int, User>
     */
    private function recipients(SstFinding $finding, array $roles, ?User $except = null): Collection
    {
        return Recipients::withRoles($finding->tenant_id, $roles, $except);
    }

    /**
     * El aviso a la campana de cada uno y, si activó los avisos en el celular, también
     * allá. «Abrir» lleva al hallazgo.
     *
     * @param  Collection<int, User>  $users
     */
    private function send(Collection $users, string $title, string $body, string $status, SstFinding $finding): void
    {
        if ($users->isEmpty()) {
            return;
        }

        try {
            $url = SstFindingResource::getUrl('view', ['record' => $finding], panel: 'admin', tenant: Tenant::query()->find($finding->tenant_id));
        } catch (Throwable) {
            $url = null;
        }

        NotificationFacade::send($users, new InboxNotification(
            kind: NotificationKind::Sst,
            title: $title,
            body: $body,
            status: $status,
            url: $url,
            tenantId: $finding->tenant_id,
            actionLabel: 'Ver hallazgo',
        ));
    }

    /**
     * @param  list<string|ResponsibleArea>  $areas
     * @return list<ResponsibleArea>
     */
    private function areasOf(array $areas): array
    {
        $areas = collect($areas)
            ->map(fn (string|ResponsibleArea $area): ResponsibleArea => $area instanceof ResponsibleArea ? $area : ResponsibleArea::from($area))
            ->unique(fn (ResponsibleArea $area): string => $area->value)
            ->values()
            ->all();

        if ($areas === []) {
            throw new \DomainException('El hallazgo necesita al menos un área responsable.');
        }

        return $areas;
    }

    private function findingOf(SstFindingTask $task): SstFinding
    {
        return SstFinding::withoutGlobalScopes()->findOrFail($task->sst_finding_id);
    }

    private function nextNumber(string $tenantId): int
    {
        return (int) SstFinding::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tenantId)->max('number') + 1;
    }
}
