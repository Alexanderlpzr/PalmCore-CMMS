<?php

namespace App\Domain\HumanResources\Support;

/**
 * Las listas cerradas de la ficha del trabajador, en un solo sitio.
 *
 * Salen de la hoja «Items» del libro de requerimientos de El Pajuil, donde esos campos
 * figuran como «selección múltiple», y de los valores que de verdad aparecen en la hoja
 * «Base datos». El formulario, la tabla y el importador leen de aquí para que un valor
 * importado siempre sea uno que el formulario sabe mostrar.
 *
 * No son enums porque no gobiernan ningún cálculo: son etiquetas de una ficha.
 */
final class EmployeeProfileOptions
{
    /** @var array<string, string> */
    public const DOCUMENT_TYPES = [
        'CC' => 'Cédula de ciudadanía',
        'CE' => 'Cédula de extranjería',
        'TI' => 'Tarjeta de identidad',
        'PA' => 'Pasaporte',
        'PEP' => 'PEP / PPT',
    ];

    /** @var array<string, string> */
    public const SEXES = [
        'M' => 'Masculino',
        'F' => 'Femenino',
    ];

    /** @var array<string, string> */
    public const RESIDENCE_ZONES = [
        'urbana' => 'Urbana',
        'rural' => 'Rural',
    ];

    /** @var list<string> */
    public const BLOOD_TYPES = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];

    /**
     * La prestación de servicios no es nómina, pero aparece en la ficha para que conste.
     *
     * @var array<string, string>
     */
    public const CONTRACT_TYPES = [
        'indefinido' => 'Término indefinido',
        'fijo' => 'Término fijo',
        'obra_labor' => 'Obra o labor',
        'aprendizaje' => 'Aprendizaje (SENA)',
        'prestacion_servicios' => 'Prestación de servicios',
    ];

    /** @var array<string, string> */
    public const AREAS = [
        'operativo' => 'Operativo',
        'administrativo' => 'Administrativo',
    ];

    /** @var array<string, string> */
    public const SPECIFIC_AREAS = [
        'procesos' => 'Procesos',
        'mantenimiento' => 'Mantenimiento',
        'laboratorio' => 'Laboratorio',
        'calidad' => 'Calidad',
        'mope' => 'MOPE',
        'oficios_varios' => 'Oficios varios',
        'administrativo' => 'Administrativo',
    ];

    /** @var list<string> */
    public const GARMENT_SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

    /**
     * Sugerencias, no una lista cerrada: la entidad nueva existe antes de que alguien
     * la agregue aquí, y bloquearla sería peor que escribirla a mano.
     *
     * @var array<string, list<string>>
     */
    public const AFFILIATION_SUGGESTIONS = [
        'eps' => ['NUEVA EPS', 'CAPRESOCA', 'SANITAS', 'SALUD TOTAL', 'FOMAG', 'SURA', 'COMPENSAR', 'FAMISANAR', 'COOSALUD'],
        'pension_fund' => ['PORVENIR', 'COLPENSIONES', 'PROTECCIÓN', 'COLFONDOS', 'SKANDIA'],
        'severance_fund' => ['PORVENIR', 'FONDO NACIONAL DEL AHORRO', 'PROTECCIÓN', 'COLFONDOS', 'SKANDIA'],
        'arl' => ['SEGUROS BOLÍVAR', 'SURA', 'POSITIVA', 'COLMENA', 'AXA COLPATRIA'],
        'compensation_fund' => ['COMFACASANARE', 'COMFAMILIAR', 'COMPENSAR', 'CAFAM', 'COLSUBSIDIO'],
    ];

    /** @return array<string, string> */
    public static function bloodTypes(): array
    {
        return array_combine(self::BLOOD_TYPES, self::BLOOD_TYPES);
    }

    /** @return array<string, string> */
    public static function garmentSizes(): array
    {
        return array_combine(self::GARMENT_SIZES, self::GARMENT_SIZES);
    }
}
