<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\Plant;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    Select::make('document_type')
                        ->label('Tipo de documento')
                        ->options(['CC' => 'Cédula de ciudadanía', 'CE' => 'Cédula de extranjería', 'PA' => 'Pasaporte', 'PEP' => 'PEP'])
                        ->default('CC')
                        ->required()
                        ->native(false),
                    TextInput::make('document_number')
                        ->label('Número de documento')
                        ->required()
                        ->maxLength(30)
                        ->unique(ignoreRecord: true)
                        ->helperText('Es la llave del trabajador. El libro de Excel cruza por nombre y por eso un acento corregido deja a alguien sin horas.'),
                    TextInput::make('first_name')->label('Nombres')->required()->maxLength(80),
                    TextInput::make('last_name')->label('Apellidos')->required()->maxLength(80),
                ]),

            Section::make('Vinculación')
                ->columns(2)
                ->schema([
                    TextInput::make('position')->label('Cargo')->maxLength(120),
                    Select::make('plant_id')
                        ->label('Planta')
                        ->options(fn (): array => Plant::orderBy('name')->pluck('name', 'id')->all())
                        ->native(false),
                    DatePicker::make('hire_date')->label('Fecha de ingreso'),
                    DatePicker::make('termination_date')->label('Fecha de retiro'),
                    Select::make('status')
                        ->label('Estado')
                        ->options(EmploymentStatus::options())
                        ->default(EmploymentStatus::Activo->value)
                        ->required()
                        ->native(false)
                        ->helperText('Solo el activo puede marcar en portería. El retirado conserva su historia.'),
                ]),

            Section::make('Remuneración')
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
                        ->label('Auxilio de transporte')
                        ->options([
                            '' => 'Según la regla (salario hasta el tope legal)',
                            '1' => 'Sí, siempre',
                            '0' => 'No, nunca',
                        ])
                        ->native(false)
                        ->helperText('Déjelo en la regla salvo que haya una razón. En el libro actual la excepción se hace borrando la fórmula, y queda un hueco que nadie sabe explicar.')
                        ->columnSpanFull(),
                ]),

            Section::make('Seguridad social')
                ->columns(2)
                ->collapsed()
                ->schema([
                    TextInput::make('eps')->label('EPS')->maxLength(120),
                    TextInput::make('pension_fund')->label('Fondo de pensiones')->maxLength(120),
                    TextInput::make('severance_fund')->label('Fondo de cesantías')->maxLength(120),
                    TextInput::make('arl_risk_class')->label('Clase de riesgo ARL')->maxLength(5),
                ]),

            Textarea::make('notes')->label('Notas')->rows(2)->columnSpanFull(),
        ]);
    }
}
