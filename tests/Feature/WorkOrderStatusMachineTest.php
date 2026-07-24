<?php

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Models\WorkOrder;

// ── State transitions ─────────────────────────────────────────────────────────

it('draft (Abierta) can transition to closed directly, planned, or cancelled', function () {
    $status = WorkOrderStatus::Draft;

    // Flujo vigente: una OT abierta se cierra directamente (Abierta → Cerrada).
    expect($status->canTransitionTo(WorkOrderStatus::Closed))->toBeTrue()
        ->and($status->canTransitionTo(WorkOrderStatus::Planned))->toBeTrue()
        ->and($status->canTransitionTo(WorkOrderStatus::Cancelled))->toBeTrue()
        ->and($status->canTransitionTo(WorkOrderStatus::InProgress))->toBeFalse();
});

it('planned can transition to in_progress, on_hold, or cancelled', function () {
    $status = WorkOrderStatus::Planned;

    expect($status->canTransitionTo(WorkOrderStatus::InProgress))->toBeTrue()
        ->and($status->canTransitionTo(WorkOrderStatus::OnHold))->toBeTrue()
        ->and($status->canTransitionTo(WorkOrderStatus::Cancelled))->toBeTrue()
        ->and($status->canTransitionTo(WorkOrderStatus::Draft))->toBeFalse();
});

it('in_progress can transition to on_hold, completed, closed, or cancelled', function () {
    $status = WorkOrderStatus::InProgress;

    expect($status->canTransitionTo(WorkOrderStatus::OnHold))->toBeTrue()
        ->and($status->canTransitionTo(WorkOrderStatus::Completed))->toBeTrue()
        ->and($status->canTransitionTo(WorkOrderStatus::Cancelled))->toBeTrue()
        ->and($status->canTransitionTo(WorkOrderStatus::Closed))->toBeTrue();
});

it('on_hold can only resume or cancel', function () {
    $status = WorkOrderStatus::OnHold;

    expect($status->canTransitionTo(WorkOrderStatus::InProgress))->toBeTrue()
        ->and($status->canTransitionTo(WorkOrderStatus::Cancelled))->toBeTrue()
        ->and($status->canTransitionTo(WorkOrderStatus::Completed))->toBeFalse();
});

it('completed can go to verified, closed, or back to in_progress', function () {
    $status = WorkOrderStatus::Completed;

    expect($status->canTransitionTo(WorkOrderStatus::Verified))->toBeTrue()
        ->and($status->canTransitionTo(WorkOrderStatus::InProgress))->toBeTrue()
        ->and($status->canTransitionTo(WorkOrderStatus::Closed))->toBeTrue();
});

it('verified can only go to closed', function () {
    $status = WorkOrderStatus::Verified;

    expect($status->canTransitionTo(WorkOrderStatus::Closed))->toBeTrue()
        ->and($status->canTransitionTo(WorkOrderStatus::Cancelled))->toBeFalse();
});

it('closed is terminal — no transitions allowed', function () {
    $status = WorkOrderStatus::Closed;

    expect($status->allowedTransitions())->toBeEmpty()
        ->and($status->isTerminal())->toBeTrue();
});

it('cancelled is terminal — no transitions allowed', function () {
    $status = WorkOrderStatus::Cancelled;

    expect($status->allowedTransitions())->toBeEmpty()
        ->and($status->isTerminal())->toBeTrue();
});

// ── isEditable ────────────────────────────────────────────────────────────────

it('only draft and planned are editable', function () {
    expect(WorkOrderStatus::Draft->isEditable())->toBeTrue()
        ->and(WorkOrderStatus::Planned->isEditable())->toBeTrue()
        ->and(WorkOrderStatus::InProgress->isEditable())->toBeFalse()
        ->and(WorkOrderStatus::Completed->isEditable())->toBeFalse()
        ->and(WorkOrderStatus::Closed->isEditable())->toBeFalse();
});

// ── isActive ──────────────────────────────────────────────────────────────────

it('in_progress and on_hold are active statuses', function () {
    expect(WorkOrderStatus::InProgress->isActive())->toBeTrue()
        ->and(WorkOrderStatus::OnHold->isActive())->toBeTrue()
        ->and(WorkOrderStatus::Draft->isActive())->toBeFalse()
        ->and(WorkOrderStatus::Completed->isActive())->toBeFalse();
});

// ── Model isEditable helper ───────────────────────────────────────────────────

it('work order model isEditable reflects status', function () {
    $draft = WorkOrder::factory()->create(['status' => WorkOrderStatus::Draft->value]);
    $planned = WorkOrder::factory()->planned()->create();
    $inProgress = WorkOrder::factory()->inProgress()->create();

    expect($draft->isEditable())->toBeTrue()
        ->and($planned->isEditable())->toBeTrue()
        ->and($inProgress->isEditable())->toBeFalse();
});
