<?php

namespace App\Console\Commands;

use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Domain\HumanResources\Support\EmployeeProfileOptions as Options;
use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader;

use function Laravel\Prompts\select;

/**
 * Carga la hoja «Base datos» del libro de requerimientos de El Pajuil: la ficha de cada
 * trabajador, activo o no.
 *
 * Las columnas se buscan por su encabezado y no por posición, porque esta hoja la
 * mantiene talento humano a mano y una columna insertada no debería cargar el celular
 * en el campo del correo.
 *
 * Tres reglas deciden qué se escribe:
 *
 * - La llave es la cédula. El «código empresarial» no sirve: en el libro hay uno repetido.
 * - Una celda vacía no borra nada. La hoja tiene huecos —trece trabajadores sin fecha de
 *   nacimiento— y cargarla no puede deshacer lo que alguien ya completó en la ficha.
 * - El salario no se toca. No está en esta hoja, y el que ya cargó la nómina manda.
 *
 * La columna EDAD se ignora a propósito: es una fórmula que marca 126 años cuando falta
 * la fecha de nacimiento, y la ficha la calcula sola. BONO RODAMIENTO tampoco se carga:
 * una bonificación es un valor de nómina con vigencia, y se registra en su pestaña.
 */
class ImportEmployeeRoster extends Command
{
    protected $signature = 'hr:import-roster
        {file : Ruta al .xlsx con la hoja «Base datos»}
        {--tenant= : ID de la empresa}
        {--sheet=Base datos : Nombre de la hoja}
        {--dry-run : Muestra lo que se cargaría sin escribir nada}';

    protected $description = 'Importa la base de datos de trabajadores (ficha personal) desde Excel';

    /**
     * Encabezado del Excel → campo. Se comparan sin tildes, sin espacios de sobra y en
     * mayúsculas, porque el libro escribe «CÉDULA» y «DIA » con espacio al final.
     *
     * @var array<string, string>
     */
    private const HEADERS = [
        'CODIGO EMPRESARIAL' => 'employee_code',
        'ESTADO' => 'status',
        'NOMBRE' => 'name',
        'CEDULA' => 'document_number',
        'CARGO' => 'position',
        'AREA GENERAL' => 'area',
        'AREA ESPECIFICA' => 'area_specific',
        'FECHA DE INGRESO' => 'hire_date',
        'TIPO DE CONTRATO' => 'contract_type',
        'SEXO' => 'sex',
        'HIJOS' => 'has_children',
        'CORREO' => 'email',
        'CELULAR' => 'phone',
        'FECHA DE NACIMIENTO' => 'birth_date',
        'FECHA DE EXPEDICION' => 'document_issue_date',
        'LUGAR DE EXPEDICION' => 'document_issue_place',
        'CONTACTO EMERGENCIA' => 'emergency_contact_phone',
        'RH' => 'blood_type',
        'DIRECCION' => 'address',
        'MUNICIPIO/VEREDA' => 'city',
        'EPS' => 'eps',
        'FONDO CESANTIAS' => 'severance_fund',
        'FONDO PENSIONES' => 'pension_fund',
        'ARL' => 'arl',
        'CAJA COMPENSACION' => 'compensation_fund',
        'T.CAMISA' => 'shirt_size',
        'T.PANTALON' => 'pants_size',
        'T.BOTAS' => 'boot_size',
        'ROMPE VIENTOS' => 'jacket_size',
    ];

