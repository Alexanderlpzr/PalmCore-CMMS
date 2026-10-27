<?php

namespace App\Models;

use Database\Factories\R100LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un interesado que dejó su correo en la calculadora R-100 y autorizó guardarlo.
 * Es de la plataforma, no de una empresa: no lleva tenant.
 */
class R100Lead extends Model
{
    /** @use HasFactory<R100LeadFactory> */
    use HasFactory;

    protected $fillable = ['email', 'consented_at'];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
        ];
    }
}
