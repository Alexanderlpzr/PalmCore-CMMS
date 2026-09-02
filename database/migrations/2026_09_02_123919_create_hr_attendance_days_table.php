<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El día de trabajo de una persona, con sus horas ya repartidas en las siete bolsas.
 *
 * Es el puente entre el reloj y la nómina, y existe como tabla —en vez de calcularse al
 * vuelo cada vez— por una razón que no es de rendimiento: estas horas las **confirma un
 * supervisor** antes de que lleguen a liquidarse. Un cálculo al vuelo no se puede firmar.
 * Y una vez firmado, cambiar la ventana nocturna o la jornada ordinaria no puede alterar
 * lo que alguien ya revisó y aprobó; por eso el resultado se congela aquí en lugar de
 * recalcularse contra los parámetros del día en que se consulte.
 *
 * `work_date` es el día en que **arrancó** el turno, no el día en que cayó cada hora. Un
 * turno de sábado 22:00 a domingo 06:00 es un jornal del sábado, y así lo cuenta el libro
 * de Excel. Las horas de ese turno, en cambio, sí están repartidas según el día real al
 * que pertenecen: las seis de después de medianoche están en la bolsa dominical. Las dos
 * cosas conviven en la misma fila y no se contradicen: una cuenta días, la otra paga
 * recargos.
 *
 * `ordinary_hours` no se paga aparte —ya va dentro del salario mensual— pero se guarda
 * igual: sin ella no hay forma de comprobar que las horas del día cuadran con lo que
 * marcó el reloj.
 *
 * Las anomalías se guardan y no se esconden. Pasar del tope de horas extras no bloquea el
 * registro: el trabajo ya ocurrió y no pagarlo no lo deshace. Lo que corresponde es que
 * quede escrito y que alguien lo vea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_attendance_days', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('hr_employees')->cascadeOnDelete();

            // El día que sostiene el jornal. Ver la nota de cabecera.
            $table->date('work_date');

            // Cuatro decimales: los turnos no siempre caen en horas redondas y redondear
            // a dos por fila acumula centavos a lo largo de 48 personas y 30 días.
            $table->decimal('ordinary_hours', 8, 4)->default(0);
            $table->decimal('night_surcharge_hours', 8, 4)->default(0);
            $table->decimal('sunday_surcharge_hours', 8, 4)->default(0);
            $table->decimal('night_sunday_surcharge_hours', 8, 4)->default(0);
            $table->decimal('overtime_day_hours', 8, 4)->default(0);
            $table->decimal('overtime_night_hours', 8, 4)->default(0);
            $table->decimal('overtime_sunday_day_hours', 8, 4)->default(0);
            $table->decimal('overtime_sunday_night_hours', 8, 4)->default(0);

            // Redundante con la suma de las ocho, y a propósito: es la columna que se
            // filtra y se ordena en pantalla, y sumar ocho decimales en cada consulta
            // para ordenar por «quién trabajó más» no vale la pena.
            $table->decimal('worked_hours', 8, 4)->default(0);

            // AttendanceDayStatus: 'propuesta' | 'confirmada'.
            $table->string('status', 20)->default('propuesta');

            $table->foreignUuid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('confirmed_at', 0)->nullable();

            // Cuándo se derivó de los escaneos. Sirve para saber si una fila quedó atrás
            // de una marca que llegó después.
            $table->timestampTz('built_at', 0)->nullable();

            // 'qr' cuando salió del reloj; 'manual' cuando alguien la escribió a mano.
            $table->string('source', 10)->default('qr');

            // Topes excedidos, turnos sin cerrar, empleado excluido con horas. Lista de
            // cadenas; no se validan contra un catálogo porque son para leer, no para
            // filtrar.
            $table->json('anomalies')->nullable();

            $table->text('notes')->nullable();

            $table->timestampsTz(0);

            // Un día por persona. Reconstruir un período sobrescribe la fila propuesta en
            // vez de duplicarla.
            $table->unique(['tenant_id', 'employee_id', 'work_date']);
            $table->index(['tenant_id', 'work_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_days');
    }
};
