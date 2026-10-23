<?php

namespace App\Domain\HumanResources\Enums;

/**
 * Lo que va en la carpeta de un trabajador.
 *
 * Los once primeros son la lista «Documentos pendientes» de la hoja «Items» del libro de
 * requerimientos de El Pajuil: la carpeta está completa cuando tiene uno de cada. «Otro»
 * existe para no obligar a nadie a mentir sobre el tipo, y no cuenta para completarla.
 */
enum EmployeeDocumentType: string
{
    case HojaDeVida = 'hoja_de_vida';
    case Cedula = 'cedula';
    case CertificadoBancario = 'certificado_bancario';
    case AfiliacionEps = 'afiliacion_eps';
    case AfiliacionArl = 'afiliacion_arl';
    case AfiliacionPension = 'afiliacion_pension';
    case AfiliacionCesantias = 'afiliacion_cesantias';
    case AfiliacionCaja = 'afiliacion_caja';
    case Contrato = 'contrato';
    case Examenes = 'examenes';
    case ClausulasAdicionales = 'clausulas_adicionales';

    /** Los que expide talento humano desde «Certificados»: no son de la carpeta de ingreso. */
    case Certificado = 'certificado';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::HojaDeVida => 'Hoja de vida',
            self::Cedula => 'Cédula de ciudadanía',
            self::CertificadoBancario => 'Certificado bancario',
            self::AfiliacionEps => 'Certificado afiliación EPS',
            self::AfiliacionArl => 'Certificado afiliación ARL',
            self::AfiliacionPension => 'Certificado afiliación fondo de pensión',
            self::AfiliacionCesantias => 'Certificado afiliación cesantías',
            self::AfiliacionCaja => 'Certificado afiliación caja de compensación',
            self::Contrato => 'Contrato',
            self::Examenes => 'Exámenes médicos',
            self::ClausulasAdicionales => 'Cláusulas adicionales TH-AL',
            self::Certificado => 'Certificado expedido',
            self::Otro => 'Otro',
        };
    }

    /** La etiqueta corta del checklist, donde once etiquetas largas no caben. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::AfiliacionEps => 'EPS',
            self::AfiliacionArl => 'ARL',
            self::AfiliacionPension => 'Pensión',
            self::AfiliacionCesantias => 'Cesantías',
            self::AfiliacionCaja => 'Caja',
            self::Cedula => 'Cédula',
            self::Examenes => 'Exámenes',
            self::ClausulasAdicionales => 'Cláusulas TH-AL',
            default => $this->label(),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::HojaDeVida, self::Cedula, self::CertificadoBancario => 'info',
            self::Contrato, self::ClausulasAdicionales => 'primary',
            self::Examenes => 'danger',
            self::AfiliacionEps, self::AfiliacionArl, self::AfiliacionPension,
            self::AfiliacionCesantias, self::AfiliacionCaja => 'success',
            self::Certificado => 'warning',
            self::Otro => 'gray',
        };
    }

    public function isRequired(): bool
    {
        return ! in_array($this, [self::Otro, self::Certificado], true);
    }

    /** @return list<self> */
    public static function required(): array
    {
        return array_values(array_filter(self::cases(), fn (self $case): bool => $case->isRequired()));
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
