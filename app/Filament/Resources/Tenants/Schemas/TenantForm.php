<?php

namespace App\Filament\Resources\Tenants\Schemas;

use App\Domain\Shared\Enums\SubscriptionStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TenantForm
{
    /**
     * Los planes, con el nombre que se le muestra a una persona. La tabla, la ficha y
     * el formulario leen de aquí: antes había dos copias, y la sección de Suscripciones
     * mostraba «starter» sin traducir mientras Empresas decía «Inicial».
     *
     * @var array<string, string>
     */
    public const PLANS = [
        'trial' => 'Prueba',
        'starter' => 'Inicial',
        'professional' => 'Profesional',
        'enterprise' => 'Empresarial',
    ];

    /**
     * `is_active` sale del estado: falso solo para una empresa suspendida. Es la misma
     * regla que aplican Suspender y Reactivar en TenantAccessService, ahora también al
     * guardar el formulario; antes eran dos campos sueltos que podían contradecirse.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withAccessFromStatus(array $data): array
    {
        $status = $data['subscription_status'] ?? null;
        $status = $status instanceof SubscriptionStatus ? $status : SubscriptionStatus::tryFrom((string) $status);

        if ($status !== null) {
            $data['is_active'] = $status !== SubscriptionStatus::Suspended;
        }

        return $data;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la empresa')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->label('Identificador en la dirección web')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->helperText('Va en la dirección del panel de la empresa: fronda.app/admin/el-pajuil. Sin espacios ni tildes.'),
                        TextInput::make('tax_id')
                            ->label('NIT')
                            ->maxLength(50),
                        TextInput::make('contact_email')
                            ->label('Correo de contacto')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('contact_phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(50),
                        TextInput::make('address')
                            ->label('Dirección')
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Se muestra en el encabezado de los documentos PDF generados.'),
                    ]),

                // El estado decide lo que la gente de la empresa puede hacer en su panel:
                // con Prueba o Activo trabaja con normalidad; con Solo lectura o Suspendido
                // entra a consultar, pero el Gate le niega crear, cambiar y borrar
                // (SubscriptionStatus::BLOCKED_ABILITIES). Vencido el plan, queda en solo
                // lectura sin que nadie lo cambie.
                Section::make('Plan y acceso')
                    ->description('Con Solo lectura o Suspendido, su gente entra a consultar pero no puede crear ni cambiar nada. Al pasar la fecha de vencimiento, queda en solo lectura sola.')
                    ->columns(3)
                    ->schema([
                        Select::make('subscription_plan')
                            ->label('Plan')
                            ->options(self::PLANS)
                            ->required()
                            ->native(false)
                            ->default('starter'),
                        Select::make('subscription_status')
                            ->label('Estado')
                            ->options(SubscriptionStatus::options())
                            ->required()
                            ->native(false)
                            ->default(SubscriptionStatus::Active),
                        DatePicker::make('subscription_expires_at')
                            ->label('Vence')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->nullable()
                            ->helperText('Vacío: sin vencimiento.'),
                    ]),

                Section::make('Región')
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(fn (string $operation): bool => $operation === 'edit')
                    ->schema([
                        // Tres letras (COL, ECU, HND), como la columna: es `char(3)`. El
                        // formulario pedía dos y no dejaba guardar ninguna empresa con «COL»,
                        // y un «CO» se habría guardado con un espacio de relleno.
                        TextInput::make('country_code')
                            ->label('País (código de 3 letras)')
                            ->maxLength(3)
                            ->default('COL')
                            ->placeholder('COL'),
                        // Hoy ningún código lee esta zona —la aplicación trabaja en la del
                        // servidor—, pero el dato tiene que ser verdadero para cuando se use:
                        // por defecto la de la planta, no UTC.
                        TextInput::make('timezone')
                            ->label('Zona horaria')
                            ->maxLength(100)
                            ->default('America/Bogota')
                            ->placeholder('America/Bogota'),
                        TextInput::make('locale')
                            ->label('Idioma y formato (código)')
                            ->maxLength(10)
                            ->default('es_CO')
                            ->placeholder('es_CO'),
                    ]),

                // Solo al crear: lo atiende CreateTenant::afterCreate(), no es parte del modelo.
                Section::make('Administrador inicial (opcional)')
                    ->description('Si escribes su correo, se crea su cuenta con el rol Administrador General y una contraseña temporal, que se muestra una sola vez y tendrá que cambiar al entrar. Si el correo ya existe, se le da acceso a esta empresa y conserva su contraseña.')
                    ->columns(2)
                    ->visibleOn('create')
                    ->schema([
                        TextInput::make('admin_name')
                            ->label('Nombre')
                            ->maxLength(255)
                            ->default('Administrador'),
                        TextInput::make('admin_email')
                            ->label('Correo')
                            ->email()
                            ->maxLength(255),
                    ]),
            ]);
    }
}
