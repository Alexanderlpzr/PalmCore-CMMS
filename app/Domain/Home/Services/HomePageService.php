<?php

namespace App\Domain\Home\Services;

use App\Domain\Home\Enums\AnnouncementCategory;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\CarouselSlide;
use App\Models\EquipmentIssueReport;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\WorkOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregates every data source the tenant "Inicio" portal renders.
 *
 * The page (App\Filament\Pages\Inicio) stays thin: it asks this service for
 * already-shaped, view-ready arrays. Each section is cached per tenant for a
 * short TTL so the landing page never hammers the database, and new sections
 * can be added here without touching the page or the Blade view wiring.
 */
class HomePageService
{
    /** Cache lifetime for every section, in seconds. */
    private const CACHE_TTL = 300;

    public function __construct(private readonly string $tenantId) {}

    /**
     * Full-width institutional carousel slides.
     *
     * @return array<int, array{id: string, title: ?string, subtitle: ?string, description: ?string, image_url: ?string, button_label: ?string, button_url: ?string}>
     */
    public function carouselSlides(): array
    {
        return $this->remember('carousel', function (): array {
            return CarouselSlide::query()
                ->visible()
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
                ->map(fn (CarouselSlide $slide): array => [
                    'id' => $slide->id,
                    'title' => $slide->title,
                    'subtitle' => $slide->subtitle,
                    'description' => $slide->description,
                    'image_url' => $slide->imageUrl(),
                    'button_label' => $slide->button_label,
                    'button_url' => $slide->button_url,
                ])
                ->all();
        });
    }

    /**
     * Pinned / important announcements rendered as highlighted cards.
     *
     * @return array<int, array<string, mixed>>
     */
    public function importantNotices(): array
    {
        return $this->remember('notices', function (): array {
            return Announcement::query()
                ->published()
                ->where('is_pinned', true)
                ->orderBy('sort_order')
                ->orderByDesc('published_at')
                ->limit(4)
                ->get()
                ->map(fn (Announcement $a): array => $this->mapAnnouncement($a))
                ->all();
        });
    }

    /**
     * News & communications feed (everything that is not pinned).
     *
     * @return array<int, array<string, mixed>>
     */
    public function newsAndCommunications(): array
    {
        return $this->remember('news', function (): array {
            return Announcement::query()
                ->published()
                ->where('is_pinned', false)
                ->whereIn('category', [
                    AnnouncementCategory::News->value,
                    AnnouncementCategory::Communication->value,
                ])
                ->orderByDesc('published_at')
                ->limit(6)
                ->get()
                ->map(fn (Announcement $a): array => $this->mapAnnouncement($a))
                ->all();
        });
    }

    /**
     * "Atención requerida" — the cards that answer "¿qué debo resolver hoy?".
     * Each is a single COUNT query, scoped to the tenant, with a destination
     * route and a Design-System tone (no new colors). Cached per tenant.
     *
     * Tones map to urgency using only the 5 DS roles:
     *   danger (red) · warning (amber) · info (blue) · brand (emerald).
     *
     * @return array<int, array{key: string, count: int, label: string, hint: string, icon: string, route: string, tone: string}>
     */
    public function attentionRequired(string $tenantSlug): array
    {
        return $this->remember('attention', function () use ($tenantSlug): array {
            $overdueWorkOrders = WorkOrder::query()
                ->whereIn('status', ['planned', 'in_progress', 'on_hold'])
                ->whereNotNull('planned_end_at')
                ->where('planned_end_at', '<', now())
                ->count();

            $pendingRequests = MaintenanceRequest::query()
                ->whereIn('status', ['submitted', 'under_review'])
                ->count();

            // MaintenanceSchedule does NOT extend BaseModel (no TenantScope),
            // so it must be scoped to the tenant explicitly.
            $upcomingPreventives = MaintenanceSchedule::query()
                ->where('tenant_id', $this->tenantId)
                ->whereNotNull('next_due_at')
                ->whereBetween('next_due_at', [now(), now()->addDays(7)])
                ->count();

            $criticalAlerts = Alert::query()
                ->where('severity', 'critical')
                ->where('status', 'open')
                ->count();

            $pendingIssueReports = EquipmentIssueReport::query()->open()->count();

            return [
                [
                    'key' => 'overdue_work_orders',
                    'count' => $overdueWorkOrders,
                    'label' => 'OT vencidas',
                    'hint' => 'Órdenes de trabajo fuera de plazo',
                    'icon' => 'heroicon-o-clock',
                    'route' => "/admin/{$tenantSlug}/maintenance/work-order/work-orders",
                    'tone' => 'warning',
                ],
                [
                    'key' => 'pending_requests',
                    'count' => $pendingRequests,
                    'label' => 'Solicitudes pendientes',
                    'hint' => 'Esperan revisión o aprobación',
                    'icon' => 'heroicon-o-inbox-arrow-down',
                    'route' => "/admin/{$tenantSlug}/maintenance/maintenance-request/maintenance-requests",
                    'tone' => 'info',
                ],
                [
                    'key' => 'upcoming_preventives',
                    'count' => $upcomingPreventives,
                    'label' => 'Preventivos próximos',
                    'hint' => 'Programados en los próximos 7 días',
                    'icon' => 'heroicon-o-calendar-days',
                    'route' => "/admin/{$tenantSlug}/maintenance/maintenance-plan/maintenance-plans",
                    'tone' => 'brand',
                ],
                [
                    'key' => 'critical_alerts',
                    'count' => $criticalAlerts,
                    'label' => 'Alertas críticas',
                    'hint' => 'Requieren atención inmediata',
                    'icon' => 'heroicon-o-bell-alert',
                    'route' => "/admin/{$tenantSlug}/alerts",
                    'tone' => 'danger',
                ],
                [
                    'key' => 'pending_issue_reports',
                    'count' => $pendingIssueReports,
                    'label' => 'Reportes de novedad pendientes',
                    'hint' => 'Reportes de falla sin atender',
                    'icon' => 'heroicon-o-exclamation-triangle',
                    'route' => "/admin/{$tenantSlug}/maintenance/issue-report/issue-reports",
                    'tone' => 'danger',
                ],
            ];
        });
    }