    /** Nombres de la misma entidad escritos de dos formas en el libro. */
    private const ENTITY_ALIASES = [
        'BOLIVAR' => 'SEGUROS BOLÍVAR',
        'SEGUROS BOLIVAR' => 'SEGUROS BOLÍVAR',
    ];

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! is_readable($file)) {
            $this->error("No se puede leer el archivo: {$file}");

            return self::FAILURE;
        }

        $tenant = $this->resolveTenant();

        if (! $tenant) {
            return self::FAILURE;
        }

        $rows = $this->readRows($file, (string) $this->option('sheet'));

        if ($rows === null) {
            return self::FAILURE;
        }

        $this->info(sprintf('Encontrados %d trabajadores en la hoja.', count($rows)));

        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $updated = 0;
        $withoutSalary = 0;

        DB::transaction(function () use ($rows, $tenant, $dryRun, &$created, &$updated, &$withoutSalary): void {
            foreach ($rows as $attributes) {
                $employee = Employee::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('document_number', $attributes['document_number'])
                    ->first();

                $employee ? $updated++ : $created++;

                if ((float) ($employee?->base_salary ?? 0) === 0.0) {
                    $withoutSalary++;
                }

                if ($dryRun) {
                    continue;
                }

                $employee ??= new Employee(['tenant_id' => $tenant->id]);
                $employee->forceFill(['tenant_id' => $tenant->id])->fill($attributes)->save();
            }
        });

        $this->table(['', 'Trabajadores'], [
            ['Nuevos', $created],
            ['Actualizados', $updated],
            ['Sin salario cargado', $withoutSalary],
        ]);

        if ($withoutSalary > 0) {
            $this->warn('Los que no tienen salario no se pueden liquidar: complételo en su ficha o cargue el libro de nómina.');
        }

        if ($dryRun) {
            $this->comment('Ensayo: no se escribió nada.');
        }

        return self::SUCCESS;
    }

    /**
     * Lee la hoja y devuelve un array de atributos por trabajador, solo con las celdas
     * que traen algo.
     *
     * @return list<array<string, mixed>>|null
     */
    private function readRows(string $file, string $sheetName): ?array
    {
        $reader = new Reader;
        $reader->open($file);

        $columns = null;
        $rows = [];
        $found = false;

        foreach ($reader->getSheetIterator() as $sheet) {
            if (trim($sheet->getName()) !== trim($sheetName)) {
                continue;
            }

            $found = true;

            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(fn (Cell $cell): mixed => $this->cellValue($cell), $row->getCells());

                if ($columns === null) {
                    $columns = $this->headerColumns($cells);

                    continue;
                }

                $attributes = $this->attributesFrom($cells, $columns);

                if ($attributes !== null) {
                    $rows[] = $attributes;
                }
            }
        }

        $reader->close();

        if (! $found) {
            $this->error("El libro no tiene la hoja «{$sheetName}».");

            return null;
        }

        if ($columns === null) {
            $this->error('No se encontró la fila de encabezados (la que dice CÉDULA y NOMBRE).');

            return null;
        }

        return $rows;
    }

    /**
     * Devuelve el mapa campo → índice si esta fila es la de encabezados, o null si no.
     *
     * @param  array<int, mixed>  $cells
     * @return array<string, int>|null
     */
    private function headerColumns(array $cells): ?array
    {
        $columns = [];

        foreach ($cells as $index => $value) {
            $header = self::normalizeHeader((string) $value);

            if (isset(self::HEADERS[$header])) {
                $columns[self::HEADERS[$header]] ??= $index;
            }
        }

        return isset($columns['document_number'], $columns['name']) ? $columns : null;
    }

    /**
     * @param  array<int, mixed>  $cells
     * @param  array<string, int>  $columns
     * @return array<string, mixed>|null
     */
    private function attributesFrom(array $cells, array $columns): ?array
    {
        $raw = fn (string $field): mixed => isset($columns[$field]) ? ($cells[$columns[$field]] ?? null) : null;

        $documentNumber = self::digits($raw('document_number'));
        $name = self::text($raw('name'));

        if ($documentNumber === null || $name === null) {
            return null;
        }

        [$firstName, $lastName] = self::splitName($name);

        $attributes = [
            'document_type' => 'CC',
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'employee_code' => self::text($raw('employee_code')),
            'status' => match (Str::upper((string) self::text($raw('status')))) {
                'ACTIVO' => EmploymentStatus::Activo->value,
                'INACTIVO', 'RETIRADO' => EmploymentStatus::Retirado->value,
                'SUSPENDIDO' => EmploymentStatus::Suspendido->value,
                default => null,
            },
            'position' => self::text($raw('position')),
            'area' => self::closedListKey($raw('area'), Options::AREAS),
            'area_specific' => self::closedListKey($raw('area_specific'), Options::SPECIFIC_AREAS),
            'hire_date' => self::date($raw('hire_date')),
            'contract_type' => self::closedListKey($raw('contract_type'), Options::CONTRACT_TYPES),
            'sex' => self::closedListKey($raw('sex'), Options::SEXES),
            'has_children' => match (Str::upper((string) self::text($raw('has_children')))) {
                'SI', 'SÍ' => true,
                'NO' => false,
                default => null,
            },
            'email' => filter_var(Str::lower((string) self::text($raw('email'))), FILTER_VALIDATE_EMAIL) ?: null,
            'phone' => self::digits($raw('phone')),
            'birth_date' => self::date($raw('birth_date')),
            'document_issue_date' => self::date($raw('document_issue_date')),
            'document_issue_place' => self::text($raw('document_issue_place')),
            'emergency_contact_phone' => self::digits($raw('emergency_contact_phone')),
            'blood_type' => in_array($rh = Str::upper(str_replace(' ', '', (string) self::text($raw('blood_type')))), Options::BLOOD_TYPES, true) ? $rh : null,
            'address' => self::text($raw('address')),
            'city' => ($city = self::text($raw('city'))) ? Str::title(Str::lower($city)) : null,
            'eps' => self::entity($raw('eps')),
            'severance_fund' => self::entity($raw('severance_fund')),
            'pension_fund' => self::entity($raw('pension_fund')),
            'arl' => self::entity($raw('arl')),
            'compensation_fund' => self::entity($raw('compensation_fund')),
            'shirt_size' => self::text($raw('shirt_size')),
            'pants_size' => self::text($raw('pants_size')),
            'boot_size' => self::text($raw('boot_size')),
            'jacket_size' => self::text($raw('jacket_size')),
        ];

        // Una celda vacía no borra lo que la ficha ya tiene.
        return array_filter($attributes, fn (mixed $value): bool => $value !== null);
    }

    // ── Normalización ─────────────────────────────────────────────────────────

    private static function normalizeHeader(string $header): string
    {
        return Str::upper(trim((string) preg_replace('/\s+/', ' ', Str::ascii($header))));
    }

    private static function text(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        $text = trim((string) preg_replace('/\s+/', ' ', (string) ($value ?? '')));

        return $text === '' ? null : $text;
    }

    /** Cédulas y teléfonos: el libro los trae como número o como «313 3275094». */
    private static function digits(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) self::text($value));

        return $digits === '' ? null : $digits;
    }

    private static function date(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        // Una fecha sin formato de fecha llega como el número de serie de Excel: días
        // desde el 30 de diciembre de 1899.
        if (is_int($value) || is_float($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        $text = self::text($value);

        if ($text === null) {
            return null;
        }

        try {
            return Carbon::parse($text)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * La clave de una lista cerrada a partir del texto del libro, comparando contra la
     * clave o contra la etiqueta: «OFICIOS VARIOS», «INDEFINIDO» y «M» entran todos.
     *
     * @param  array<string, string>  $options
     */
    private static function closedListKey(mixed $value, array $options): ?string
    {
        $needle = Str::slug((string) self::text($value), '_');

        if ($needle === '') {
            return null;
        }

        foreach ($options as $key => $label) {
            if ($needle === Str::slug($key, '_') || $needle === Str::slug($label, '_')) {
                return $key;
            }

            // «INDEFINIDO» contra «Término indefinido».
            if (str_ends_with(Str::slug($label, '_'), '_'.$needle)) {
                return $key;
            }
        }

        return null;
    }

    private static function entity(mixed $value): ?string
    {
        $name = self::text($value);

        if ($name === null) {
            return null;
        }

        $name = Str::upper($name);

        return self::ENTITY_ALIASES[Str::upper(Str::ascii($name))] ?? $name;
    }

    /**
     * Misma convención que el importador de nómina: los dos últimos son apellidos. Así
     * las dos cargas no parten distinto a la misma persona.
     *
     * @return array{0: string, 1: string}
     */
    private static function splitName(string $full): array
    {
        $parts = preg_split('/\s+/', trim($full)) ?: [];

        if (count($parts) <= 2) {
            return [$parts[0] ?? $full, $parts[1] ?? ''];
        }

        $lastNames = array_splice($parts, -2);

        return [implode(' ', $parts), implode(' ', $lastNames)];
    }

    private function cellValue(Cell $cell): mixed
    {
        return $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell->getValue();
    }

    private function resolveTenant(): ?Tenant
    {
        if ($id = $this->option('tenant')) {
            $tenant = Tenant::find($id);

            if (! $tenant) {
                $this->error("No existe la empresa {$id}.");
            }

            return $tenant;
        }

        $tenants = Tenant::query()->orderBy('name')->pluck('name', 'id')->all();

        if ($tenants === []) {
            $this->error('No hay empresas registradas.');

            return null;
        }

        return Tenant::find(select('¿En qué empresa?', $tenants));
    }
}
