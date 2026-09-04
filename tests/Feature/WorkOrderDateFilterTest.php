<?php

use App\Filament\Resources\Maintenance\WorkOrder\Pages\ListWorkOrders;
use App\Models\Equipment;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
 * Los atajos de fecha en Órdenes de Trabajo.
 *
 * Filtran por la **fecha planificada** y no por la de creación ni la de cierre. La tabla
 * muestra tres fechas y cada una responde una pregunta distinta; esta es la del que
 * planifica —qué hay por delante— y es la primera que se ve.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);

    $this->admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

function otPlanificadaEl(string $cuando): WorkOrder
{
    return WorkOrder::factory()->create([
        'tenant_id' => test()->tenant->id,
        'equipment_id' => test()->equipment->id,
        'planned_start_at' => Carbon::parse($cuando),
    ]);
}

it('«este mes» deja fuera la OT del mes pasado', function (): void {
    Carbon::setTestNow('2026-08-14');

    $esteMes = otPlanificadaEl('2026-08-05 08:00');
    $mesPasado = otPlanificadaEl('2026-07-20 08:00');

    Livewire::test(ListWorkOrders::class)
        ->filterTable('rango_de_fechas', ['atajo' => 'este_mes'])
        ->assertCanSeeTableRecords([$esteMes])
        ->assertCanNotSeeTableRecords([$mesPasado]);

    Carbon::setTestNow();
});

it('«mes pasado» sigue siendo el mes entero un 31 de mayo', function (): void {
    // Un 31 de mayo destapa el desbordamiento de Carbon —abril no tiene 31— y un 31 de
    // agosto no, porque julio sí lo tiene. La fecha está elegida, no puesta al azar.
    Carbon::setTestNow('2026-05-31');

    $primeroDeAbril = otPlanificadaEl('2026-04-01 08:00');
    $ultimoDeAbril = otPlanificadaEl('2026-04-30 22:00');
    $mayo = otPlanificadaEl('2026-05-02 08:00');

    Livewire::test(ListWorkOrders::class)
        ->filterTable('rango_de_fechas', ['atajo' => 'mes_pasado'])
        ->assertCanSeeTableRecords([$primeroDeAbril, $ultimoDeAbril])
        ->assertCanNotSeeTableRecords([$mayo]);

    Carbon::setTestNow();
});

it('filtra por la fecha planificada, no por la de creación', function (): void {
    // La distinción que se decidió: una OT creada hace meses pero programada para este
    // mes entra; una creada hoy para el mes que viene, no. Si el filtro mirara
    // `created_at` —como ordena la tabla por defecto— el resultado sería el contrario.
    Carbon::setTestNow('2026-08-14');

    $viejaParaEsteMes = otPlanificadaEl('2026-08-20 08:00');
    $viejaParaEsteMes->forceFill(['created_at' => Carbon::parse('2026-03-01')])->saveQuietly();

    $nuevaParaOtroMes = otPlanificadaEl('2026-09-03 08:00');

    Livewire::test(ListWorkOrders::class)
        ->filterTable('rango_de_fechas', ['atajo' => 'este_mes'])
        ->assertCanSeeTableRecords([$viejaParaEsteMes])
        ->assertCanNotSeeTableRecords([$nuevaParaOtroMes]);

    Carbon::setTestNow();
});

it('recuerda el filtro al volver a la pantalla', function (): void {
    Carbon::setTestNow('2026-08-14');

    $esteMes = otPlanificadaEl('2026-08-05 08:00');
    $mesPasado = otPlanificadaEl('2026-07-20 08:00');

    Livewire::test(ListWorkOrders::class)
        ->filterTable('rango_de_fechas', ['atajo' => 'este_mes']);

    Livewire::test(ListWorkOrders::class)
        ->assertCanSeeTableRecords([$esteMes])
        ->assertCanNotSeeTableRecords([$mesPasado]);

    Carbon::setTestNow();
});

it('no filtra nada cuando no se ha elegido período', function (): void {
    // Una OT sin fecha planificada no puede desaparecer solo porque exista el filtro:
    // sin período elegido, la tabla las muestra todas.
    $conFecha = otPlanificadaEl('2026-08-05 08:00');

    $sinFecha = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'planned_start_at' => null,
    ]);

    Livewire::test(ListWorkOrders::class)
        ->assertCanSeeTableRecords([$conFecha, $sinFecha]);
});
