<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait SortsApiQueries
{
    /**
     * Apply a whitelisted, deterministic ordering to an API list query.
     *
     * Only columns present in $allowed can be sorted by the client; anything
     * else falls back to the resource default. A final ordering by `id` is
     * always appended so cursor pagination stays stable when the sort column
     * is non-unique.
     *
     * @param  array<int, string>  $allowed
     */
    protected function applySort(
        Builder $query,
        Request $request,
        array $allowed,
        string $defaultColumn,
        string $defaultDirection = 'asc',
    ): void {
        $direction = strtolower((string) $request->query('direction')) === 'desc' ? 'desc' : 'asc';
        $sort = $request->query('sort');

        if (is_string($sort) && in_array($sort, $allowed, true)) {
            $query->orderBy($sort, $request->filled('direction') ? $direction : 'asc');
        } else {
            $query->orderBy($defaultColumn, $defaultDirection);
        }

        $query->orderBy('id');
    }

    /**
     * Normalize a status filter into a list, accepting a single value or a
     * comma-separated set (e.g. "planned,in_progress") for multi-state filters.
     *
     * @return array<int, string>
     */
    protected function statusList(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
