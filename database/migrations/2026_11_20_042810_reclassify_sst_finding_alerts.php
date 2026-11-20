<?php

use App\Models\EquipmentIssueReport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las alertas que abrieron los hallazgos de SST y Ambiental al llegar a mantenimiento
 * pasan a su propia categoría, «SST y Ambiental», y a su propio menú en el Centro de
 * Alertas. Se reconocen por el cargo con que firma el reporte de novedad. Pedido
 * 2026-11-19. Idempotente.
 */
return new class extends Migration
{
    /** @var array<string, string> cargo del reporte → cómo se nombra el hallazgo */
    private array $positions = ['Seguridad y Salud en el Trabajo' => 'SST', 'Gestión Ambiental' => 'ambiental'];

    public function up(): void
    {
        foreach ($this->positions as $position => $adjective) {
            $reportIds = DB::table('equipment_issue_reports')->where('reporter_position', $position)->pluck('id');

            DB::table('alerts')
                ->where('entity_type', EquipmentIssueReport::class)
                ->whereIn('entity_id', $reportIds)
                ->where('category', 'maintenance')
                ->update([
                    'category' => 'safety',
                    'title' => DB::raw("replace(title, 'Reporte de novedad pendiente: ', 'Hallazgo {$adjective} pendiente en mantenimiento: ')"),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('alerts')->where('category', 'safety')->update(['category' => 'maintenance']);
    }
};
