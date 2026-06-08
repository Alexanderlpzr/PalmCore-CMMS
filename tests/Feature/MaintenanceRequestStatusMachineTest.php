<?php

use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;

// ── State machine unit tests ───────────────────────────────────────────────────

it('draft can transition to submitted or cancelled only', function () {
    $allowed = MaintenanceRequestStatus::Draft->allowedTransitions();

    expect($allowed)->toContain(MaintenanceRequestStatus::Submitted)
        ->and($allowed)->toContain(MaintenanceRequestStatus::Cancelled)
        ->and(count($allowed))->toBe(2);
});

it('submitted can transition to under_review or cancelled', function () {
    $allowed = MaintenanceRequestStatus::Submitted->allowedTransitions();

    expect($allowed)->toContain(MaintenanceRequestStatus::UnderReview)
        ->and($allowed)->toContain(MaintenanceRequestStatus::Cancelled);
});

it('under_review can transition to approved, rejected, or back to submitted', function () {
    $allowed = MaintenanceRequestStatus::UnderReview->allowedTransitions();

    expect($allowed)->toContain(MaintenanceRequestStatus::Approved)
        ->and($allowed)->toContain(MaintenanceRequestStatus::Rejected)
        ->and($allowed)->toContain(MaintenanceRequestStatus::Submitted);
});

it('approved can transition to converted or cancelled', function () {
    $allowed = MaintenanceRequestStatus::Approved->allowedTransitions();

    expect($allowed)->toContain(MaintenanceRequestStatus::Converted)
        ->and($allowed)->toContain(MaintenanceRequestStatus::Cancelled);
});

it('rejected can transition to submitted only', function () {
    $allowed = MaintenanceRequestStatus::Rejected->allowedTransitions();

    expect($allowed)->toContain(MaintenanceRequestStatus::Submitted)
        ->and(count($allowed))->toBe(1);
});

it('cancelled is terminal with no allowed transitions', function () {
    expect(MaintenanceRequestStatus::Cancelled->allowedTransitions())->toBe([])
        ->and(MaintenanceRequestStatus::Cancelled->isTerminal())->toBeTrue();
});

it('converted is terminal with no allowed transitions', function () {
    expect(MaintenanceRequestStatus::Converted->allowedTransitions())->toBe([])
        ->and(MaintenanceRequestStatus::Converted->isTerminal())->toBeTrue();
});

it('only draft and submitted are editable', function () {
    expect(MaintenanceRequestStatus::Draft->isEditable())->toBeTrue()
        ->and(MaintenanceRequestStatus::Submitted->isEditable())->toBeTrue()
        ->and(MaintenanceRequestStatus::UnderReview->isEditable())->toBeFalse()
        ->and(MaintenanceRequestStatus::Approved->isEditable())->toBeFalse();
});

it('canTransitionTo returns false for invalid targets', function () {
    expect(MaintenanceRequestStatus::Draft->canTransitionTo(MaintenanceRequestStatus::Approved))->toBeFalse()
        ->and(MaintenanceRequestStatus::Draft->canTransitionTo(MaintenanceRequestStatus::Submitted))->toBeTrue();
});
