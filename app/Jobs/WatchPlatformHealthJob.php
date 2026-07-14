<?php

namespace App\Jobs;

use App\Domain\Platform\Services\HealthWatchService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Mira la salud de la plataforma cada hora y avisa cuando algo cambia a mal.
 *
 * Va a la cola `default` a propósito: es la que Horizon atiende siempre. Un vigilante
 * encolado en una cola sin worker sería una broma cruel.
 */
class WatchPlatformHealthJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function uniqueFor(): int
    {
        return 900;
    }

    public function handle(HealthWatchService $watch): void
    {
        $watch->run();
    }
}
