<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * La razón social y la firma de talento humano del formato de horas extras (TH-NOM-F-001)
 * de El Pajuil, como las trae su formato del 23/10/2026. Se cargan aquí porque solo el
 * superadministrador puede editarlas en Plataforma → Empresas → «Formatos». Una sola vez
 * y sin pisar: si ya tienen valor, no se tocan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tenant = Tenant::query()->where('slug', 'ELPAJUIL')->first();

        if (! $tenant) {
            return;
        }

        $settings = $tenant->settings ?? [];

        if (blank(data_get($settings, 'documents.legal_name'))) {
            data_set($settings, 'documents.legal_name', 'EXTRACTORA EL PAJUIL CENTRAL DEL CASANARE SAS ZOMAC');
        }

        if (blank(data_get($settings, 'human_resources.form_signer'))) {
            data_set($settings, 'human_resources.form_signer', 'Danna Fernanda Torres');
        }

        $tenant->forceFill(['settings' => $settings])->save();
    }

    public function down(): void
    {
        // Quedan: son datos de la empresa, que se cambian en Plataforma → Empresas.
    }
};
