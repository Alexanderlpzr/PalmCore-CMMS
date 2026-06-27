<?php

namespace App\Models;

use App\Domain\Home\Enums\InstitutionalContentType;
use Database\Factories\InstitutionalContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'created_by',
    'title',
    'subtitle',
    'description',
    'image_path',
    'button_text',
    'button_url',
    'type',
    'display_order',
    'is_active',
    'is_global',
    'starts_at',
    'ends_at',
])]
class InstitutionalContent extends Model
{
    /** @use HasFactory<InstitutionalContentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'institutional_content_tenant');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeVisibleForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(function (Builder $q) use ($tenantId): void {
                $q->where('is_global', true)
                    ->orWhereHas('tenants', fn ($r) => $r->where('tenants.id', $tenantId));
            });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk(persistent_disk())->url($this->image_path);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'type' => InstitutionalContentType::class,
            'is_active' => 'boolean',
            'is_global' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