    /**
     * Derive the HERO status line from the live attention counts — a calm,
     * human sentence, not a KPI. Returns the message plus a DS tone for the dot.
     *
     * @param  array<int, array<string, mixed>>  $attention
     * @return array{message: string, tone: string}
     */
    public function heroStatus(array $attention): array
    {
        $byKey = collect($attention)->keyBy('key');
        $critical = (int) ($byKey['critical_alerts']['count'] ?? 0);
        $overdue = (int) ($byKey['overdue_work_orders']['count'] ?? 0);
        $pending = (int) ($byKey['pending_requests']['count'] ?? 0);

        if ($critical > 0) {
            return [
                'message' => $critical === 1
                    ? 'Hay 1 alerta crítica que requiere atención inmediata.'
                    : "Hay {$critical} alertas críticas que requieren atención inmediata.",
                'tone' => 'danger',
            ];
        }

        $tasks = $overdue + $pending;

        if ($tasks > 0) {
            return [
                'message' => $tasks === 1
                    ? 'Hay 1 tarea que requiere tu atención.'
                    : "Hay {$tasks} tareas que requieren tu atención.",
                'tone' => 'warning',
            ];
        }

        return [
            'message' => 'La operación se encuentra estable. No hay incidentes críticos.',
            'tone' => 'brand',
        ];
    }

    /**
     * Large icon tiles for the most common tenant workflows. Static (no DB).
     * Surfaces stay neutral; only the icon chip carries a restrained tone so
     * the grid reads calm and premium rather than multicolor.
     *
     * @return array<int, array{label: string, description: string, icon: string, route: string, tone: string}>
     */
    public function quickActions(string $tenantSlug): array
    {
        return [
            [
                'label' => 'Nueva OT',
                'description' => 'Crea una orden de trabajo correctiva o preventiva.',
                'icon' => 'heroicon-o-clipboard-document-list',
                'route' => "/admin/{$tenantSlug}/maintenance/work-order/work-orders/create",
                'tone' => 'brand',
            ],
            [
                'label' => 'Nueva solicitud',
                'description' => 'Registra una solicitud de mantenimiento.',
                'icon' => 'heroicon-o-inbox-arrow-down',
                'route' => "/admin/{$tenantSlug}/maintenance/maintenance-request/maintenance-requests/create",
                'tone' => 'info',
            ],
            [
                'label' => 'Nuevo equipo',
                'description' => 'Da de alta un activo en el inventario.',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'route' => "/admin/{$tenantSlug}/equipment/create",
                'tone' => 'brand',
            ],
            [
                'label' => 'Escanear QR',
                'description' => 'Identifica un equipo desde su código.',
                'icon' => 'heroicon-o-qr-code',
                'route' => "/admin/{$tenantSlug}/equipment",
                'tone' => 'neutral',
            ],
            [
                'label' => 'Reportar falla',
                'description' => 'Notifica una incidencia en planta.',
                'icon' => 'heroicon-o-exclamation-triangle',
                'route' => "/admin/{$tenantSlug}/maintenance/issue-report/issue-reports",
                'tone' => 'danger',
            ],
            [
                'label' => 'Dashboard',
                'description' => 'MTTR, MTBF, disponibilidad y gráficas.',
                'icon' => 'heroicon-o-chart-bar',
                'route' => "/admin/{$tenantSlug}/dashboard",
                'tone' => 'info',
            ],
        ];
    }

