<?php

namespace Database\Seeders;

use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Services\DowntimeService;
use App\Domain\Maintenance\Enums\MaintenanceRequestPriority;
use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Enums\MaintenanceRequestType;
use App\Domain\Maintenance\Enums\MaintenanceTimeFrequency;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\MaintenanceBudget;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRequest;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Un mes cerrado y otro en curso, para poder enseñar el sistema con datos que
 * cuentan una historia en vez de filas sueltas.
 *
 * El mes cerrado reproduce a propósito las cifras de referencia de la planta —HP
 * 452 h, HASEO 8 h, HMTTO 14 h, HOPER 10 h, FP 6.000 t— para que los indicadores
 * salgan en 94,59 % · 13,51 t/h · 95,13 %: los números que el ingeniero ya
 * calcula a mano y reconoce de inmediato. Que cuadren no es casualidad, es la
 * prueba de que el sistema mide lo mismo que su planilla.
 *
 * Nada se inventa por la puerta de atrás. Los gastos del presupuesto no se
 * escriben: salen de cerrar OT con costo, que es lo que hace
 * CreateBudgetExpenseFromWorkOrderListener en producción. Los paros van por
 * DowntimeService, que valida solapamientos. Las lecturas por su servicio, que
 * calcula el acumulado. Si el ejemplo cuadra, es porque el flujo real cuadra.
 *
 * Los paros del mes cerrado van encadenados en el tiempo, sin solaparse nunca:
 * la planta tiene un solo reloj y las horas perdidas son la UNIÓN de los paros,
 * no su suma. Dos paros simultáneos costarían menos horas que por separado y los
 * totales dejarían de dar las cifras de referencia.
 *
 * Es idempotente: se apoya en claves naturales (número de OT, código de
 * repuesto, fecha de calendario), así que re-ejecutarlo corrige en vez de
 * duplicar.
 */
class DemoActivitySeeder extends Seeder
{
    /** Equipos protagonistas, por código del inventario de la extractora. */
    private const CRITICAL_EQUIPMENT = [
        'A05EXT.04.01', // Digestor Vertical
        'A05EXT.05.01', // Prensa de Doble Tornillo
        'A06CLA.12.01', // Tricanter
        'A10SPG.13.02', // Caldera Inducido #1
        'A02STR.03.01', // Esterilizador Oblicuo Automático
        'A01REC.03.01', // Redler #1 Fruta de las Tolvas
        'A08KRS.11.01', // Molino Tipo Ripple Mill
        'A06CLA.34.04', // Centrífuga Alfa Laval
        'A04EBT.02.01', // Crusher para Racimos Vacíos
        'A05EXT.01.01', // Elevador de Fruto
    ];

