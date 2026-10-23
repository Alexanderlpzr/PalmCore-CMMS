<?php

namespace App\Livewire\HumanResources;

use App\Domain\Reports\Excel\FormatoHorasExtrasExcel;
use App\Domain\Reports\Services\FormatoHorasExtrasPdfService as Formato;
use App\Filament\Pages\HorasExtras;
use App\Models\Employee;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La hoja de horas extras de un trabajador en pantalla, como en el Excel (TH-NOM-F-001):
 * encabezado, todos los días del corte, el total, el resumen por clase, los valores en
 * pesos para quien ve sueldos y las firmas. Con «anterior» y «siguiente» se pasa de un
 * trabajador a otro, en el orden de la lista de «Horas extras», como las hojas del libro.
 */
class OvertimeSheet extends Component
{
    /** Los trabajadores de la lista, en su orden: entre ellos se navega. */
    #[Locked]
    public array $employeeIds = [];

    #[Locked]
    public string $tenantId = '';

    #[Locked]
    public string $from = '';

    #[Locked]
    public string $to = '';

    public string $employeeId = '';

    /**
     * @param  list<string>  $employeeIds
     */
    public function mount(array $employeeIds, string $employeeId, string $from, string $to): void
    {
        abort_unless(HorasExtras::canAccess(), 403);

        $this->tenantId = (string) Filament::getTenant()?->getKey();
        $this->employeeIds = array_values(array_map('strval', $employeeIds));
        $this->employeeId = in_array($employeeId, $this->employeeIds, true) ? $employeeId : ($this->employeeIds[0] ?? '');
        $this->from = CarbonImmutable::parse($from)->toDateString();
        $this->to = CarbonImmutable::parse($to)->toDateString();
    }

    public function previous(): void
    {
        $this->move(-1);
    }

    public function next(): void
    {
        $this->move(1);
    }

    /** Ir directo a un trabajador de la lista. */
    public function show(string $employeeId): void
    {
        if (in_array($employeeId, $this->employeeIds, true)) {
            $this->employeeId = $employeeId;
            unset($this->employee, $this->data);
        }
    }

    public function position(): int
    {
        return (int) array_search($this->employeeId, $this->employeeIds, true);
    }

    #[Computed]
    public function employee(): ?Employee
    {
        return Employee::query()->forTenant($this->tenantId)->whereKey($this->employeeId)->first();
    }

    /** @return array<string, string> id => nombre, en el orden de la lista */
    #[Computed]
    public function names(): array
    {
        $names = Employee::query()->forTenant($this->tenantId)->whereKey($this->employeeIds)->get()
            ->mapWithKeys(fn (Employee $employee): array => [$employee->getKey() => $employee->fullName()]);

        return collect($this->employeeIds)->filter(fn (string $id): bool => $names->has($id))->mapWithKeys(fn (string $id): array => [$id => $names[$id]])->all();
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function data(): ?array
    {
        return $this->employee ? app(Formato::class)->data($this->employee, CarbonImmutable::parse($this->from), CarbonImmutable::parse($this->to)) : null;
    }

    /** Los valores en pesos, solo para quien puede ver el sueldo de este trabajador. */
    public function showValues(): bool
    {
        return $this->employee !== null && (auth()->user()?->can('viewSalary', $this->employee) ?? false);
    }

    #[Computed]
    public function tenant(): ?Tenant
    {
        return Tenant::withoutGlobalScopes()->find($this->tenantId);
    }

    public function downloadExcel(): ?StreamedResponse
    {
        return $this->employee
            ? app(FormatoHorasExtrasExcel::class)->download(collect([$this->employee]), CarbonImmutable::parse($this->from), CarbonImmutable::parse($this->to), $this->showValues())
            : null;
    }

    public function downloadPdf(): ?StreamedResponse
    {
        if (! $this->employee) {
            return null;
        }

        $service = app(Formato::class);
        $employee = $this->employee;
        $showValues = $this->showValues();
        $to = CarbonImmutable::parse($this->to);

        return response()->streamDownload(
            fn () => print $service->generate($employee, CarbonImmutable::parse($this->from), $to, $showValues),
            $service->filename($employee, $to),
        );
    }

    public function render(): View
    {
        return view('filament.pages.partials.hoja-de-horas');
    }

    private function move(int $step): void
    {
        $next = $this->position() + $step;

        if (isset($this->employeeIds[$next])) {
            $this->employeeId = $this->employeeIds[$next];
            unset($this->employee, $this->data);
        }
    }
}