    /**
     * Chronological cross-domain activity (work orders, requests, alerts),
     * merged newest-first for the timeline. Each entry carries the actor, a
     * human action verb, the entity, an icon, a discreet DS tone and the time.
     *
     * @return array<int, array{type: string, icon: string, tone: string, actor: string, action: string, title: string, meta: ?string, iso: string, at_human: string, time_human: string}>
     */
    public function recentActivity(int $limit = 12): array
    {
        return $this->remember('activity', function () use ($limit): array {
            $workOrders = WorkOrder::query()
                ->with(['equipment:id,name', 'createdBy:id,name'])
                ->latest('created_at')
                ->limit($limit)
                ->get()
                ->map(fn (WorkOrder $wo): array => [
                    'type' => 'work_order',
                    'icon' => 'heroicon-o-clipboard-document-list',
                    'tone' => 'brand',
                    'actor' => $wo->createdBy?->name ?? 'Sistema',
                    'action' => 'creó la orden',
                    'title' => trim(($wo->work_order_number ? "{$wo->work_order_number} · " : '').$wo->title),
                    'meta' => $wo->equipment?->name,
                    'at' => $wo->created_at,
                ]);

            $requests = MaintenanceRequest::query()
                ->with(['equipment:id,name', 'createdBy:id,name'])
                ->latest('created_at')
                ->limit($limit)
                ->get()
                ->map(fn (MaintenanceRequest $mr): array => [
                    'type' => 'request',
                    'icon' => 'heroicon-o-inbox-arrow-down',
                    'tone' => 'info',
                    'actor' => $mr->createdBy?->name ?? 'Sistema',
                    'action' => 'registró la solicitud',
                    'title' => trim(($mr->request_number ? "{$mr->request_number} · " : '').$mr->title),
                    'meta' => $mr->equipment?->name,
                    'at' => $mr->created_at,
                ]);

            $alerts = Alert::query()
                ->latest('created_at')
                ->limit($limit)
                ->get()
                ->map(fn (Alert $alert): array => [
                    'type' => 'alert',
                    'icon' => 'heroicon-o-bell-alert',
                    'tone' => 'warning',
                    'actor' => 'Sistema',
                    'action' => 'generó una alerta',
                    'title' => $alert->title,
                    'meta' => $alert->severity?->label() ?? null,
                    'at' => $alert->created_at,
                ]);

            return $workOrders
                ->concat($requests)
                ->concat($alerts)
                ->filter(fn (array $item): bool => $item['at'] instanceof CarbonInterface)
                ->sortByDesc(fn (array $item): CarbonInterface => $item['at'])
                ->take($limit)
                ->map(function (array $item): array {
                    // Flatten Carbon to strings BEFORE it ever enters the cache or
                    // the Livewire public-property graph (CarbonImmutable does not
                    // survive serialize()/hydrate() — it returns as an incomplete
                    // object). The view consumes only these strings.
                    $item['iso'] = $item['at']->toIso8601String();
                    $item['at_human'] = $item['at']->diffForHumans();
                    $item['time_human'] = $item['at']->translatedFormat('d M · H:i');
                    unset($item['at']);

                    return $item;
                })
                ->values()
                ->all();
        });
    }

    /**
     * Shape an Announcement into the card payload the view expects.
     *
     * @return array<string, mixed>
     */
    private function mapAnnouncement(Announcement $a): array
    {
        return [
            'id' => $a->id,
            'title' => $a->title,
            'subtitle' => $a->subtitle,
            'summary' => $a->subtitle ?: str(strip_tags((string) $a->body))->limit(160)->value(),
            'category' => $a->category?->value,
            'category_label' => $a->category?->label(),
            'category_color' => $a->category?->color() ?? 'gray',
            'image_url' => $a->imageUrl(),
            // Every news card gets a CTA: the custom label/URL when present,
            // otherwise "Leer más" pointing at the announcement detail page.
            'button_label' => $a->button_label ?: 'Leer más',
            'button_url' => $a->button_url,
            // String only — no Carbon in the cache / Livewire property graph.
            'published_human' => $a->published_at?->translatedFormat('d M Y'),
        ];
    }

    /**
     * Cache a section payload under a tenant-scoped key.
     *
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    private function remember(string $section, callable $callback): mixed
    {
        return Cache::remember(
            "home:{$this->tenantId}:{$section}",
            self::CACHE_TTL,
            $callback,
        );
    }

    /**
     * Invalidate every cached section for this tenant. Call after content edits.
     */
    public function flush(): void
    {
        foreach (['carousel', 'notices', 'news', 'activity', 'attention'] as $section) {
            Cache::forget("home:{$this->tenantId}:{$section}");
        }
    }

    /**
     * Collect every section the view needs in one shot.
     *
     * @param  array<string, mixed>  $hero  User/tenant greeting context built by the page.
     */
    public function snapshot(string $tenantSlug, array $hero = []): HomePageData
    {
        $attention = $this->attentionRequired($tenantSlug);
        $hero['status'] = $this->heroStatus($attention);

        return new HomePageData(
            hero: $hero,
            attentionItems: $attention,
            quickActions: $this->quickActions($tenantSlug),
            carouselSlides: $this->carouselSlides(),
            importantNotices: $this->importantNotices(),
            newsAndCommunications: $this->newsAndCommunications(),
            recentActivity: $this->recentActivity(),
        );
    }
}
