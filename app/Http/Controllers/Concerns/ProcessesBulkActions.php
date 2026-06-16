<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait ProcessesBulkActions
{
    /**
     * Apply $handler to each id independently. Each item is atomic (the service
     * it calls wraps its own write); a failure is recorded and the batch keeps
     * going so one bad record never aborts the whole lote (partial success).
     *
     * @param  array<int, string>  $ids
     * @param  callable(string): mixed  $resolve  Loads the tenant-scoped model or throws.
     * @param  callable(mixed): void  $handler  Performs the authorized action or throws.
     * @return array{succeeded: int, failed: array<int, array{id: string, error: string}>}
     */
    protected function runBulk(array $ids, callable $resolve, callable $handler): array
    {
        $succeeded = 0;
        $failed = [];

        foreach (array_values(array_unique($ids)) as $id) {
            try {
                $handler($resolve($id));
                $succeeded++;
            } catch (\Throwable $e) {
                $failed[] = ['id' => $id, 'error' => $this->bulkError($e)];
            }
        }

        return ['succeeded' => $succeeded, 'failed' => $failed];
    }

    private function bulkError(\Throwable $e): string
    {
        return match (true) {
            $e instanceof ModelNotFoundException => 'No encontrado en este tenant',
            $e instanceof AuthorizationException => 'No autorizado',
            default => $e->getMessage(),
        };
    }
}
