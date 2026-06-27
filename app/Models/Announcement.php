<?php

namespace App\Models;

use App\Domain\Home\Enums\AnnouncementCategory;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'tenant_id',
    'created_by',
    'title',
    'subtitle',
    'body',
    'category',
    'image_path',
    'button_label',
    'button_url',
    'is_active',
    'is_pinned',
    'sort_order',
    'published_at',
    'expires_at',
])]
class Announcement extends BaseModel
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => AnnouncementCategory::class,
            'is_active' => 'boolean',
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('published_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk(persistent_disk())->url($this->image_path);
    }
}
