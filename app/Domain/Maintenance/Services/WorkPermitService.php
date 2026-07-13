<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Maintenance\Enums\WorkPermitStatus;
use App\Domain\Maintenance\Enums\WorkPermitType;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkPermit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Permisos de trabajo de alto riesgo: emisión, aceptación, cierre y bloqueo.
 *
 * La regla que justifica todo lo demás vive en {@see assertPermitsInPlace()}: una
 * OT que declara trabajo en caliente o espacio confinado **no arranca** sin su
 * permiso firmado y vigente. Un permiso que no bloquea la ejecución es un archivo
 * adjunto, y un archivo adjunto no le salva la vida a nadie.
 */
class WorkPermitService
{
    /**
     * Emitir el permiso. Todavía no autoriza nada: falta que lo firme quien va a
     * hacer el trabajo.
     *
     * @param  array{
     *     hazards: string,
     *     controls: string,
     *     valid_from: CarbonInterface|string,
     *     valid_until: CarbonInterface|string,
     *     isolation_points?: array<int, string>|null,
     *     notes?: ?string,
     * }  $data
     *
     * @throws BusinessRuleException
     */
    public function issue(
        WorkOrder $workOrder,
        WorkPermitType $type,
        array $data,
        User $issuedBy,
    ): WorkPermit {
        $validFrom = Carbon::parse($data['valid_from']);
        $validUntil = Carbon::parse($data['valid_until']);

        if ($validUntil->lessThanOrEqualTo($validFrom)) {
            throw new BusinessRuleException('Un permiso no puede vencer antes de empezar a regir.');
        }

        $isolationPoints = array_values(array_filter($data['isolation_points'] ?? []));

        // Un permiso de espacio confinado o de LOTO sin puntos de aislamiento deja
        // el equipo energizado con alguien adentro. No se emite.
        if ($type->requiresIsolation() && $isolationPoints === []) {
            throw new BusinessRuleException(
                "Un permiso de «{$type->label()}» exige declarar los puntos de aislamiento (bloqueo y etiquetado)."
            );
        }

        return DB::transaction(function () use ($workOrder, $type, $data, $issuedBy, $validFrom, $validUntil, $isolationPoints): WorkPermit {
            // Un permiso vigente del mismo tipo ya cubre este trabajo: reemitirlo
            // duplicaría la autorización y dejaría dos vigencias corriendo.
            $existing = $workOrder->permits()
                ->where('permit_type', $type->value)
                ->whereIn('status', [WorkPermitStatus::Issued->value, WorkPermitStatus::Accepted->value])
                ->first();

            if ($existing !== null && ! $existing->isExpired()) {
                throw new BusinessRuleException(
                    "Esta OT ya tiene un permiso de «{$type->label()}» vigente ({$existing->permit_number})."
                );
            }

            return WorkPermit::create([
                'tenant_id' => $workOrder->tenant_id,
                'work_order_id' => $workOrder->id,
                'permit_number' => $this->generatePermitNumber($workOrder->tenant_id),
                'permit_type' => $type->value,
                'status' => WorkPermitStatus::Issued->value,
                'hazards' => $data['hazards'],
                'controls' => $data['controls'],
                'isolation_points' => $isolationPoints !== [] ? $isolationPoints : null,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'issued_by' => $issuedBy->id,
                'issued_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * La firma del ejecutante: le explicaron los riesgos y los acepta.
     *
     * No puede firmarlo el mismo que lo emitió. Un permiso auto-emitido y
     * auto-aceptado es un trámite consigo mismo — la segunda firma existe
     * justamente para que dos personas hayan mirado el trabajo.
     *
     * @throws BusinessRuleException
     */
    public function accept(WorkPermit $permit, User $acceptedBy): WorkPermit
    {
        if ($permit->status !== WorkPermitStatus::Issued) {
            throw new BusinessRuleException(
                "Solo se puede firmar un permiso emitido. Este está: {$permit->status->label()}."
            );
        }

        if ($permit->isExpired()) {
            throw new BusinessRuleException('Este permiso ya venció. Debe emitirse uno nuevo.');
        }

        if ($permit->issued_by === $acceptedBy->id) {
            throw new BusinessRuleException(
                'Quien emite el permiso no puede firmarlo como ejecutante: son dos responsabilidades distintas.'
            );
        }

        $permit->update([
            'status' => WorkPermitStatus::Accepted->value,
            'accepted_by' => $acceptedBy->id,
            'accepted_at' => now(),
        ]);

        return $permit->refresh();
    }

    /** Se terminó el trabajo: se retiran los candados y se cierra el permiso. */
    public function close(WorkPermit $permit, User $closedBy): WorkPermit
    {
        if (in_array($permit->status, [WorkPermitStatus::Closed, WorkPermitStatus::Cancelled], strict: true)) {
            return $permit;
        }

        $permit->update([
            'status' => WorkPermitStatus::Closed->value,
            'closed_by' => $closedBy->id,
            'closed_at' => now(),
        ]);

        return $permit->refresh();
    }

    public function cancel(WorkPermit $permit, User $cancelledBy): WorkPermit
    {
        $permit->update([
            'status' => WorkPermitStatus::Cancelled->value,
            'closed_by' => $cancelledBy->id,
            'closed_at' => now(),
        ]);

        return $permit->refresh();
    }

    /**
     * G4 — la puerta. Una OT que exige permisos no arranca sin ellos.
     *
     * Se exige un permiso **aceptado y vigente en este momento** por cada tipo que
     * la OT declaró. Emitido no basta (nadie firmó), y vencido tampoco: un permiso
     * de trabajo en caliente de ayer no cubre la chispa de hoy.
     *
     * @throws BusinessRuleException
     */
    public function assertPermitsInPlace(WorkOrder $workOrder, ?CarbonInterface $moment = null): void
    {
        $required = $this->requiredTypes($workOrder);

        if ($required === []) {
            return;
        }

        $moment ??= now();

        $missing = [];

        foreach ($required as $type) {
            $covered = $workOrder->permits
                ->where('permit_type', $type)
                ->contains(fn (WorkPermit $permit): bool => $permit->authorizesWorkAt($moment));

            if (! $covered) {
                $missing[] = $type->label();
            }
        }

        if ($missing !== []) {
            throw new BusinessRuleException(
                'No se puede iniciar el trabajo sin el permiso firmado y vigente de: '
                .implode(', ', $missing).'.',
                detail: "work_order:{$workOrder->id}",
            );
        }
    }

    /**
     * Cerrar los permisos que quedaron abiertos cuando el trabajo terminó.
     *
     * Un permiso abierto significa que el equipo sigue bloqueado y el espacio sigue
     * intervenido. Dejarlo así después de completar la OT es cómo un candado se
     * queda puesto una semana.
     */
    public function closeOpenPermits(WorkOrder $workOrder, User $actor): void
    {
        $workOrder->permits()
            ->whereIn('status', [WorkPermitStatus::Issued->value, WorkPermitStatus::Accepted->value])
            ->get()
            ->each(fn (WorkPermit $permit) => $this->close($permit, $actor));
    }

    /**
     * Los tipos de permiso que esta OT declaró necesitar.
     *
     * @return array<int, WorkPermitType>
     */
    public function requiredTypes(WorkOrder $workOrder): array
    {
        return array_values(array_filter(array_map(
            fn (string $value): ?WorkPermitType => WorkPermitType::tryFrom($value),
            $workOrder->required_permit_types ?? [],
        )));
    }

    /** PT-{AÑO}-{SECUENCIAL}, secuencial por tenant y año. */
    private function generatePermitNumber(string $tenantId): string
    {
        $year = now()->year;

        $last = WorkPermit::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('permit_number', 'like', "PT-{$year}-%")
            ->lockForUpdate()
            ->orderByRaw('CAST(RIGHT(permit_number, 5) AS INTEGER) DESC')
            ->value('permit_number');

        $sequence = $last !== null ? (int) substr($last, -5) + 1 : 1;

        return sprintf('PT-%d-%05d', $year, $sequence);
    }
}
