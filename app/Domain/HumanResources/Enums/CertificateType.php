<?php

namespace App\Domain\HumanResources\Enums;

/**
 * Los certificados que expide talento humano, cada uno con su formato de Word: el código
 * es el nombre de la plantilla en `resources/templates/certificados`.
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

    /** Cómo se llama en la pantalla. */
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

    /** Cómo lo nombra talento humano en el archivo: «TH-EGR-F-001-068. Certificación laboral Carlos…». */
    public function fileLabel(): string
    {
        return match ($this) {
            self::ActiveEmployment, self::FormerEmployment => 'Certificación laboral',
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
