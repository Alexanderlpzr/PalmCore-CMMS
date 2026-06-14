<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Enums\MaintenanceRequestType;
use App\Events\MaintenanceRequestApproved;
use App\Events\MaintenanceRequestCreated;
use App\Models\EquipmentIssueReport;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Notifications\MaintenanceRequestEmergencyNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class MaintenanceRequestService
{
    // ── Numbering ─────────────────────────────────────────────────────────────

    /**
     * Generate the next sequential request number for the tenant in the current year.
     * Uses lockForUpdate() inside a transaction to prevent race conditions in SaaS context.
     */
    public function generateRequestNumber(string $tenantId): string
    {
        $year = date('Y');

        $lastNumber = MaintenanceRequest::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('request_number', 'like', "MR-{$year}-%")
            ->lockForUpdate()
            ->orderByDesc('request_number')
            ->value('request_number');

        $sequence = 1;

        if ($lastNumber !== null) {
            $sequence = (int) substr($lastNumber, -5) + 1;
        }

        return sprintf('MR-%s-%05d', $year, $sequence);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    /**
     * Create a new MaintenanceRequest from the given data.
     * Emergency type skips draft/submitted and starts directly at under_review.
     */
    public function create(array $data, User $createdBy): MaintenanceRequest
    {
        $request = DB::transaction(function () use ($data, $createdBy): MaintenanceRequest {
            $type = MaintenanceRequestType::from($data['request_type']);
            $status = $type->startsUnderReview()
                ? MaintenanceRequestStatus::UnderReview
                : MaintenanceRequestStatus::Draft;

            $number = $this->generateRequestNumber($data['tenant_id']);

            $request = MaintenanceRequest::create([
                ...$data,
                'request_number' => $number,
                'status' => $status->value,
                'created_by' => $createdBy->id,
            ]);

            if ($type->startsUnderReview()) {
                $this->notifyEmergency($request);
            }

            return $request;
        });

        event(new MaintenanceRequestCreated($request));

        return $request;
    }

    /**
     * Convert an EquipmentIssueReport into a MaintenanceRequest.
     * Marks the source report as converted_to_mr.
     */
    public function createFromIssueReport(
        EquipmentIssueReport $issueReport,
        array $data,
        User $createdBy,
    ): MaintenanceRequest {
        return DB::transaction(function () use ($issueReport, $data, $createdBy): MaintenanceRequest {
            $request = $this->create([
                ...$data,
                'tenant_id' => $issueReport->tenant_id,
                'issue_report_id' => $issueReport->id,
                'equipment_id' => $issueReport->equipment_id,
            ], $createdBy);

            $issueReport->markConvertedToMr();

            return $request;
        });
    }

    // ── State Transitions ─────────────────────────────────────────────────────

    public function transition(
        MaintenanceRequest $request,
        MaintenanceRequestStatus $toStatus,
        User $actor,
        array $extra = [],
    ): MaintenanceRequest {
        if (! $request->status->canTransitionTo($toStatus)) {
            throw new \RuntimeException(
                "Cannot transition from [{$request->status->value}] to [{$toStatus->value}]."
            );
        }

        $timestamps = $this->transitionTimestamps($toStatus, $actor);

        $request->update(array_merge(['status' => $toStatus->value], $timestamps, $extra));

        $request = $request->refresh();

        if ($toStatus === MaintenanceRequestStatus::Approved) {
            event(new MaintenanceRequestApproved($request));
        }

        return $request;
    }

    // ── Notifications ─────────────────────────────────────────────────────────

    private function notifyEmergency(MaintenanceRequest $request): void
    {
        // TODO: resolve the correct recipient (tenant admin / plant manager)
        // This stub dispatches the notification asynchronously via ShouldQueue.
        Notification::route('mail', $request->tenant->contact_email ?? '')
            ->notify(new MaintenanceRequestEmergencyNotification($request));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function transitionTimestamps(MaintenanceRequestStatus $toStatus, User $actor): array
    {
        return match ($toStatus) {
            MaintenanceRequestStatus::Submitted => ['submitted_at' => now()],
            MaintenanceRequestStatus::UnderReview => ['reviewed_at' => now(), 'assigned_reviewer' => $actor->id],
            MaintenanceRequestStatus::Approved => ['approved_at' => now(), 'approved_by' => $actor->id],
            MaintenanceRequestStatus::Rejected => ['rejected_at' => now(), 'rejected_by' => $actor->id],
            default => [],
        };
    }
}
