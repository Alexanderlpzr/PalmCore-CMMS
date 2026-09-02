<?php

namespace App\Domain\HumanResources\Enums;

/**
 * Los ocho motivos por los que un día del mes no fue un día trabajado.
 *
 * Salen de auditar las columnas K a AA del libro de la extractora, una por una. Cada uno
 * responde tres preguntas distintas y no siempre igual, que es justamente por qué esto es
 * un enum con métodos y no una lista de etiquetas:
 *
 *   1. ¿Cuánto vale el día? (el salario propio, el mínimo, o nada)
 *   2. ¿Entra al IBC de pensión aunque no se pague?
 *   3. ¿Entra a la base de prima y cesantías? ¿Y a la de vacaciones?
 *
 * Las respuestas están tomadas literalmente de las fórmulas del libro y se citan por
 * columna, para que dentro de un año se pueda comprobar de dónde salió cada una. Son
 * reglas del Código Sustantivo del Trabajo, no política de la empresa: por eso viven aquí
 * y no en la matriz configurable de `hr_payroll_concepts`, que gobierna los conceptos que
 * sí cambian por convención —bonificaciones, auxilio, recargos—.
 */
enum NoveltyType: string
{
    case AusenciaNoJustificada = 'ausencia_no_justificada';
    case PermisoAutorizado = 'permiso_autorizado';
    case PermisoNoRemunerado = 'permiso_no_remunerado';
    case IncapacidadEgSalario = 'incapacidad_eg_salario';
    case IncapacidadEgMinimo = 'incapacidad_eg_minimo';
    case IncapacidadAt = 'incapacidad_at';
    case CalamidadDomestica = 'calamidad_domestica';
    case Vacaciones = 'vacaciones';

    public function label(): string
    {
        return match ($this) {
            self::AusenciaNoJustificada => 'Ausencia no justificada o suspensión',
            self::PermisoAutorizado => 'Permiso autorizado',
            self::PermisoNoRemunerado => 'Permiso no remunerado',
            self::IncapacidadEgSalario => 'Incapacidad por enfermedad general (salario)',
            self::IncapacidadEgMinimo => 'Incapacidad por enfermedad general (mínimo)',
            self::IncapacidadAt => 'Incapacidad por accidente de trabajo',
            self::CalamidadDomestica => 'Calamidad doméstica',
            self::Vacaciones => 'Vacaciones',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AusenciaNoJustificada, self::PermisoNoRemunerado => 'danger',
            self::IncapacidadEgSalario, self::IncapacidadEgMinimo, self::IncapacidadAt => 'warning',
            self::Vacaciones => 'info',
            default => 'gray',
        };
    }

    /**
     * Cuánto vale cada día de esta novedad.
     *
     * `Smlmv` es el piso legal de la incapacidad por enfermedad general: cuando el
     * porcentaje que reconoce la EPS queda por debajo del salario mínimo, se paga el
     * mínimo. En el libro son las columnas P y R.
     */
    public function dayValueBasis(): NoveltyDayBasis
    {
        return match ($this) {
            self::AusenciaNoJustificada, self::PermisoNoRemunerado => NoveltyDayBasis::Unpaid,
            self::IncapacidadEgMinimo => NoveltyDayBasis::Smlmv,
            default => NoveltyDayBasis::OwnSalary,
        };
    }

    public function isPaid(): bool
    {
        return $this->dayValueBasis() !== NoveltyDayBasis::Unpaid;
    }

    /**
     * ¿Suma al IBC de pensión aunque el día no se pague?
     *
     * Columna BM: `IBC salud + valor de la ausencia + días de permiso no remunerado por
     * el valor día`. Son justamente los dos días que no se pagan: la cotización a pensión
     * no se interrumpe porque alguien faltó.
     */
    public function countsIbcPension(): bool
    {
        return in_array($this, [self::AusenciaNoJustificada, self::PermisoNoRemunerado], true);
    }

    /**
     * ¿Suma a la base de prima de servicios y cesantías?
     *
     * Columna BN, que suma los días laborados, la incapacidad de enfermedad general al
     * salario propio, la de accidente de trabajo, la calamidad y las vacaciones. Deja
     * fuera las ausencias, los permisos y la incapacidad liquidada al mínimo.
     */
    public function countsSeveranceBase(): bool
    {
        return in_array($this, [
            self::IncapacidadEgSalario,
            self::IncapacidadAt,
            self::CalamidadDomestica,
            self::Vacaciones,
        ], true);
    }

    /**
     * ¿Suma a la base de vacaciones?
     *
     * Columna BO, más estrecha que la de prima: días laborados, incapacidad de enfermedad
     * general al salario propio, incapacidad por accidente de trabajo y calamidad. Las
     * vacaciones no generan vacaciones, que es lo que uno esperaría y conviene comprobar.
     */
    public function countsVacationBase(): bool
    {
        return in_array($this, [
            self::IncapacidadEgSalario,
            self::IncapacidadAt,
            self::CalamidadDomestica,
        ], true);
    }

    /**
     * ¿Su valor entra al devengado del mes?
     *
     * Las vacaciones son la excepción, y sale del libro: allí se calculan (columna AA) y
     * se cuentan en el total de días, pero **no** entran en el básico devengado ni en el
     * neto a pagar. Es la práctica habitual —las vacaciones se desembolsan aparte, antes
     * de que la persona salga— pero conviene tenerlo presente: quien tomó doce días de
     * vacaciones cobra dieciocho días en esta nómina.
     */
    public function countsMonthlyEarnings(): bool
    {
        return $this->isPaid() && $this !== self::Vacaciones;
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
