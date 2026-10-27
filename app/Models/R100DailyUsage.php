<?php

namespace App\Models;

use Database\Factories\R100DailyUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cuántas veces se abrió la calculadora R-100 y cuántos viajes se calcularon en
 * un día. Es de la plataforma, no de una empresa: no lleva tenant.
 */
class R100DailyUsage extends Model
{
    /** @use HasFactory<R100DailyUsageFactory> */
    use HasFactory;

    protected $fillable = ['date', 'visits', 'calculations'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'visits' => 'integer',
            'calculations' => 'integer',
        ];
    }
}
