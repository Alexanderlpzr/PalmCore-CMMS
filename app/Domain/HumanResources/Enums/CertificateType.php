<?php

namespace App\Domain\HumanResources\Enums;

/**
 * Los certificados que expide talento humano, con el control documental de sus formatos
 * de Word (código, versión y fecha del formato), para que el papel sea reconocible como el
 * mismo de siempre.
 *
 * El de persona activa es para quien sigue en la empresa; los cuatro de egreso (TH-EGR) se
 * entregan cuando alguien se retira.
 */
enum CertificateType: string
{
    case ActiveEmployment = 'certificacion_activa';
    case FormerEmployment = 'certificacion_inactiva';
    case SeveranceWithdrawal = 'retiro_cesantias';
    case Clearance = 'paz_y_salvo';
    case ExitExam = 'examen_egreso';

    public function code(): string
    {
        return match ($this) {
            self::ActiveEmployment => 'TH-SOL-F-002',
            self::FormerEmployment => 'TH-EGR-F-001',
            self::SeveranceWithdrawal => 'TH-EGR-F-002',
            self::Clearance => 'TH-EGR-F-003',
            self::ExitExam => 'TH-EGR-F-004',
        };
    }

    public function version(): string
    {
        return '01';
    }

    /** La fecha del formato, la del encabezado de cada plantilla. */
    public function formDate(): string
    {
        return match ($this) {
            self::ActiveEmployment => '24/10/2026',
            self::FormerEmployment, self::SeveranceWithdrawal, self::Clearance => '22/10/2026',
            self::ExitExam => '23/10/2026',
        };
    }

    /** El título del encabezado. */
    public function heading(): string
    {
        return match ($this) {
            self::ActiveEmployment => 'CERTIFICACIÓN LABORAL – PERSONA ACTIVA',
            self::FormerEmployment => 'CERTIFICACIÓN LABORAL – PERSONA INACTIVA',
            self::SeveranceWithdrawal => 'AUTORIZACIÓN DE RETIRO TOTAL DE CESANTÍAS',
            self::Clearance => 'PAZ Y SALVO',
            self::ExitExam => 'AUTORIZACIÓN DE REALIZACIÓN EXAMEN DE EGRESO',
        };
    }

    /** Cómo se llama en la pantalla y en el nombre del archivo. */
    public function label(): string
    {
        return match ($this) {
            self::ActiveEmployment => 'Certificación laboral',
            self::FormerEmployment => 'Certificación laboral (retirado)',
            self::SeveranceWithdrawal => 'Autorización de retiro total de cesantías',
            self::Clearance => 'Paz y salvo',
            self::ExitExam => 'Autorización de exámenes de egreso',
        };
    }

    /** Los de egreso hablan de una fecha de retiro: piden que la persona se haya ido. */
    public function isForExit(): bool
    {
        return $this !== self::ActiveEmployment;
    }

    /**
     * Los que aplican a la persona: el de activa a quien sigue, los de egreso a quien se
     * retiró o tiene fecha de retiro.
     *
     * @return list<self>
     */
    public static function for(bool $hasLeft): array
    {
        return array_values(array_filter(self::cases(), fn (self $type): bool => $type->isForExit() === $hasLeft));
    }

    /** @return array<string, string> */
    public static function options(bool $hasLeft): array
    {
        return collect(self::for($hasLeft))
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->code().' · '.$type->label()])
            ->all();
    }
}
