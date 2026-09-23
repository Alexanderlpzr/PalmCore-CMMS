<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Domain\HumanResources\Support\EmployeeProfileOptions as Options;
use App\Filament\Resources\Employees\RelationManagers\DocumentsRelationManager;
use App\Models\Employee;
use App\Models\Plant;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * La pestaña «Información» de la ficha del trabajador.
 *
 * Los campos son los de la hoja «Items» del libro de requerimientos de El Pajuil, y la
 * disposición sigue la ficha en papel que talento humano ya usa: a la izquierda la
 * persona, a la derecha a quién llamar, y debajo lo laboral, las afiliaciones y la
 * dotación. Los documentos van en su propia pestaña
 * ({@see DocumentsRelationManager}).
 */
class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)
                ->columnSpanFull()
                ->schema([
                    self::personalSection(),

                    Group::make()
                        ->columnSpan(['default' => 3, 'lg' => 1])
                        ->schema([
                            Section::make('Contacto')
                                ->icon(Heroicon::OutlinedPhone)
                                ->schema([
                                    TextInput::make('phone')->label('Celular')->tel()->maxLength(30),
                                    TextInput::make('email')->label('Correo')->email()->maxLength(150),
                                ]),
                            Section::make('Contacto de emergencia')
                                ->icon(Heroicon::OutlinedLifebuoy)
                                ->description('A quién llamar si algo pasa en planta.')
                                ->schema([
                                    TextInput::make('emergency_contact_name')->label('Nombre')->maxLength(150),
                                    TextInput::make('emergency_contact_relationship')
                                        ->label('Parentesco')
                                        ->placeholder('Cónyuge, madre, hermano…')
                                        ->maxLength(60),
                                    TextInput::make('emergency_contact_phone')->label('Teléfono')->tel()->maxLength(30),
                                ]),
                        ]),
                ]),

            self::laborSection(),
            self::salarySection(),

            Section::make('Afiliaciones')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->description('Escriba la entidad o elija una de las sugeridas.')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    self::affiliation('eps', 'EPS'),
                    self::affiliation('pension_fund', 'Fondo de pensiones (AFP)'),
                    self::affiliation('arl', 'ARL'),
                    self::affiliation('severance_fund', 'Fondo de cesantías'),
                    self::affiliation('compensation_fund', 'Caja de compensación'),
                    TextInput::make('arl_risk_class')->label('Clase de riesgo ARL')->maxLength(5),
                ]),

            Section::make('Dotación')
                ->icon(Heroicon::OutlinedShoppingBag)
                ->description('Tallas para la entrega de dotación.')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    Select::make('shirt_size')->label('Camisa')->options(Options::garmentSizes())->native(false),
                    TextInput::make('pants_size')->label('Pantalón')->maxLength(10),
                    TextInput::make('boot_size')->label('Botas')->maxLength(10),
                    Select::make('jacket_size')->label('Rompevientos')->options(Options::garmentSizes())->native(false),
                ]),

            Textarea::make('notes')->label('Notas')->rows(2)->columnSpanFull(),
        ]);
    }

    private static function personalSection(): Section
    {
        return Section::make('Personal')
            ->icon(Heroicon::OutlinedUser)
            ->columns(2)
            ->columnSpan(['default' => 3, 'lg' => 2])
            ->schema([
                TextInput::make('first_name')->label('Nombres')->required()->maxLength(80),
                TextInput::make('last_name')->label('Apellidos')->required()->maxLength(80),
                Select::make('document_type')
                    ->label('Tipo de documento')
                    ->options(Options::DOCUMENT_TYPES)
                    ->default('CC')
                    ->required()
                    ->native(false),
                TextInput::make('document_number')
                    ->label('Número de documento')
                    ->required()
                    ->maxLength(30)
                    ->unique(ignoreRecord: true)
                    ->helperText('Es la llave del trabajador: la nómina y la importación cruzan por aquí.'),
                DatePicker::make('document_issue_date')->label('Fecha de expedición')->maxDate(now()),
                TextInput::make('document_issue_place')
                    ->label('Lugar de expedición')
                    ->placeholder('Maní - Casanare')
                    ->maxLength(120),
                DatePicker::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->maxDate(now())
                    ->live()
                    // La edad se calcula, no se guarda: en el Excel es una columna que
                    // envejece sola y ya marca 126 años en las filas sin fecha.
                    ->helperText(fn (?string $state): ?string => $state
                        ? Carbon::parse($state)->age.' años'
                        : null),
                Select::make('sex')->label('Sexo')->options(Options::SEXES)->native(false),
                Select::make('blood_type')->label('RH')->options(Options::bloodTypes())->native(false),
                TextInput::make('allergies')
                    ->label('Alergias')
                    ->placeholder('Ninguna')
                    ->maxLength(255),
                TextInput::make('city')
                    ->label('Municipio / vereda de residencia')
                    ->maxLength(120),
                Select::make('residence_zone')
                    ->label('Zona de residencia')
                    ->options(Options::RESIDENCE_ZONES)
                    ->native(false),
                TextInput::make('address')->label('Dirección')->maxLength(255)->columnSpanFull(),
                Toggle::make('has_children')
                    ->label('Tiene hijos')
                    ->live()
                    ->inline(false),
                TextInput::make('children_count')
                    ->label('Número de hijos')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(20)
                    ->visible(fn (Get $get): bool => (bool) $get('has_children')),
            ]);
    }

    private static function laborSection(): Section
    {
        return Section::make('Laboral')
            ->icon(Heroicon::OutlinedBriefcase)
            ->columns(3)
            ->columnSpanFull()
            ->schema([
                // Dos códigos, porque en El Pajuil son dos cosas distintas: el
                // consecutivo corto con el que se nombra a la gente en planta, y el
                // empresarial del libro, que lleva pegada la fecha de vinculación.
                TextInput::make('employee_code')
                    ->label('Código')
                    ->placeholder('001')
                    ->maxLength(10)
                    ->default(fn (): ?string => Filament::getTenant()
                        ? Employee::nextCode(Filament::getTenant()->id)
                        : null)
                    ->dehydrateStateUsing(fn (?string $state): ?string => Employee::formatCode($state))
                    ->helperText('Consecutivo de tres dígitos. Al crear se propone el siguiente libre.'),
                TextInput::make('company_code')
                    ->label('Código empresarial')
                    ->placeholder('O4092021')
                    ->maxLength(30),
                TextInput::make('position')->label('Cargo')->maxLength(120),
                Select::make('contract_type')
                    ->label('Tipo de contrato')
                    ->options(Options::CONTRACT_TYPES)
                    ->native(false),
                Select::make('area')
                    ->label('Área')
                    ->options(Options::AREAS)
                    ->native(false),
                Select::make('area_specific')
                    ->label('Área específica')
                    ->options(Options::SPECIFIC_AREAS)
                    ->native(false),
                Select::make('plant_id')
                    ->label('Planta')
                    ->options(fn (): array => Plant::orderBy('name')->pluck('name', 'id')->all())
                    ->native(false),
                DatePicker::make('hire_date')
                    ->label('Fecha de ingreso')
                    ->live()
                    ->helperText(fn (?string $state): ?string => $state
                        ? 'Antigüedad: '.Carbon::parse($state)->diffForHumans(now(), [
                            'syntax' => CarbonInterface::DIFF_ABSOLUTE,
                            'parts' => 2,
                        ])
                        : null),
                DatePicker::make('termination_date')->label('Fecha de retiro'),
                Select::make('status')
                    ->label('Estado')
                    ->options(EmploymentStatus::options())
                    ->default(EmploymentStatus::Activo->value)
                    ->required()
                    ->native(false)
                    ->helperText('Solo el activo puede marcar en portería. El retirado conserva su historia.'),
            ]);
    }

    private static function salarySection(): Section
    {
        return Section::make('Remuneración')
            ->icon(Heroicon::OutlinedBanknotes)
            ->description('Las bonificaciones constitutivas y no constitutivas se registran en la pestaña «Bonificaciones».')
            ->columnSpanFull()
            // El sueldo es la única información del sistema que ni el administrador
            // del tenant recibe por omisión. Al crear todavía no hay registro, y ahí
            // la pregunta es `viewAnySalary`: `viewSalary` exige un empleado y Gate
            // lo denegaría en silencio.
            ->visible(fn (?Employee $record): bool => $record
                ? (auth()->user()?->can('viewSalary', $record) ?? false)
                : (auth()->user()?->can('viewAnySalary', Employee::class) ?? false))
            ->columns(2)
            ->schema([
                TextInput::make('base_salary')
                    ->label('Salario básico mensual')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->prefix('$'),
                Select::make('salary_type')
                    ->label('Tipo de salario')
                    ->options(['ordinario' => 'Ordinario', 'integral' => 'Integral'])
                    ->default('ordinario')
                    ->required()
                    ->native(false)
                    ->helperText('El salario integral ya lleva incorporadas las horas extras y los recargos.'),

                Toggle::make('excluded_from_overtime')
                    ->label('No causa horas extras ni recargos')
                    ->helperText(
                        'Trabajador de dirección, confianza y manejo. En la nómina de agosto son 14 de 48: '
                        .'supervisores, coordinadores, jefes y el director. Si esto queda mal marcado, '
                        .'portería les genera horas extras que no se deben pagar.'
                    )
                    ->columnSpanFull(),

                Select::make('transport_allowance_override')
                    ->label('Aplica auxilio de transporte')
                    ->options([
                        '' => 'Según la regla (salario hasta el tope legal)',
                        '1' => 'Sí, siempre',
                        '0' => 'No, nunca',
                    ])
                    ->native(false)
                    ->helperText('Déjelo en la regla salvo que haya una razón. En el libro actual la excepción se hace borrando la fórmula, y queda un hueco que nadie sabe explicar.')
                    ->columnSpanFull(),
            ]);
    }

    private static function affiliation(string $field, string $label): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->datalist(Options::AFFILIATION_SUGGESTIONS[$field])
            ->maxLength(120);
    }
}
