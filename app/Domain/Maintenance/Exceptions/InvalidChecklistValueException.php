<?php

namespace App\Domain\Maintenance\Exceptions;

use App\Exceptions\BusinessRuleException;
use App\Models\WorkOrderChecklistResult;

/**
 * The value the técnico typed does not fit the item he was answering — a word
 * where a measurement was expected. A business rule, not a server error: it must
 * come back to the phone as a readable message.
 */
class InvalidChecklistValueException extends BusinessRuleException
{
    public static function expectedNumeric(WorkOrderChecklistResult $result): self
    {
        return new self(
            sprintf('El ítem «%s» requiere un valor numérico.', $result->label),
            detail: "checklist_result:{$result->id}",
        );
    }
}
