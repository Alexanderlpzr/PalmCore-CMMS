<?php

namespace App\Domain\HumanResources\Enums;

/**
 * El catálogo de todo lo que talento humano puede cambiar sin tocar código.
 *
 * La lista sale de auditar el libro de Excel de la extractora, columna por columna. Todo
 * número que allí está escrito a mano o cableado en una fórmula, y que la ley o la
 * empresa pueden mover, está aquí. Los que no están —el 30 de días del mes es el
 * ejemplo— es porque moverlos cambia el significado de las otras columnas, y eso es una
 * migración, no un ajuste.
 *
 * Dos de estos valores son la razón de que el módulo exista. El libro divide entre 220
 * horas y aplica una base dominical del 80%, que son los valores de la jornada de 44
 * horas: para un período de agosto de 2026 hay que confirmar con contabilidad si
 * corresponden 210 y 90%. Con vigencias, esa confirmación es cargar un tramo nuevo desde
 * julio de 2026 y los meses anteriores quedan intactos. Sin vigencias, sería editar un
 * número y perder la trazabilidad de lo ya pagado.
 */
enum PayrollParameter: string
{
    // ── Valores anuales del Gobierno ──────────────────────────────────────────
    case Smlmv = 'smlmv';
    case TransportAllowance = 'transport_allowance';
    case TransportAllowanceMaxSmlmv = 'transport_allowance_max_smlmv';
    case UvtValue = 'uvt_value';

    // ── Jornada ───────────────────────────────────────────────────────────────
    case MonthlyHoursDivisor = 'monthly_hours_divisor';
    case MonthDays = 'month_days';
    case OrdinaryHoursPerDay = 'ordinary_hours_per_day';
    case NightWindowStart = 'night_window_start';
    case NightWindowEnd = 'night_window_end';
    case MaxOvertimeHoursDay = 'max_overtime_hours_day';
    case MaxOvertimeHoursWeek = 'max_overtime_hours_week';

    // ── Recargos y horas extras ───────────────────────────────────────────────
    case SurchargeNight = 'surcharge_night';
    case SurchargeSunday = 'surcharge_sunday';
    case SurchargeNightSunday = 'surcharge_night_sunday';
    case OvertimeDay = 'overtime_day';
    case OvertimeNight = 'overtime_night';
    case OvertimeSundayDay = 'overtime_sunday_day';
    case OvertimeSundayNight = 'overtime_sunday_night';

    // ── Aportes del trabajador ────────────────────────────────────────────────
    case HealthEmployeeRate = 'health_employee_rate';
    case PensionEmployeeRate = 'pension_employee_rate';

    public function label(): string
    {
        return match ($this) {
            self::Smlmv => 'Salario mínimo legal mensual',
            self::TransportAllowance => 'Auxilio de transporte',
            self::TransportAllowanceMaxSmlmv => 'Tope de auxilio, en SMLMV',
            self::UvtValue => 'Valor del UVT',
            self::MonthlyHoursDivisor => 'Divisor de horas mensuales',
            self::MonthDays => 'Días del mes para liquidar',
            self::OrdinaryHoursPerDay => 'Jornada ordinaria diaria',
            self::NightWindowStart => 'Inicio de la jornada nocturna',
            self::NightWindowEnd => 'Fin de la jornada nocturna',
            self::MaxOvertimeHoursDay => 'Tope de horas extras por día',
            self::MaxOvertimeHoursWeek => 'Tope de horas extras por semana',
            self::SurchargeNight => 'Recargo nocturno',
            self::SurchargeSunday => 'Recargo dominical y festivo',
            self::SurchargeNightSunday => 'Recargo nocturno dominical',
            self::OvertimeDay => 'Hora extra diurna',
            self::OvertimeNight => 'Hora extra nocturna',
            self::OvertimeSundayDay => 'Hora extra dominical diurna',
            self::OvertimeSundayNight => 'Hora extra dominical nocturna',
            self::HealthEmployeeRate => 'Aporte a salud del trabajador',
            self::PensionEmployeeRate => 'Aporte a pensión del trabajador',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::Smlmv, self::TransportAllowance, self::TransportAllowanceMaxSmlmv, self::UvtValue => 'Valores del año',
            self::MonthlyHoursDivisor, self::MonthDays, self::OrdinaryHoursPerDay,
            self::NightWindowStart, self::NightWindowEnd,
            self::MaxOvertimeHoursDay, self::MaxOvertimeHoursWeek => 'Jornada',
            self::SurchargeNight, self::SurchargeSunday, self::SurchargeNightSunday,
            self::OvertimeDay, self::OvertimeNight, self::OvertimeSundayDay, self::OvertimeSundayNight => 'Recargos y horas extras',
            self::HealthEmployeeRate, self::PensionEmployeeRate => 'Aportes del trabajador',
        };
    }

