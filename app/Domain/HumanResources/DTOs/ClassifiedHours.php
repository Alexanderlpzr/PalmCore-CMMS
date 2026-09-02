<?php

namespace App\Domain\HumanResources\DTOs;

use App\Domain\HumanResources\Enums\PayrollParameter;

/**
 * Las horas de un día ya repartidas en las siete bolsas que paga la nómina.
 *
 * Las bolsas no son una taxonomía inventada: son el cruce de dos preguntas —¿la hora cae
 * en jornada nocturna? ¿el día es domingo o festivo?— con una tercera, ¿pasó del tope de
 * la jornada ordinaria? De ahí salen ocho combinaciones, y la octava, la hora ordinaria
 * diurna en día hábil, no se paga aparte porque ya va dentro del salario mensual. Quedan
 * siete, exactamente las siete columnas del libro de Excel.
 *
 *                    │ Diurna                  │ Nocturna
 *   ─────────────────┼─────────────────────────┼──────────────────────────────
 *   Ordinaria hábil  │ — (va en el salario)    │ recargo nocturno
 *   Ordinaria domin. │ recargo dominical       │ recargo nocturno dominical
 *   Extra hábil      │ extra diurna            │ extra nocturna
 *   Extra dominical  │ extra dominical diurna  │ extra dominical nocturna
 *
 * `ordinary` no se paga aparte, pero se guarda: es la hora trabajada que sostiene el
 * jornal, y sin ella no hay forma de comprobar que el día cuadra.
 */
readonly class ClassifiedHours
{
    public function __construct(
        /** Hora ordinaria diurna en día hábil. Ya está dentro del salario mensual. */
        public float $ordinary = 0.0,
        public float $nightSurcharge = 0.0,
        public float $sundaySurcharge = 0.0,
        public float $nightSundaySurcharge = 0.0,
        public float $overtimeDay = 0.0,
        public float $overtimeNight = 0.0,
        public float $overtimeSundayDay = 0.0,
        public float $overtimeSundayNight = 0.0,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public function plus(self $other): self
    {
        return new self(
            ordinary: $this->ordinary + $other->ordinary,
            nightSurcharge: $this->nightSurcharge + $other->nightSurcharge,
            sundaySurcharge: $this->sundaySurcharge + $other->sundaySurcharge,
            nightSundaySurcharge: $this->nightSundaySurcharge + $other->nightSundaySurcharge,
            overtimeDay: $this->overtimeDay + $other->overtimeDay,
            overtimeNight: $this->overtimeNight + $other->overtimeNight,
            overtimeSundayDay: $this->overtimeSundayDay + $other->overtimeSundayDay,
            overtimeSundayNight: $this->overtimeSundayNight + $other->overtimeSundayNight,
        );
    }

    /** Todo lo que se trabajó, pagado aparte o no. */
    public function workedHours(): float
    {
        return $this->ordinary
            + $this->nightSurcharge
            + $this->sundaySurcharge
            + $this->nightSundaySurcharge
            + $this->overtimeHours();
    }

    /** Solo lo que pasó del tope de la jornada ordinaria. Es lo que miran los topes legales. */
    public function overtimeHours(): float
    {
        return $this->overtimeDay
            + $this->overtimeNight
            + $this->overtimeSundayDay
            + $this->overtimeSundayNight;
    }

    /**
     * Cada bolsa con el parámetro que la valora. El que liquida no tiene que recordar
     * qué factor le toca a cada una.
     *
     * @return array<string, array{hours: float, parameter: PayrollParameter}>
     */
    public function paidBuckets(): array
    {
        return [
            'night_surcharge' => ['hours' => $this->nightSurcharge, 'parameter' => PayrollParameter::SurchargeNight],
            'sunday_surcharge' => ['hours' => $this->sundaySurcharge, 'parameter' => PayrollParameter::SurchargeSunday],
            'night_sunday_surcharge' => ['hours' => $this->nightSundaySurcharge, 'parameter' => PayrollParameter::SurchargeNightSunday],
            'overtime_day' => ['hours' => $this->overtimeDay, 'parameter' => PayrollParameter::OvertimeDay],
            'overtime_night' => ['hours' => $this->overtimeNight, 'parameter' => PayrollParameter::OvertimeNight],
            'overtime_sunday_day' => ['hours' => $this->overtimeSundayDay, 'parameter' => PayrollParameter::OvertimeSundayDay],
            'overtime_sunday_night' => ['hours' => $this->overtimeSundayNight, 'parameter' => PayrollParameter::OvertimeSundayNight],
        ];
    }

    /** @return array<string, float> */
    public function toArray(): array
    {
        return [
            'ordinary_hours' => round($this->ordinary, 4),
            'night_surcharge_hours' => round($this->nightSurcharge, 4),
            'sunday_surcharge_hours' => round($this->sundaySurcharge, 4),
            'night_sunday_surcharge_hours' => round($this->nightSundaySurcharge, 4),
            'overtime_day_hours' => round($this->overtimeDay, 4),
            'overtime_night_hours' => round($this->overtimeNight, 4),
            'overtime_sunday_day_hours' => round($this->overtimeSundayDay, 4),
            'overtime_sunday_night_hours' => round($this->overtimeSundayNight, 4),
        ];
    }
}
