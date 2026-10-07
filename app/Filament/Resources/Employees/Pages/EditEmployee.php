<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Domain\Reports\Services\FormatoHorasExtrasPdfService;
use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Employee;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La ficha del trabajador, en pestañas: «Información» y a su lado «Documentos»,
 * novedades, bonificaciones y descuentos.
 *
 * Antes el formulario iba arriba y las tablas relacionadas apiladas debajo, así que para
 * llegar a la carpeta de alguien había que desplazarse por toda su ficha. Combinar el
 * formulario con las pestañas de las relaciones deja cada cosa a un clic.
 *
 * @property Employee $record
 */
class EditEmployee extends EditRecord
{
    use HasBackAction;

    protected static string $resource = EmployeeResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Información';
    }

    public function getContentTabIcon(): string|\BackedEnum|Htmlable|null
    {
        return Heroicon::OutlinedIdentification;
    }

    /** El nombre de la persona, no «Editar trabajador». */
    public function getHeading(): string|Htmlable
    {
        return $this->record->fullName();
    }

    /** Cargo · documento · estado, como la cabecera de la ficha en papel. */
    public function getSubheading(): string|Htmlable|null
    {
        return collect([
            $this->record->position,
            $this->record->document_type.' '.$this->record->document_number,
            $this->record->status?->label(),
        ])->filter()->implode(' · ');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getBackAction(),
            $this->hoursFormAction(),
            DeleteAction::make(),
        ];
    }

    /**
     * El formato de horas extras (TH-FOR-002) del trabajador, hecho con las horas del
     * reloj. Abre en la ventana de corte en curso; se puede pedir cualquier otra.
     */
    private function hoursFormAction(): Action
    {
        return Action::make('formatoHoras')
            ->label('Formato de horas')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            // Lleva el salario y la calculadora con valores: lo ve quien ve sueldos, como el
            // desprendible.
            ->authorize(fn (): bool => auth()->user()?->can('viewSalary', $this->record) ?? false)
            ->modalHeading('Formato de horas extras')
            ->modalDescription('Las horas del reloj en el formato TH-FOR-002, repartidas como las liquida la nómina, para que el jefe inmediato lo firme.')
            ->fillForm(function (): array {
                $cutoffDay = (int) app(PayrollParameterService::class)->valueOrDefault(
                    PayrollParameter::HoursCutoffDay,
                    CarbonImmutable::today(),
                    $this->record->tenant_id,
                );
                [$from, $to] = PayrollRun::hoursWindowContaining(CarbonImmutable::today(), $cutoffDay);

                return ['desde' => $from->toDateString(), 'hasta' => $to->toDateString()];
            })
            ->schema([
                DatePicker::make('desde')->label('Horas desde')->required(),
                DatePicker::make('hasta')->label('Horas hasta')->required()->afterOrEqual('desde'),
            ])
            ->modalSubmitActionLabel('Descargar PDF')
            ->action(function (array $data): StreamedResponse {
                $service = app(FormatoHorasExtrasPdfService::class);
                $to = CarbonImmutable::parse($data['hasta']);

                return response()->streamDownload(
                    fn () => print $service->generate($this->record, CarbonImmutable::parse($data['desde']), $to),
                    $service->filename($this->record, $to),
                );
            });
    }
}