    /**
     * Cómo se lee el número. Determina el sufijo en pantalla y, sobre todo, evita que
     * alguien escriba 35 donde va 0,35.
     */
    public function unit(): PayrollParameterUnit
    {
        return match ($this) {
            self::Smlmv, self::TransportAllowance, self::UvtValue => PayrollParameterUnit::Money,
            self::TransportAllowanceMaxSmlmv, self::MonthlyHoursDivisor, self::MonthDays,
            self::OrdinaryHoursPerDay, self::MaxOvertimeHoursDay,
            self::MaxOvertimeHoursWeek => PayrollParameterUnit::Number,
            self::NightWindowStart, self::NightWindowEnd => PayrollParameterUnit::HourOfDay,
            default => PayrollParameterUnit::Factor,
        };
    }

    /**
     * Los valores tal como los aplica hoy el libro de la extractora. Son el punto de
     * partida de la primera vigencia, no una recomendación: los dos de jornada están
     * justamente pendientes de confirmación.
     */
    public function seedValue(): float
    {
        return match ($this) {
            self::Smlmv => 1750905,
            self::TransportAllowance => 249095,
            self::TransportAllowanceMaxSmlmv => 2,
            self::UvtValue => 0,
            self::MonthlyHoursDivisor => 220,
            self::MonthDays => 30,
            self::OrdinaryHoursPerDay => 8,
            self::NightWindowStart => 21,
            self::NightWindowEnd => 6,
            self::MaxOvertimeHoursDay => 2,
            self::MaxOvertimeHoursWeek => 12,
            self::SurchargeNight => 0.35,
            self::SurchargeSunday => 0.80,
            self::SurchargeNightSunday => 1.15,
            self::OvertimeDay => 1.25,
            self::OvertimeNight => 1.75,
            self::OvertimeSundayDay => 2.05,
            self::OvertimeSundayNight => 2.55,
            self::HealthEmployeeRate => 0.04,
            self::PensionEmployeeRate => 0.04,
        };
    }

    /**
     * Cómo debería componerse este factor a partir del recargo dominical, cuando aplica.
     *
     * El libro construye los cuatro factores de domingo sobre una sola base: el nocturno
     * dominical es 80% + 35%, la extra dominical diurna es 1 + 80% + 25%, la nocturna es
     * 1 + 80% + 75%. Guardamos los siete por separado porque legalmente son números
     * independientes y algún día podrían dejar de componerse así, pero la relación se
     * declara aquí para poder avisar en pantalla cuando alguien cambie la base y olvide
     * los derivados. Avisa; no corrige solo.
     *
     * @return array{base: self, extra: float}|null
     */
    public function derivedFromSunday(): ?array
    {
        return match ($this) {
            self::SurchargeNightSunday => ['base' => self::SurchargeSunday, 'extra' => 0.35],
            self::OvertimeSundayDay => ['base' => self::SurchargeSunday, 'extra' => 1.25],
            self::OvertimeSundayNight => ['base' => self::SurchargeSunday, 'extra' => 1.75],
            default => null,
        };
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
