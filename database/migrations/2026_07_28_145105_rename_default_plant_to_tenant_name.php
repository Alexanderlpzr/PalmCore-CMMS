<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La planta que `ProvisionTenantBaseStructure` sembró al crear cada tenant se
     * llamaba «Planta Principal». Ahora nace con el nombre del tenant mismo (ver
     * esa clase); esto pone al día las plantas que ya se crearon con el nombre
     * viejo, para cada tenant que aún tenga una planta llamada así.
     */
    public function up(): void
    {
        $tenants = DB::table('tenants')->select('id', 'name')->get();

        foreach ($tenants as $tenant) {
            DB::table('plants')
                ->where('tenant_id', $tenant->id)
                ->where('name', 'Planta Principal')
                ->update(['name' => $tenant->name]);
        }
    }

    /**
     * Backfill de datos: no se revierte (no se guarda el nombre previo de cada planta).
     */
    public function down(): void
    {
        //
    }
};