    /**
     * Repuestos de una extractora, con su mínimo. Los tres últimos quedan por
     * debajo a propósito: un almacén donde nunca falta nada no enseña nada.
     *
     * @var list<array{code: string, name: string, criticality: string, abc: string, category: string, unit: string, cost: int, min: int, stock: int}>
     */
    private const SPARE_PARTS = [
        ['code' => 'RPT-001', 'name' => 'Rodamiento SKF 22320', 'criticality' => 'critical', 'abc' => 'A', 'category' => 'mechanical', 'unit' => 'unidad', 'cost' => 480000, 'min' => 4, 'stock' => 11],
        ['code' => 'RPT-002', 'name' => 'Banda transportadora 800 mm', 'criticality' => 'medium', 'abc' => 'B', 'category' => 'mechanical', 'unit' => 'm', 'cost' => 165000, 'min' => 10, 'stock' => 34],
        ['code' => 'RPT-003', 'name' => 'Retenedor de eje 120x150x12', 'criticality' => 'medium', 'abc' => 'C', 'category' => 'mechanical', 'unit' => 'unidad', 'cost' => 62000, 'min' => 8, 'stock' => 22],
        ['code' => 'RPT-004', 'name' => 'Cadena de arrastre paso 4"', 'criticality' => 'high', 'abc' => 'B', 'category' => 'mechanical', 'unit' => 'm', 'cost' => 210000, 'min' => 12, 'stock' => 28],
        ['code' => 'RPT-005', 'name' => 'Empaque de vapor 3"', 'criticality' => 'low', 'abc' => 'C', 'category' => 'mechanical', 'unit' => 'unidad', 'cost' => 38000, 'min' => 15, 'stock' => 40],
        ['code' => 'RPT-006', 'name' => 'Aceite hidráulico ISO 68 (caneca)', 'criticality' => 'high', 'abc' => 'B', 'category' => 'lubrication', 'unit' => 'caja', 'cost' => 720000, 'min' => 3, 'stock' => 7],
        ['code' => 'RPT-007', 'name' => 'Contactor trifásico 65 A', 'criticality' => 'high', 'abc' => 'B', 'category' => 'electrical', 'unit' => 'unidad', 'cost' => 340000, 'min' => 4, 'stock' => 9],
        ['code' => 'RPT-008', 'name' => 'Malla de tamiz 0,5 mm', 'criticality' => 'critical', 'abc' => 'A', 'category' => 'mechanical', 'unit' => 'unidad', 'cost' => 890000, 'min' => 2, 'stock' => 5],
        ['code' => 'RPT-009', 'name' => 'Cuchilla de digestor', 'criticality' => 'high', 'abc' => 'B', 'category' => 'mechanical', 'unit' => 'unidad', 'cost' => 275000, 'min' => 6, 'stock' => 14],
        ['code' => 'RPT-010', 'name' => 'Barra de rotor Ripple Mill', 'criticality' => 'medium', 'abc' => 'B', 'category' => 'mechanical', 'unit' => 'unidad', 'cost' => 195000, 'min' => 10, 'stock' => 3],
        ['code' => 'RPT-011', 'name' => 'Sello mecánico 45 mm', 'criticality' => 'critical', 'abc' => 'A', 'category' => 'mechanical', 'unit' => 'unidad', 'cost' => 430000, 'min' => 5, 'stock' => 2],
        ['code' => 'RPT-012', 'name' => 'Correa en V C-180', 'criticality' => 'low', 'abc' => 'C', 'category' => 'mechanical', 'unit' => 'unidad', 'cost' => 88000, 'min' => 12, 'stock' => 6],
    ];

    private Plant $plant;

    private User $actor;

    /** @var array<string, Equipment> */
    private array $equipment = [];

    private Carbon $closedMonth;

    private Carbon $currentMonth;

    public function run(Tenant $tenant): void
    {
        $this->plant = Plant::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'PLT-01')
            ->firstOrFail();

