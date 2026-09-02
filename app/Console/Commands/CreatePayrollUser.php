<?php

namespace App\Console\Commands;

use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Crea la cuenta de la persona de talento humano, o la de portería.
 *
 * Se hace por comando y no por el panel porque el rol de talento humano no se lo puede
 * dar el administrador del tenant a sí mismo: es justamente el rol al que no tiene
 * acceso. Alguien de plataforma lo crea la primera vez, y a partir de ahí talento humano
 * administra su propio módulo.
 *
 * La contraseña no se pide por argumento ni se escribe en el comando: se genera, se
 * muestra una vez y el usuario la cambia al entrar. Una contraseña en el historial de la
 * terminal es una contraseña filtrada.
 */
class CreatePayrollUser extends Command
{
    protected $signature = 'payroll:create-user
        {--tenant= : ID de la empresa. Si se omite, se pregunta}
        {--email= : Correo de la persona}
        {--name= : Nombre completo}
        {--role=talento-humano : talento-humano o porteria}
        {--seed-parameters : Carga además las vigencias iniciales de nómina para esa empresa}';

    protected $description = 'Crea el usuario de talento humano o de portería en una empresa';

    public function handle(PayrollParameterService $parameters): int
    {
        $role = $this->option('role');

        if (! in_array($role, ['talento-humano', 'porteria'], true)) {
            $this->error('El rol debe ser «talento-humano» o «porteria».');

            return self::FAILURE;
        }

        $tenant = $this->resolveTenant();

        if (! $tenant) {
            return self::FAILURE;
        }

        $name = $this->option('name') ?: text('Nombre completo', required: true);
        $email = $this->option('email') ?: text('Correo electrónico', required: true);

        if (User::query()->where('email', $email)->exists()) {
            $this->error("Ya existe un usuario con el correo {$email}.");

            return self::FAILURE;
        }

        // 24 caracteres aleatorios: se usa una sola vez, así que no hay razón para que
        // sea memorizable.
        $password = Str::password(24);

        $user = DB::transaction(function () use ($name, $email, $password, $tenant, $role): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
                'is_super_admin' => false,
                'email_verified_at' => now(),
            ]);

            $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

            setPermissionsTeamId($tenant->id);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $user->assignRole($role);

            return $user;
        });

        if ($this->option('seed-parameters') && $role === 'talento-humano') {
            $created = $parameters->seedDefaults($tenant->id, Carbon::parse('2026-01-01'), $user->id);
            $this->line("Vigencias de nómina cargadas: {$created}.");
        }

        $this->newLine();
        $this->info("Usuario creado en {$tenant->name}.");
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Nombre', $name],
                ['Correo', $email],
                ['Rol', $role],
                ['Contraseña temporal', $password],
            ],
        );
        $this->warn('Anote la contraseña ahora: no se vuelve a mostrar. Pídale que la cambie al entrar.');

        return self::SUCCESS;
    }

    private function resolveTenant(): ?Tenant
    {
        if ($id = $this->option('tenant')) {
            $tenant = Tenant::find($id);

            if (! $tenant) {
                $this->error("No existe la empresa {$id}.");

                return null;
            }

            return $tenant;
        }

        $tenants = Tenant::query()->orderBy('name')->pluck('name', 'id')->all();

        if ($tenants === []) {
            $this->error('No hay empresas registradas.');

            return null;
        }

        $id = select('¿En qué empresa?', $tenants);

        return Tenant::find($id);
    }
}
