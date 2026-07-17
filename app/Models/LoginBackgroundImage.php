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

        $disk = persistent_disk();
        $url = Storage::disk($disk)->url($this->image_path);

        // El disco local sirve en APP_URL/storage (p. ej. https://www.fronda.app/...).
        // El login es la única página que se visita indistintamente por www y por el
        // dominio pelado —Google enlaza uno, el usuario teclea el otro— y un URL
        // absoluto a un host distinto del que se está viendo lo bloquea la CSP
        // `img-src 'self'`: la imagen no carga y sale el ícono roto. Devolver la ruta
        // relativa a la raíz la hace cargar siempre desde el mismo host de la visita.
        // Un disco remoto (R2) trae su propio host y se deja intacto.
        if ($disk === 'public') {
            return parse_url($url, PHP_URL_PATH) ?: $url;
        }

        return $url;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
