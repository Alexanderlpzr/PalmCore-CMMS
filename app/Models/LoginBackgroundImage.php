<?php

namespace App\Models;

use Database\Factories\LoginBackgroundImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Una foto del carrusel de login. Sin tenant: el login ocurre antes de que exista un
 * contexto de empresa, así que esto es de la plataforma entera, no de una empresa.
 */
#[Fillable([
    'image_path',
    'caption',
    'sort_order',
    'is_active',
])]
class LoginBackgroundImage extends Model
{
    /** @use HasFactory<LoginBackgroundImageFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk(persistent_disk())->url($this->image_path);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
