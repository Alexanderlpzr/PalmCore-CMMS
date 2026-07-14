<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Un trabajo que la cola intentó y no pudo.
 *
 * Existe como modelo solo para poder mirarlo a la cara desde el panel: la tabla
 * `failed_jobs` la escribe Laravel, no nosotros, y nada aquí la modifica. Sin esta
 * ventana, un job fallido es una fila que nadie va a leer nunca.
 */
class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    /** El nombre de la clase del job, sin el payload alrededor. */
    public function jobName(): string
    {
        $payload = json_decode($this->payload ?? '{}', true);

        return $payload['displayName'] ?? ($payload['job'] ?? 'Desconocido');
    }

    /** La primera línea de la excepción: el «qué pasó» sin las 200 líneas de traza. */
    public function reason(): string
    {
        return Str::limit(strtok((string) $this->exception, "\n"), 160);
    }

    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }
}
