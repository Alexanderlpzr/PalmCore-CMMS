<?php

namespace App\Console\Commands;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Domain\HumanResources\Services\PresumedWorkdayService;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Enciende la jornada presunta de los administrativos y llena hacia atrás los días que
 * quedaron sin marcas, desde la primera marca de portería hasta ayer. De ahí en adelante
 * la corrida de cada noche llena el día de quien no marcó.
 *
 * Sin `--apply` solo muestra a quién y cuántos días: lo hace de verdad y deshace todo al
 * final.
 */
#[Signature('attendance:presume-workdays
    {--tenant= : Slug de la empresa}
    {--from= : Desde qué día rige (AAAA-MM-DD); por omisión, el de la primera marca en portería}
    {--apply : Prende el parámetro y escribe las marcas; sin esto solo muestra el plan}')]
#[Description('Presume la jornada legal (6 a. m. a 2 p. m.) de los administrativos que no marcan en portería')]
class PresumeAdministrativeWorkdays extends Command
{
    public function handle(PayrollParameterService $parameters, PresumedWorkdayService $presumed): int
    {
        $tenant = Tenant::withoutGlobalScopes()->where('slug', (string) $this->option('tenant'))->first();

        if (! $tenant) {
            $this->error('No encontré esa empresa. Pásela con --tenant=<slug>.');

            return self::FAILURE;
        }

        CurrentTenant::set($tenant);
        $timezone = $tenant->plantTimezone();
        $from = $this->option('from')
            ? CarbonImmutable::parse((string) $this->option('from'), $timezone)
            : $this->firstGateMark($tenant);

        if (! $from) {
            $this->error('Esa empresa todavía no tiene marcas en portería. Pase la fecha con --from=AAAA-MM-DD.');

            return self::FAILURE;
        }

        $yesterday = CarbonImmutable::now($timezone)->subDay()->startOfDay();

        DB::beginTransaction();

        try {
            $parameters->setValue(
                PayrollParameter::PresumeAdministrativeWorkday,
                1,
                $from,
                $tenant->id,
                notes: 'Administrativo sin marcas: jornada de 6 a. m. a 2 p. m. con una hora de almuerzo.',
            );

            $filled = $presumed->fill($tenant, $from, $yesterday);
        } catch (\Throwable $e) {
            DB::rollBack();
            $parameters->forget();

            throw $e;
        }

        if ($this->option('apply')) {
            DB::commit();
        } else {
            DB::rollBack();
            $parameters->forget();
        }

        $administratives = Employee::query()->forTenant($tenant->id)->active()->where('area', PresumedWorkdayService::AREA)->count();

        $this->info(sprintf(
            '%s — jornada presunta desde el %s hasta el %s%s',
            $tenant->name,
            $from->format('d/m/Y'),
            $yesterday->format('d/m/Y'),
            $this->option('apply') ? '' : ' (plan: no se escribió nada; use --apply)',
        ));
        $this->table(
            ['Trabajador', 'Días presuntos', 'Primero', 'Último'],
            $filled->map(fn (array $row): array => [
                $row['employee']->fullName(),
                count($row['dates']),
                CarbonImmutable::parse($row['dates'][0])->format('d/m/Y'),
                CarbonImmutable::parse(end($row['dates']))->format('d/m/Y'),
            ])->all(),
        );
        $this->line(sprintf(
            'Administrativos activos: %d · con días presuntos: %d · días en total: %d',
            $administratives,
            $filled->count(),
            $filled->sum(fn (array $row): int => count($row['dates'])),
        ));

        return self::SUCCESS;
    }

    /** El día de la primera marca de carné en portería: desde ahí existe el registro. */
    private function firstGateMark(Tenant $tenant): ?CarbonImmutable
    {
        $first = AttendanceScan::query()
            ->forTenant($tenant->id)
            ->where('source', 'qr')
            ->min('scanned_at');

        return $first ? CarbonImmutable::parse($first)->setTimezone($tenant->plantTimezone())->startOfDay() : null;
    }
}
