<?php

namespace App\Filament\Pages;

use App\Domain\HumanResources\Enums\CertificateType;
use App\Domain\HumanResources\Enums\EmployeeDocumentType;
use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Domain\Reports\Services\CertificateWordService;
use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Los certificados de talento humano: se elige el trabajador, sale la lista de los que le
 * aplican —la certificación laboral si trabaja, los cuatro del egreso si se retiró— con
 * los datos de su ficha puestos, y se expide en el mismo Word de la empresa, con la firma
 * de quien expide, que queda en su carpeta.
 *
 * Quién expide es un trabajador de Personal con su firma escaneada, y lo cambia talento
 * humano en «Quién expide»: hoy el director de planta, cuando vuelva, la de gestión humana.
 */
class Certificados extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Talento Humano · Personal y ajustes';

    protected static ?string $navigationLabel = 'Certificados';

    protected static ?string $title = 'Certificados';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'certificados';

    /** El trabajador que llega elegido, desde su ficha. */
    #[Url(as: 'trabajador')]
    public ?string $employeeId = null;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->can('viewAny', Employee::class) ?? false)
            && ($user?->can('viewAnySalary', Employee::class) ?? false)
            && ($user?->can('create', EmployeeDocument::class) ?? false);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $employee = $this->employeeId ? $this->employee($this->employeeId) : null;

        $this->form->fill([
            'employee_id' => $employee?->id,
            'type' => null,
        ]);
    }

    public function getSubheading(): ?string
    {
        return 'Certificación laboral y documentos del egreso, firmados por quien expide y guardados en la carpeta del trabajador.';
    }

    // ── Formulario ────────────────────────────────────────────────────────────

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $type = fn (Get $get): ?CertificateType => CertificateType::tryFrom((string) $get('type'));

        return $schema->components([
            Section::make('Trabajador y documento')
                ->columns(2)
                ->schema([
                    Select::make('employee_id')
                        ->label('Trabajador')
                        ->options(fn (): array => $this->employeeOptions())
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('type', null);
                        }),
                    Select::make('type')
                        ->label('Documento')
                        ->options(function (Get $get): array {
                            $employee = $this->employee($get('employee_id'));

                            return $employee ? CertificateType::options($this->hasLeft($employee)) : [];
                        })
                        ->helperText(fn (Get $get): ?string => ($employee = $this->employee($get('employee_id')))
                            ? ($this->hasLeft($employee) ? 'Se retiró: salen los documentos del egreso.' : 'Está activo: sale la certificación laboral.')
                            : null)
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => $this->fillDefaults($get, $set)),
                ]),

            Section::make('Datos del documento')
                ->description('Salen de la ficha; corríjalos aquí si hace falta. Lo que cambie no toca la ficha.')
                ->columns(2)
                ->visible(fn (Get $get): bool => $type($get) !== null)
                ->schema([
                    DatePicker::make('issued_on')
                        ->label('Fecha de expedición')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required(),
                    DatePicker::make('start_date')
                        ->label('Fecha de ingreso')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required(),
                    DatePicker::make('end_date')
                        ->label('Fecha de retiro')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            if ($state) {
                                $set('exit_exam_deadline', app(CertificateWordService::class)->exitExamDeadline($this->tenant(), CarbonImmutable::parse($state))->toDateString());
                            }
                        })
                        ->visible(fn (Get $get): bool => $type($get)?->isForExit() ?? false),
                    Radio::make('salary_mode')
                        ->label('Salario en la certificación')
                        ->options(fn (Get $get): array => $this->salaryOptions($this->employee($get('employee_id'))))
                        ->disableOptionWhen(fn (string $value, Get $get): bool => $this->salaryOptionUnavailable($value, $this->employee($get('employee_id'))))
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                            // El promedio es salario variable: así lo dice el formato.
                            if ($state === 'average' && blank($get('salary_note'))) {
                                $set('salary_note', 'de tipo variable');
                            }
                        })
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => $type($get) === CertificateType::ActiveEmployment),
                    TextInput::make('salary')
                        ->label('Salario que dice la certificación')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(1)
                        ->required()
                        ->visible(fn (Get $get): bool => $type($get) === CertificateType::ActiveEmployment && $get('salary_mode') === 'other'),
                    TextInput::make('salary_note')
                        ->label('Después del salario')
                        ->placeholder('de tipo variable')
                        ->helperText('Opcional: lo que va después de la cifra, como «de tipo variable».')
                        ->maxLength(80)
                        ->visible(fn (Get $get): bool => $type($get) === CertificateType::ActiveEmployment && $get('salary_mode') !== 'none'),
                    TextInput::make('fund_name')
                        ->label('Fondo de cesantías (a quién va dirigida)')
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => $type($get) === CertificateType::SeveranceWithdrawal),
                    CheckboxList::make('delivered')
                        ->label('Documentos que recibió')
                        ->options(CertificateWordService::DELIVERED_DOCUMENTS)
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => $type($get) === CertificateType::Clearance),
                    TextInput::make('delivered_other')
                        ->label('Otro documento recibido')
                        ->maxLength(120)
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => $type($get) === CertificateType::Clearance),
                    TextInput::make('exit_exam_provider')
                        ->label('IPS y dirección')
                        ->required()
                        ->maxLength(200)
                        ->visible(fn (Get $get): bool => $type($get) === CertificateType::ExitExam),
                    DatePicker::make('exit_exam_deadline')
                        ->label('Plazo máximo para presentarse')
                        ->helperText('Cinco días hábiles después del retiro, sin festivos.')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->visible(fn (Get $get): bool => $type($get) === CertificateType::ExitExam),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.certificados-quien-expide'),

            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('expedir')
                ->footer([
                    Actions::make([
                        Action::make('expedir')
                            ->label('Expedir y descargar el Word')
                            ->icon(Heroicon::OutlinedArrowDownTray)
                            ->submit('expedir'),
                    ])->key('form-actions'),
                ]),

            View::make('filament.pages.certificados-expedidos'),
        ]);
    }

    /** Expide el certificado, lo guarda en la carpeta del trabajador y lo descarga. */
    public function expedir(): ?StreamedResponse
    {
        $state = $this->form->getState();
        $employee = $this->employee($state['employee_id'] ?? null);
        $type = CertificateType::tryFrom((string) ($state['type'] ?? ''));

        if (! $employee || ! $type) {
            return null;
        }

        /** @var User $user */
        $user = auth()->user();
        $service = app(CertificateWordService::class);

        try {
            $document = $service->issue($type, $employee, $this->tenant(), $state, $user);
        } catch (BusinessRuleException $e) {
            Notification::make()->title('No se expidió')->body($e->getMessage())->danger()->send();

            return null;
        }

        Notification::make()
            ->title('Certificado expedido')
            ->body('Quedó en la carpeta de '.$employee->fullName().'.')
            ->success()
            ->send();

        $bytes = (string) Storage::disk(private_files_disk())->get($document->file_path);

        return response()->streamDownload(fn () => print ($bytes), $document->file_name, ['Content-Type' => CertificateWordService::MIME]);
    }

    // ── Quién expide ──────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('quienExpide')
                ->label('Quién expide')
                ->icon(Heroicon::OutlinedPencil)
                ->color('gray')
                ->modalHeading('Quién expide los certificados')
                ->modalDescription('Firma todos los certificados que se expidan desde ahora. Los ya expedidos se quedan como salieron.')
                ->fillForm(fn (): array => $this->tenant()->certificateSettings())
                ->schema([
                    Select::make('signer_employee_id')
                        ->label('Quien expide')
                        ->helperText('Su nombre, cargo y cédula salen de su ficha en Personal.')
                        ->options(fn (): array => Employee::query()
                            ->forTenant($this->tenant()->id)
                            ->active()
                            ->orderBy('first_name')
                            ->get()
                            ->mapWithKeys(fn (Employee $e): array => [$e->id => $e->fullName().($e->position ? ' · '.$e->position : '')])
                            ->all())
                        ->searchable()
                        ->required(),
                    FileUpload::make('signature_upload')
                        ->label('Firma escaneada')
                        ->helperText('Imagen PNG o JPG de la firma, idealmente con fondo blanco o transparente. Déjelo vacío para conservar la firma actual; si cambia quien expide, suba la suya.')
                        ->disk(private_files_disk())
                        ->visibility('private')
                        ->directory(fn (): string => 'certificates/'.$this->tenant()->id)
                        ->image()
                        ->acceptedFileTypes(['image/png', 'image/jpeg'])
                        ->maxSize(2048),
                    TextInput::make('exit_exam_provider')
                        ->label('IPS del examen de egreso')
                        ->placeholder('Nombre de la IPS – dirección')
                        ->maxLength(200),
                ])
                ->action(function (array $data): void {
                    $this->saveSigner($data);
                }),
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function saveSigner(array $data): void
    {
        $tenant = $this->tenant();
        $current = $tenant->certificateSettings();
        $signerChanged = ($data['signer_employee_id'] ?? null) !== $current['signer_employee_id'];
        $upload = $data['signature_upload'] ?? null;
        $signature = $upload ?: ($signerChanged ? null : $current['signature_path']);

        // La firma de quien deja de expedir no se queda guardada.
        if ($current['signature_path'] && $current['signature_path'] !== $signature) {
            Storage::disk(private_files_disk())->delete($current['signature_path']);
        }

        $settings = $tenant->settings ?? [];
        data_set($settings, 'human_resources.certificates', [
            'signer_employee_id' => $data['signer_employee_id'] ?? null,
            'signature_path' => $signature,
            'exit_exam_provider' => trim((string) ($data['exit_exam_provider'] ?? '')) ?: null,
        ]);
        $tenant->forceFill(['settings' => $settings])->save();

        Notification::make()
            ->title('Listo')
            ->body($signature ? 'Los certificados salen con la firma de quien expide.' : 'Sin firma escaneada: los certificados salen con el espacio para firmar a mano.')
            ->success()
            ->send();
    }

    // ── Para las vistas ───────────────────────────────────────────────────────

    /** @return array{signer: ?Employee, signature: ?string, settings: array<string, mixed>} */
    public function signerSummary(): array
    {
        $service = app(CertificateWordService::class);
        $tenant = $this->tenant();

        return [
            'signer' => $service->signer($tenant),
            'signature' => $service->signatureBase64($tenant),
            'settings' => $tenant->certificateSettings(),
        ];
    }

    /** @return Collection<int, EmployeeDocument> los últimos expedidos */
    public function issuedCertificates(): Collection
    {
        return EmployeeDocument::query()
            ->forTenant($this->tenant()->id)
            ->where('document_type', EmployeeDocumentType::Certificado)
            ->with(['employee:id,first_name,last_name', 'uploadedBy:id,name'])
            ->latest()
            ->limit(15)
            ->get();
    }

    // ── Apoyo ─────────────────────────────────────────────────────────────────

    private function fillDefaults(Get $get, Set $set): void
    {
        $employee = $this->employee($get('employee_id'));
        $type = CertificateType::tryFrom((string) $get('type'));

        if (! $employee || ! $type) {
            return;
        }

        $today = CarbonImmutable::now($this->tenant()->plantTimezone());

        foreach (app(CertificateWordService::class)->defaults($type, $employee, $this->tenant(), $today) as $key => $value) {
            $set($key, $value);
        }
    }

    /**
     * Las opciones de salario con su cifra a la vista, para elegir sabiendo qué va a decir.
     *
     * @return array<string, string>
     */
    private function salaryOptions(?Employee $employee): array
    {
        $pesos = fn (float $value): string => '$ '.number_format($value, 0, ',', '.');
        $options = CertificateWordService::SALARY_MODES;

        if (! $employee) {
            return $options;
        }

        $basic = (float) $employee->base_salary;
        $average = app(CertificateWordService::class)->averageSalary($employee);

        $options['basic'] .= $basic > 0 ? ' · '.$pesos($basic) : ' · no tiene en la ficha';
        $options['average'] .= $average
            ? ' · '.$pesos($average['amount']).' ('.implode(', ', $average['runs']).')'
            : ' · todavía no tiene nóminas cerradas';

        return $options;
    }

    private function salaryOptionUnavailable(string $mode, ?Employee $employee): bool
    {
        return match ($mode) {
            'basic' => ! $employee || (float) $employee->base_salary <= 0,
            'average' => ! $employee || app(CertificateWordService::class)->averageSalary($employee) === null,
            default => false,
        };
    }

    /** @return array<string, string> todos, con los retirados al final */
    private function employeeOptions(): array
    {
        return Employee::query()
            ->forTenant($this->tenant()->id)
            ->orderByRaw('case when status = ? then 1 else 0 end', [EmploymentStatus::Retirado->value])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(fn (Employee $e): array => [
                $e->id => $e->fullName().($e->position ? ' · '.$e->position : '').($this->hasLeft($e) ? ' · retirado' : ''),
            ])
            ->all();
    }

    private function hasLeft(Employee $employee): bool
    {
        return $employee->status === EmploymentStatus::Retirado || $employee->termination_date !== null;
    }

    private function employee(?string $id): ?Employee
    {
        return $id ? Employee::query()->forTenant($this->tenant()->id)->whereKey($id)->first() : null;
    }

    private function tenant(): Tenant
    {
        /** @var Tenant */
        return Filament::getTenant();
    }
}
