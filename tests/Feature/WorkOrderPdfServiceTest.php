<?php

use App\Domain\Reports\Services\WorkOrderPdfService;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Models\WorkOrderSignature;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('work_orders_private');

    $this->tenant = Tenant::factory()->create();
});

it('generates a PDF even when a signature file is missing from disk', function () {
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id]);

    // image_path references a file that was never actually stored (e.g. deleted,
    // or storage misconfigured) — this used to crash DomPDF with a malformed
    // "data:;base64," image src instead of falling back to "sin firma".
    WorkOrderSignature::factory()->create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $workOrder->id,
        'image_path' => 'signatures/does-not-exist.png',
    ]);

    $pdf = app(WorkOrderPdfService::class)->generate($this->tenant->id, $workOrder->id);

    expect($pdf)->toStartWith('%PDF');
});
