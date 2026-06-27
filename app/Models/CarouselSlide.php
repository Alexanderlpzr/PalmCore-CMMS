<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\CarouselSlideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'tenant_id',
    'title',
    'subtitle',
    'description',
    'image_path',
    'button_label',
    'button_url',
    'sort_order',
    'is_active',
    'starts_at',
    'ends_at',
])]
class CarouselSlide extends BaseModel
{
    /** @use HasFactory<CarouselSlideFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk(persistent_disk())->url($this->image_path);
    }
}
