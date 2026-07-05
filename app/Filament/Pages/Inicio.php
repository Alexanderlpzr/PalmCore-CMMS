<?php

namespace App\Filament\Pages;

use App\Domain\Home\Services\HomePageService;
use App\Models\Role;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Tenant entry portal — a corporate landing page (carousel, notices, news,
 * quick actions, recent activity), deliberately separate from the analytics
 * Dashboard. All data is sourced from HomePageService so this page stays thin.
 */
class Inicio extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Inicio';

    protected static ?string $title = 'Inicio';

    protected static ?int $navigationSort = -100;

    /**
     * Inicio owns the panel root (`/admin/{tenant}`), so it is where users land
     * after login and tenant resolution — the analytics Dashboard is relocated
     * to `/dashboard` (see App\Filament\Pages\Dashboard).
     */
    protected static string $routePath = '/';

    protected string $view = 'filament.pages.inicio';

    public static function getRoutePath(Panel $panel): string
    {
        return static::$routePath;
    }

    /**
     * Expose the portal snapshot as a plain view variable ($home) rather than a
     * public Livewire property: HomePageData is a custom DTO and Livewire only
     * serializes primitives/arrays/models. Passing it through getViewData()
     * keeps the page free of Livewire hydration of unsupported types. The
     * underlying sections are cached in the service, so rebuilding per render
     * is cheap.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();
        $service = new HomePageService($tenant->id);

        // Read-only calls so the HERO copy can reflect the live state — the
        // service itself is untouched (results are cached).
        $attention = $service->attentionRequired($tenant->slug);
        $status = $service->heroStatus($attention);

        return [
            'home' => $service->snapshot(
                $tenant->slug,
                $this->buildHero($user, $tenant, $attention, $status),
                $user?->id,
            ),
        ];
    }

    /**
     * Assemble the per-user HERO greeting context. Kept on the page (not the
     * cached service) because it is user-specific. Produces a written-for-you
     * greeting: first name, human-readable role, an elegant date, and a natural
     * status sentence chosen from a small catalogue (so it never feels robotic).
     *
     * @param  array<int, array<string, mixed>>  $attention
     * @param  array{message: string, tone: string}  $status
     * @return array<string, mixed>
     */
    private function buildHero(mixed $user, mixed $tenant, array $attention, array $status): array
    {
        $hour = (int) now()->format('H');

        $greeting = match (true) {
            $hour < 12 => 'Buenos días',
            $hour < 19 => 'Buenas tardes',
            default => 'Buenas noches',
        };

        $firstName = Str::of((string) ($user?->name ?? ''))->trim()->before(' ')->value() ?: null;

        return [
            'greeting' => $greeting,
            'name' => $firstName,
            'role' => $this->humanizeRole($user?->getRoleNames()->first()),
            'company' => $tenant->name,
            'date_human' => now()->translatedFormat('l, d \d\e F \d\e Y'),
            'iso_time' => now()->toIso8601String(),
            'headline' => $this->heroHeadline($status['tone'], $attention),
            'tone' => $status['tone'],
        ];
    }

    /**
     * Turn a permission-role slug into a human label, generically.
     * "administrador-general" → "Administrador General"; "maintenance-manager"
     * → "Maintenance Manager". Falls back to a sensible default.
     */
    private function humanizeRole(?string $slug): string
    {
        if (blank($slug)) {
            return 'Administrador';
        }

        return Role::humanizeName($slug);
    }

    /**
     * Pick a natural status sentence from a small catalogue, varying by state so
     * the HERO never repeats the exact same phrase. The counts come from the
     * already-computed attention cards.
     *
     * @param  array<int, array<string, mixed>>  $attention
     */
    private function heroHeadline(string $tone, array $attention): string
    {
        $byKey = collect($attention)->keyBy('key');

        $catalogue = [
            'brand' => [
                'La operación se encuentra estable. No hay incidentes críticos registrados.',
                'Todo en orden por ahora — nada requiere tu atención.',
                'Sin novedades críticas: la planta opera con normalidad.',
            ],
            'warning' => [
                'Hoy tienes {n} {items} que requieren tu atención.',
                'Hay {n} {items} esperando tu revisión.',
                '{n} {items} necesitan que les eches un vistazo.',
            ],
            'danger' => [
                '{n} {alerts} {require} tu acción inmediata.',
                'Atención: {n} {alerts} {require} intervención ahora.',
            ],
        ];

        $sentence = Arr::random($catalogue[$tone] ?? $catalogue['brand']);

        if ($tone === 'warning') {
            $count = (int) ($byKey['overdue_work_orders']['count'] ?? 0)
                + (int) ($byKey['pending_requests']['count'] ?? 0);

            return strtr($sentence, [
                '{n}' => $count,
                '{items}' => $count === 1 ? 'asunto' : 'asuntos',
            ]);
        }

        if ($tone === 'danger') {
            $count = (int) ($byKey['critical_alerts']['count'] ?? 0);

            return strtr($sentence, [
                '{n}' => $count,
                '{alerts}' => $count === 1 ? 'alerta crítica' : 'alertas críticas',
                '{require}' => $count === 1 ? 'requiere' : 'requieren',
            ]);
        }

        return $sentence;
    }

    /** Hide the default page heading — the HERO is the visual header. */
    public function getHeading(): string
    {
        return '';
    }

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }
}
