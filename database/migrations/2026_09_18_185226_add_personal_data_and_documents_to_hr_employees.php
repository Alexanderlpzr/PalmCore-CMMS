<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La hoja de vida del trabajador: sus datos personales y su carpeta de documentos.
 *
 * Hasta aquí el maestro de personal solo tenía lo que la nómina necesita para liquidar.
 * Talento humano lleva además la carpeta física de cada persona —cédula, contrato,
 * exámenes médicos, afiliaciones— y el contacto a quien llamar si algo pasa en planta.
 * Todo es opcional: los 48 de la extractora ya existen y nadie va a llenar cuarenta
 * campos antes de poder liquidar la quincena.
 *
 * Los campos son los de la hoja «Items» del libro de requerimientos de El Pajuil, y la
 * hoja «Base datos» del mismo libro se carga con `hr:import-roster`. `employee_code` no
 * es único a propósito: en ese libro el código empresarial 220820231 está repetido en
 * dos personas, y la llave del trabajador sigue siendo su documento.
 *
 * Los documentos van en el disco privado y se sirven por un controlador que revisa el
 * permiso. Una copia de la cédula o un examen de ingreso no puede quedar detrás de una
 * URL pública, que es lo que pasaría en el disco persistente de las fotos de OT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->string('employee_code', 30)->nullable()->after('user_id');
            $table->date('document_issue_date')->nullable()->after('document_number');
            $table->string('document_issue_place', 120)->nullable()->after('document_issue_date');
            $table->date('birth_date')->nullable()->after('last_name');
            $table->string('sex', 1)->nullable()->after('birth_date');
            $table->boolean('has_children')->nullable()->after('sex');
            $table->unsignedSmallInteger('children_count')->nullable()->after('has_children');
            $table->string('blood_type', 5)->nullable()->after('birth_date');
            $table->string('allergies', 255)->nullable()->after('blood_type');

            $table->string('address', 255)->nullable()->after('allergies');
            $table->string('city', 120)->nullable()->after('address');
            $table->string('residence_zone', 10)->nullable()->after('city');
            $table->string('phone', 30)->nullable()->after('city');
            $table->string('email', 150)->nullable()->after('phone');

            $table->string('emergency_contact_name', 150)->nullable()->after('email');
            $table->string('emergency_contact_relationship', 60)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone', 30)->nullable()->after('emergency_contact_relationship');

            $table->string('contract_type', 40)->nullable()->after('position');
            $table->string('area', 40)->nullable()->after('contract_type');
            $table->string('area_specific', 60)->nullable()->after('area');

            $table->string('arl', 120)->nullable()->after('severance_fund');
            $table->string('compensation_fund', 120)->nullable()->after('arl');

            // Dotación: se pide tres veces al año y hoy vive en una columna del Excel.
            $table->string('shirt_size', 10)->nullable()->after('compensation_fund');
            $table->string('pants_size', 10)->nullable()->after('shirt_size');
            $table->string('boot_size', 10)->nullable()->after('pants_size');
            $table->string('jacket_size', 10)->nullable()->after('boot_size');
        });

        Schema::create('hr_employee_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('hr_employees')->cascadeOnDelete();

            // EmployeeDocumentType enum.
            $table->string('document_type', 40);
            $table->string('title', 255);

            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type', 120)->default('application/octet-stream');

            // El examen médico, el curso de alturas y la afiliación vencen, y el vencido
            // es justo el que importa encontrar.
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();

            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz(0);
            $table->softDeletesTz();

            $table->index(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_documents');

        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropColumn([
                'employee_code',
                'document_issue_date',
                'document_issue_place',
                'birth_date',
                'sex',
                'has_children',
                'children_count',
                'residence_zone',
                'area',
                'area_specific',
                'arl',
                'shirt_size',
                'pants_size',
                'boot_size',
                'jacket_size',
                'blood_type',
                'allergies',
                'address',
                'city',
                'phone',
                'email',
                'emergency_contact_name',
                'emergency_contact_relationship',
                'emergency_contact_phone',
                'contract_type',
                'compensation_fund',
            ]);
        });
    }
};