        $this->actor = User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenant->id))
            ->orderBy('created_at')
            ->firstOr(fn () => throw new RuntimeException(
                'El tenant no tiene ningún usuario: el ejemplo necesita un actor que firme las OT y los paros.'
            ));

        $this->equipment = Equipment::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('code', self::CRITICAL_EQUIPMENT)
            ->get()
            ->keyBy('code')
            ->all();

        if (count($this->equipment) < count(self::CRITICAL_EQUIPMENT)) {
            throw new RuntimeException(
                'Falta inventario: corre ProvisionTenantBaseStructure antes, el ejemplo se monta sobre los equipos reales.'
            );
        }

        // Relativo a hoy, no fechas fijas: el ejemplo debe seguir teniendo un mes
        // cerrado y otro en curso el día que se vuelva a correr.
        $this->closedMonth = Carbon::now()->startOfMonth()->subMonthNoOverflow();
        $this->currentMonth = Carbon::now()->startOfMonth();

        $this->seedProductionCalendar($tenant);
        $this->seedWarehouse($tenant);
        $this->seedMaintenancePlans($tenant);
        $this->seedClosedMonthStoppages($tenant);
        $this->seedCurrentMonthStoppages($tenant);
        $this->seedBudget($tenant);
        $this->seedClosedWorkOrders($tenant);
        $this->seedOpenWorkOrders($tenant);
        $this->seedMaintenanceRequests($tenant);
        $this->seedMeterReadings();

        // Congela el mes cerrado: es lo que da la barra del histórico.
        app(PlantKpiService::class)->snapshotMonth(
            $this->plant,
            (int) $this->closedMonth->year,
            (int) $this->closedMonth->month,
        );
    }

    /**
     * HP y FP. El mes cerrado va a las cifras de referencia exactas: 20 jornadas
     * de 22,6 h son 452 h, y 300 t por jornada son 6.000 t.
     */
    private function seedProductionCalendar(Tenant $tenant): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->calendarDay($tenant, $this->closedMonth->copy()->addDays($i), 22.6, 300);
        }

        // El mes en curso sólo hasta hoy: programar días que no han pasado sería
        // inventar producción futura.
        $daysSoFar = (int) Carbon::now()->day;

        for ($i = 0; $i < $daysSoFar; $i++) {
            $day = $this->currentMonth->copy()->addDays($i);

            if ($day->isSunday()) {
                $this->calendarDay($tenant, $day, 0, 0);

                continue;
            }

            $this->calendarDay($tenant, $day, 22.0, 288);
        }
    }

    private function calendarDay(Tenant $tenant, Carbon $date, float $hours, float $tons): void
    {
        ProductionCalendarDay::withoutGlobalScopes()->updateOrCreate(
            ['plant_id' => $this->plant->id, 'calendar_date' => $date->toDateString()],
            ['tenant_id' => $tenant->id, 'programmed_hours' => $hours, 'processed_tons' => $tons],
        );
    }

    private function seedWarehouse(Tenant $tenant): void
    {
        $warehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'ALM-01'],
            [
                'name' => 'Almacén de Mantenimiento',
                'location' => 'Taller central',
                'is_active' => true,
                'created_by' => $this->actor->id,
            ],
        );

        foreach (self::SPARE_PARTS as $item) {
            $part = SparePart::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $item['code']],
                [
                    'name' => $item['name'],
                    'category_type' => $item['category'],
                    'criticality' => $item['criticality'],
                    'abc_classification' => $item['abc'],
                    'unit' => $item['unit'],
                    'unit_cost' => $item['cost'],
                    'minimum_stock' => $item['min'],
                    'reorder_point' => $item['min'],
                    'is_active' => true,
                    'created_by' => $this->actor->id,
                ],
            );

            WarehouseSparePart::withoutGlobalScopes()->updateOrCreate(
                ['warehouse_id' => $warehouse->id, 'spare_part_id' => $part->id],
                [
                    'tenant_id' => $tenant->id,
                    'current_stock' => $item['stock'],
                    'average_unit_cost' => $item['cost'],
                ],
            );
        }
    }

    /** Los preventivos que llenan el «por realizar». */
    private function seedMaintenancePlans(Tenant $tenant): void
    {
        $plans = [
            ['A05EXT.04.01', 'Cambio de cuchillas del digestor', MaintenanceTimeFrequency::Monthly, 240],
            ['A05EXT.04.01', 'Inspección de camisa de vapor', MaintenanceTimeFrequency::Quarterly, 120],
            ['A05EXT.05.01', 'Revisión de tornillos de prensa', MaintenanceTimeFrequency::Monthly, 300],
            ['A05EXT.05.01', 'Cambio de aceite unidad hidráulica', MaintenanceTimeFrequency::Quarterly, 180],
            ['A06CLA.12.01', 'Lubricación de caja de engranajes', MaintenanceTimeFrequency::Weekly, 60],
            ['A06CLA.12.01', 'Balanceo de tambor giratorio', MaintenanceTimeFrequency::Semiannual, 480],
            ['A10SPG.13.02', 'Limpieza de rodete del inducido', MaintenanceTimeFrequency::Monthly, 180],
            ['A10SPG.13.02', 'Revisión de rodamientos y chumaceras', MaintenanceTimeFrequency::Quarterly, 240],
            ['A02STR.03.01', 'Inspección de sellos de esterilizador', MaintenanceTimeFrequency::Monthly, 150],
            ['A01REC.03.01', 'Tensado de cadena del redler', MaintenanceTimeFrequency::Weekly, 45],
            ['A01REC.03.01', 'Cambio de cadena de arrastre', MaintenanceTimeFrequency::Annual, 600],
            ['A08KRS.11.01', 'Cambio de barras del rotor', MaintenanceTimeFrequency::Monthly, 120],
            ['A06CLA.34.04', 'Limpieza de pila de discos', MaintenanceTimeFrequency::Monthly, 210],
            ['A04EBT.02.01', 'Revisión de ejes rotativos', MaintenanceTimeFrequency::Quarterly, 180],
            ['A05EXT.01.01', 'Inspección de sistema de tensión', MaintenanceTimeFrequency::Monthly, 90],
        ];

        foreach ($plans as $index => [$code, $name, $frequency, $minutes]) {
            MaintenancePlan::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'plan_number' => sprintf('PM-%03d', $index + 1)],
                [
                    'equipment_id' => $this->equipment[$code]->id,
                    'name' => $name,
                    'trigger_source' => MaintenanceTriggerSource::Calendar->value,
                    'time_frequency' => $frequency->value,
                    'estimated_duration_minutes' => $minutes,
                    'responsible_user_id' => $this->actor->id,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Los paros del mes cerrado, cuadrados contra las cifras de referencia:
     * 8 h de aseo, 14 h de mantenimiento correctivo y 10 h de otras causas.
     *
     * Se reparten en muchos eventos cortos —que es como ocurre en planta— pero
     * encadenados para que la unión sea la suma y los totales den exactos.
     */
    private function seedClosedMonthStoppages(Tenant $tenant): void
    {
        $cursor = $this->closedMonth->copy()->setTime(6, 0);

        // HASEO — 4 paradas de aseo y preventivo, 2 h cada una.
        foreach (['A02STR.03.01', 'A05EXT.04.01', 'A10SPG.13.02', 'A06CLA.12.01'] as $code) {
            $cursor = $this->stoppage($tenant, $cursor, $code, StoppageCategory::Planned, 120);
        }

        // HMTTO — 14 fallas correctivas de 1 h, alternando mecánica y eléctrica.
        $mechanical = ['A05EXT.05.01', 'A01REC.03.01', 'A08KRS.11.01', 'A04EBT.02.01', 'A05EXT.01.01', 'A06CLA.34.04', 'A05EXT.04.01'];

        foreach ($mechanical as $code) {
            $cursor = $this->stoppage($tenant, $cursor, $code, StoppageCategory::Mechanical, 60);
            $cursor = $this->stoppage($tenant, $cursor, $code, StoppageCategory::Electrical, 60);
        }

        // HOPER — 20 paradas de media hora que mantenimiento no controla.
        $operational = [
            StoppageCategory::RawMaterial,
            StoppageCategory::Process,
            StoppageCategory::Utilities,
            StoppageCategory::External,
        ];

        for ($i = 0; $i < 20; $i++) {
            $cursor = $this->stoppage(
                $tenant,
                $cursor,
                self::CRITICAL_EQUIPMENT[$i % count(self::CRITICAL_EQUIPMENT)],
                $operational[$i % count($operational)],
                30,
            );
        }
    }

    /** El mes en curso: menos paros, sin cifra objetivo — todavía está pasando. */
    private function seedCurrentMonthStoppages(Tenant $tenant): void
    {
        $cursor = $this->currentMonth->copy()->setTime(7, 0);

        $script = [
            ['A05EXT.05.01', StoppageCategory::Mechanical, 90],
            ['A10SPG.13.02', StoppageCategory::Electrical, 45],
            ['A01REC.03.01', StoppageCategory::RawMaterial, 60],
            ['A06CLA.12.01', StoppageCategory::Planned, 120],
            ['A08KRS.11.01', StoppageCategory::Mechanical, 75],
            ['A02STR.03.01', StoppageCategory::Process, 30],
            ['A05EXT.04.01', StoppageCategory::Instrumentation, 40],
            ['A04EBT.02.01', StoppageCategory::Utilities, 25],
        ];

        foreach ($script as [$code, $category, $minutes]) {
            $cursor = $this->stoppage($tenant, $cursor, $code, $category, $minutes);
        }
    }

    /**
     * Registra un paro a continuación del anterior y devuelve el nuevo cursor,
     * separado por unas horas para que la planilla no parezca un bloque continuo.
     */
    private function stoppage(
        Tenant $tenant,
        Carbon $startedAt,
        string $equipmentCode,
        StoppageCategory $category,
        int $minutes,
    ): Carbon {
        $endedAt = $startedAt->copy()->addMinutes($minutes);

        $exists = EquipmentDowntimeEvent::withoutGlobalScopes()
            ->where('plant_id', $this->plant->id)
            ->where('started_at', $startedAt)
            ->exists();

        if (! $exists) {
            app(DowntimeService::class)->register([
                'tenant_id' => $tenant->id,
                'plant_id' => $this->plant->id,
                'equipment_id' => $this->equipment[$equipmentCode]->id,
                'stoppage_category' => $category,
                'affects_production' => true,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
            ], $this->actor);
        }

        return $endedAt->copy()->addHours(7);
    }

    /** PPT — el presupuesto mensual contra el que se miden los gastos. */
    private function seedBudget(Tenant $tenant): void
    {
        foreach ([$this->closedMonth, $this->currentMonth] as $month) {
            MaintenanceBudget::withoutGlobalScopes()->updateOrCreate(
                [
                    'plant_id' => $this->plant->id,
                    'year' => (int) $month->year,
                    'month' => (int) $month->month,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'amount' => 48_000_000,
                    'notes' => 'Presupuesto mensual de mantenimiento',
                    'created_by' => $this->actor->id,
                ],
            );
        }
    }

    /**
     * Las OT del mes cerrado. Se crean y se cierran con costo, que es lo que
     * hace aparecer el gasto en el presupuesto — desglosado por rubro, sin que
     * nadie recapture cifras.
     */
    private function seedClosedWorkOrders(Tenant $tenant): void
    {
        $script = [
            ['A05EXT.04.01', WorkOrderType::Preventive, 'Cambio de cuchillas del digestor', 1_850_000, 2_400_000],
            ['A05EXT.05.01', WorkOrderType::Corrective, 'Fuga en unidad hidráulica de prensa', 980_000, 1_650_000],
            ['A06CLA.12.01', WorkOrderType::Preventive, 'Lubricación de caja de engranajes', 420_000, 310_000],
            ['A10SPG.13.02', WorkOrderType::Corrective, 'Vibración excesiva en inducido', 1_240_000, 890_000],
            ['A02STR.03.01', WorkOrderType::Preventive, 'Inspección de sellos de esterilizador', 650_000, 480_000],
            ['A01REC.03.01', WorkOrderType::Corrective, 'Cadena del redler destensada', 380_000, 620_000],
            ['A08KRS.11.01', WorkOrderType::Corrective, 'Desgaste de barras del rotor', 720_000, 1_170_000],
            ['A06CLA.34.04', WorkOrderType::Preventive, 'Limpieza de pila de discos', 890_000, 260_000],
            ['A04EBT.02.01', WorkOrderType::Corrective, 'Rodamiento de eje rotativo con juego', 540_000, 960_000],
            ['A05EXT.01.01', WorkOrderType::Preventive, 'Inspección de sistema de tensión', 310_000, 180_000],
            ['A05EXT.05.01', WorkOrderType::Corrective, 'Cambio de sello mecánico', 460_000, 860_000],
            ['A10SPG.13.02', WorkOrderType::Preventive, 'Revisión de rodamientos y chumaceras', 780_000, 540_000],
            ['A06CLA.12.01', WorkOrderType::Corrective, 'Ruido en tornillo sinfín del tricanter', 1_120_000, 1_380_000],
            ['A01REC.03.01', WorkOrderType::Preventive, 'Tensado de cadena del redler', 210_000, 90_000],
            ['A05EXT.04.01', WorkOrderType::Corrective, 'Sobrecalentamiento de motor del digestor', 1_460_000, 2_100_000],
            ['A02STR.03.01', WorkOrderType::Corrective, 'Válvula de vapor no cierra', 690_000, 740_000],
            ['A08KRS.11.01', WorkOrderType::Preventive, 'Cambio de barras del rotor', 580_000, 780_000],
            ['A04EBT.02.01', WorkOrderType::Preventive, 'Revisión de ejes rotativos', 340_000, 220_000],
            ['A06CLA.34.04', WorkOrderType::Corrective, 'Fuga en sistema hidráulico de centrífuga', 830_000, 1_040_000],
            ['A05EXT.01.01', WorkOrderType::Corrective, 'Elevador patina bajo carga', 470_000, 690_000],
            ['A10SPG.13.02', WorkOrderType::Corrective, 'Contactor quemado en tablero', 290_000, 340_000],
            ['A06CLA.12.01', WorkOrderType::Preventive, 'Balanceo de tambor giratorio', 1_650_000, 1_280_000],
        ];

        foreach ($script as $index => [$code, $type, $title, $labor, $parts]) {
            $closedAt = $this->closedMonth->copy()->addDays(($index % 20) + 1)->setTime(16, 0);

            $workOrder = $this->workOrder($tenant, $code, $type, $title, $closedAt->copy()->subHours(6));

            if ($workOrder === null || $workOrder->status === WorkOrderStatus::Closed) {
                continue;
            }

            $workOrder->update([
                'actual_cost_labor' => $labor,
                'actual_cost_parts' => $parts,
                'actual_cost_consumables' => (int) round($parts * 0.08),
                'actual_cost_total' => $labor + $parts + (int) round($parts * 0.08),
                'work_performed' => 'Intervención ejecutada y equipo entregado a producción.',
                'actual_start_at' => $closedAt->copy()->subHours(6),
                'actual_end_at' => $closedAt,
            ]);

            // El cierre dispara el listener que vuelca el costo al presupuesto.
            app(WorkOrderService::class)->transition(
                $workOrder->refresh(),
                WorkOrderStatus::Closed,
                $this->actor,
                ['closed_at' => $closedAt],
            );
        }
    }

    /** El trabajo que está sobre la mesa hoy: abiertas, sin cerrar. */
    private function seedOpenWorkOrders(Tenant $tenant): void
    {
        $script = [
            ['A05EXT.05.01', WorkOrderType::Corrective, 'Prensa con ruido en caja reductora', WorkOrderPriority::P1Critical],
            ['A10SPG.13.02', WorkOrderType::Corrective, 'Inducido con temperatura alta en chumacera', WorkOrderPriority::P1Critical],
            ['A06CLA.12.01', WorkOrderType::Preventive, 'Lubricación de caja de engranajes', WorkOrderPriority::P3Medium],
            ['A05EXT.04.01', WorkOrderType::Preventive, 'Cambio de cuchillas del digestor', WorkOrderPriority::P2High],
            ['A02STR.03.01', WorkOrderType::Corrective, 'Fuga de vapor en brida de esterilizador', WorkOrderPriority::P2High],
            ['A01REC.03.01', WorkOrderType::Preventive, 'Tensado de cadena del redler', WorkOrderPriority::P3Medium],
            ['A08KRS.11.01', WorkOrderType::Corrective, 'Ripple Mill con rendimiento bajo', WorkOrderPriority::P2High],
            ['A04EBT.02.01', WorkOrderType::Preventive, 'Revisión de ejes rotativos', WorkOrderPriority::P3Medium],
            ['A06CLA.34.04', WorkOrderType::Corrective, 'Centrífuga vibra al arrancar', WorkOrderPriority::P1Critical],
            ['A05EXT.01.01', WorkOrderType::Preventive, 'Inspección de sistema de tensión', WorkOrderPriority::P4Low],
            ['A10SPG.13.02', WorkOrderType::Preventive, 'Limpieza de rodete del inducido', WorkOrderPriority::P3Medium],
            ['A05EXT.05.01', WorkOrderType::Improvement, 'Instalar guarda de seguridad en acople', WorkOrderPriority::P4Low],
            ['A06CLA.12.01', WorkOrderType::Corrective, 'Sensor de nivel del tanque pulmón sin señal', WorkOrderPriority::P2High],
        ];

        foreach ($script as $index => [$code, $type, $title, $priority]) {
            $this->workOrder(
                $tenant,
                $code,
                $type,
                $title,
                $this->currentMonth->copy()->addDays($index % max(1, (int) Carbon::now()->day))->setTime(8, 0),
                $priority,
            );
        }
    }

    /**
     * Crea una OT si su título no existe ya en el mes, para que re-ejecutar el
     * seeder no llene la planta de duplicados.
     */
    private function workOrder(
        Tenant $tenant,
        string $equipmentCode,
        WorkOrderType $type,
        string $title,
        Carbon $createdAt,
        WorkOrderPriority $priority = WorkOrderPriority::P3Medium,
    ): ?WorkOrder {
        $existing = WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('title', $title)
            ->whereBetween('created_at', [$createdAt->copy()->startOfMonth(), $createdAt->copy()->endOfMonth()])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $equipment = $this->equipment[$equipmentCode];

        $workOrder = app(WorkOrderService::class)->create([
            'tenant_id' => $tenant->id,
            'equipment_id' => $equipment->id,
            'plant_id' => $this->plant->id,
            'area_id' => $equipment->area_id,
            'work_order_type' => $type->value,
            'priority' => $priority->value,
            'title' => $title,
            'description' => $title.' — registrado por el supervisor de turno.',
            'executed_by' => 'Cuadrilla de mantenimiento',
            'currency_code' => 'COP',
        ], $this->actor);

        $workOrder->forceFill(['created_at' => $createdAt])->save();

        return $workOrder->refresh();
    }

    /** Cómo entra el trabajo: lo que el operario reporta desde planta. */
    private function seedMaintenanceRequests(Tenant $tenant): void
    {
        $script = [
            ['A05EXT.05.01', 'Prensa botando aceite por el pantalón', MaintenanceRequestPriority::P2High, MaintenanceRequestStatus::Submitted],
            ['A10SPG.13.02', 'Inducido suena distinto desde el turno de la noche', MaintenanceRequestPriority::P2High, MaintenanceRequestStatus::Submitted],
            ['A01REC.03.01', 'Redler se traba con fruta húmeda', MaintenanceRequestPriority::P3Medium, MaintenanceRequestStatus::Submitted],
            ['A06CLA.34.04', 'Centrífuga tarda en llegar a velocidad', MaintenanceRequestPriority::P3Medium, MaintenanceRequestStatus::UnderReview],
            ['A08KRS.11.01', 'Sale mucha almendra partida del molino', MaintenanceRequestPriority::P3Medium, MaintenanceRequestStatus::UnderReview],
            ['A02STR.03.01', 'Puerta de esterilizador cierra con dificultad', MaintenanceRequestPriority::P4Low, MaintenanceRequestStatus::Approved],
            ['A04EBT.02.01', 'Crusher deja racimos sin triturar', MaintenanceRequestPriority::P3Medium, MaintenanceRequestStatus::Approved],
            ['A05EXT.04.01', 'Digestor con vapor insuficiente', MaintenanceRequestPriority::P2High, MaintenanceRequestStatus::Rejected],
        ];

        foreach ($script as $index => [$code, $title, $priority, $status]) {
            MaintenanceRequest::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'request_number' => sprintf('SOL-%04d', $index + 1)],
                [
                    'equipment_id' => $this->equipment[$code]->id,
                    'request_type' => MaintenanceRequestType::Corrective->value,
                    'priority' => $priority->value,
                    'status' => $status->value,
                    'title' => $title,
                    'description' => $title.' — reportado por el operario del área.',
                    'created_by' => $this->actor->id,
                    'submitted_at' => $this->currentMonth->copy()->addDays($index),
                    'rejection_reason' => $status === MaintenanceRequestStatus::Rejected
                        ? 'La presión de vapor depende de la caldera, no del digestor. Se traslada a Cogeneración.'
                        : null,
                ],
            );
        }
    }

    /** Horómetros: la ronda diaria que alimenta los preventivos por horas. */
    private function seedMeterReadings(): void
    {
        $service = app(EquipmentMeterReadingService::class);
        $days = (int) Carbon::now()->day;

        foreach (['A05EXT.04.01', 'A05EXT.05.01', 'A06CLA.12.01', 'A10SPG.13.02', 'A02STR.03.01'] as $offset => $code) {
            $equipment = $this->equipment[$code];
            $base = 12_000 + ($offset * 1_500);

            for ($day = 0; $day < $days; $day++) {
                $recordedAt = $this->currentMonth->copy()->addDays($day)->setTime(6, 0);

                if ($equipment->meterReadings()->whereDate('recorded_at', $recordedAt->toDateString())->exists()) {
                    continue;
                }

                $service->record(
                    $equipment,
                    $base + ($day * 21.5),
                    $this->actor,
                    recordedAt: $recordedAt,
                );
            }
        }
    }
}
