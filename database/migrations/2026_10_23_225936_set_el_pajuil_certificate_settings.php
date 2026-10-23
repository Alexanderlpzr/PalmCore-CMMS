<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * Los datos fijos de los certificados de talento humano de El Pajuil, como los traen sus
 * formatos de Word del 22 al 24/10/2026: la ciudad donde se expiden, la IPS del examen de
 * egreso y el correo del pie. Quién expide y su firma los elige talento humano en
 * «Certificados». Una sola vez y sin pisar lo que ya tenga valor.
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
        $defaults = [
            'city' => 'Maní – Casanare',
            'exit_exam_provider' => 'Avancemos Salud IPS – Cra 7 #19-72 Maní, Casanare',
            'contact_email' => 'rrhh@elpajuil.com',
        ];

        foreach ($defaults as $key => $value) {
            if (blank(data_get($settings, "human_resources.certificates.{$key}"))) {
                data_set($settings, "human_resources.certificates.{$key}", $value);
            }
        }

        $tenant->forceFill(['settings' => $settings])->save();
    }

    public function down(): void
    {
        // Quedan: son datos de la empresa, que talento humano cambia en «Certificados».
    }
};
