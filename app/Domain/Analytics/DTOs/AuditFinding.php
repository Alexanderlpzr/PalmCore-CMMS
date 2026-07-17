<?php

namespace App\Domain\Analytics\DTOs;

use App\Domain\Analytics\Enums\AuditSeverity;

/**
 * Un hallazgo de la auditoría de integridad de datos: qué se encontró mal,
 * cuántos casos, y una muestra de los registros afectados para poder ir a
 * arreglarlos sin adivinar.
 */
readonly class AuditFinding
{
    /**
     * @param  list<string>  $sample  etiquetas de los primeros registros afectados
     */
    public function __construct(
        public string $key,
        public AuditSeverity $severity,
        public string $title,
        public string $description,
        public int $count,
        public array $sample = [],
    ) {}
}
