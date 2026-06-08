<?php

namespace App\Livewire\Equipment;

use App\Domain\Assets\Enums\IssueSeverity;
use App\Models\Equipment;
use App\Models\EquipmentIssueReport;
use App\Models\EquipmentQrCode;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Rule;
use Livewire\Component;

class ReportForm extends Component
{
    public Equipment $equipment;

    public EquipmentQrCode $qrCode;

    public bool $submitted = false;

    // ── Form fields ───────────────────────────────────────────────────────────

    #[Rule('required|string|min:10|max:2000')]
    public string $description = '';

    #[Rule('required|in:low,medium,high,critical')]
    public string $severity = 'medium';

    #[Rule('nullable|string|max:255')]
    public string $reporterName = '';

    #[Rule('nullable|string|max:50')]
    public string $reporterPhone = '';

    // ── Actions ───────────────────────────────────────────────────────────────

    public function submit(): void
    {
        $key = 'issue-report:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            $this->addError('description', 'Demasiados reportes desde esta IP. Intenta en unos minutos.');

            return;
        }

        $this->validate();

        RateLimiter::hit($key, decaySeconds: 300);

        EquipmentIssueReport::create([
            'equipment_id'     => $this->equipment->id,
            'tenant_id'        => $this->equipment->tenant_id,
            'qr_code_id'       => $this->qrCode->id,
            'description'      => $this->description,
            'severity'         => $this->severity,
            'reporter_name'    => $this->reporterName ?: null,
            'reporter_phone'   => $this->reporterPhone ?: null,
            'reporter_user_id' => auth()->id(),
            'status'           => 'open',
        ]);

        $this->reset(['description', 'severity', 'reporterName', 'reporterPhone']);
        $this->severity   = 'medium';
        $this->submitted  = true;
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getSeverityOptions(): array
    {
        return IssueSeverity::options();
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.equipment.report-form');
    }
}
