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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ReportForm extends Component
{
    use WithFileUploads;

    public Equipment $equipment;

    public EquipmentQrCode $qrCode;

    public bool $submitted = false;

    // ── Form fields ───────────────────────────────────────────────────────────

    #[Rule('required|string|min:10|max:2000')]
    public string $description = '';

    #[Rule('required|in:low,medium,high,critical')]
    public string $severity = 'medium';

    /** Foto opcional tomada desde la cámara del celular en planta. */
    #[Rule('nullable|image|max:5120')]
    public $photo = null;

    // Datos del reportante: ahora obligatorios (nombre y cargo), no un anexo opcional.
    #[Rule('required|string|min:3|max:255')]
    public string $reporterName = '';

    #[Rule('required|string|min:2|max:255')]
    public string $reporterPosition = '';

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

        $photoPath = $this->photo instanceof TemporaryUploadedFile
            ? $this->photo->store('issue-reports', persistent_disk())
            : null;

        EquipmentIssueReport::create([
            'equipment_id' => $this->equipment->id,
            'tenant_id' => $this->equipment->tenant_id,
            'qr_code_id' => $this->qrCode->id,
            'description' => $this->description,
            'photo_path' => $photoPath,
            'severity' => $this->severity,
            'reporter_name' => $this->reporterName,
            'reporter_position' => $this->reporterPosition,
            'reporter_user_id' => auth()->id(),
            'status' => 'open',
        ]);

        $this->reset(['description', 'severity', 'reporterName', 'reporterPosition', 'photo']);
        $this->severity = 'medium';
        $this->submitted = true;
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
