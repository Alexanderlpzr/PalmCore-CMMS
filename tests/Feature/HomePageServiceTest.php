<?php

use App\Domain\Home\Enums\AnnouncementCategory;
use App\Domain\Home\Services\HomePageService;
use App\Domain\Maintenance\Enums\IssueReportStatus;
use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\CarouselSlide;
use App\Models\EquipmentIssueReport;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

function homePageTenant(): Tenant
{
    $tenant = Tenant::factory()->create();
    CurrentTenant::set($tenant);

    return $tenant;
}

it('carouselSlides returns only visible slides shaped for the view', function () {
    $tenant = homePageTenant();

    CarouselSlide::factory()->for($tenant)->create([
        'title' => 'Bienvenidos', 'subtitle' => 'Sub', 'is_active' => true,
        'button_label' => 'Ver', 'button_url' => 'https://x.test', 'sort_order' => 1,
        'starts_at' => null, 'ends_at' => null,
    ]);
    CarouselSlide::factory()->for($tenant)->create(['is_active' => false]);

    $slides = (new HomePageService($tenant->id))->carouselSlides();

    expect($slides)->toHaveCount(1)
        ->and($slides[0]['title'])->toBe('Bienvenidos')
        ->and($slides[0]['button_label'])->toBe('Ver')
        ->and($slides[0])->toHaveKeys(['id', 'title', 'subtitle', 'description', 'image_url', 'button_label', 'button_url']);
});

it('importantNotices returns only pinned published announcements', function () {
    $tenant = homePageTenant();

    Announcement::factory()->for($tenant)->create([
        'title' => 'Aviso fijado', 'is_pinned' => true, 'is_active' => true,
        'category' => AnnouncementCategory::Communication, 'published_at' => now()->subDay(),
    ]);
    Announcement::factory()->for($tenant)->create([
        'title' => 'No fijado', 'is_pinned' => false, 'is_active' => true,
        'published_at' => now()->subDay(),
    ]);

    $notices = (new HomePageService($tenant->id))->importantNotices();

    expect($notices)->toHaveCount(1)
        ->and($notices[0]['title'])->toBe('Aviso fijado')
        ->and($notices[0]['category_label'])->toBe('Comunicado');
});

it('newsAndCommunications returns non-pinned news and communications only', function () {
    $tenant = homePageTenant();

    Announcement::factory()->for($tenant)->create([
        'title' => 'Noticia', 'category' => AnnouncementCategory::News,
        'is_pinned' => false, 'is_active' => true, 'published_at' => now()->subDay(),
    ]);
    Announcement::factory()->for($tenant)->create([
        'title' => 'Capacitacion', 'category' => AnnouncementCategory::Training,
        'is_pinned' => false, 'is_active' => true, 'published_at' => now()->subDay(),
    ]);
    Announcement::factory()->for($tenant)->create([
        'title' => 'Fijada', 'category' => AnnouncementCategory::News,
        'is_pinned' => true, 'is_active' => true, 'published_at' => now()->subDay(),
    ]);

    $news = (new HomePageService($tenant->id))->newsAndCommunications();

    expect(collect($news)->pluck('title')->all())->toBe(['Noticia']);
});

it('quickActions builds six tenant-scoped tiles with DS tones (no violet)', function () {
    $tenant = homePageTenant();

    $actions = (new HomePageService($tenant->id))->quickActions('acme');

    expect($actions)->toHaveCount(6)
        ->and($actions[0])->toHaveKeys(['label', 'description', 'icon', 'route', 'tone'])
        ->and($actions[0]['route'])->toContain('/admin/acme/')
        ->and(collect($actions)->pluck('tone')->unique()->all())
        ->each->toBeIn(['brand', 'emerald', 'info', 'blue', 'warning', 'amber', 'danger', 'red', 'neutral', 'gray']);
});

it('attentionRequired returns five counted, routed, toned cards', function () {
    $tenant = homePageTenant();

    // Overdue WO (planned end in the past, still open)
    WorkOrder::factory()->for($tenant)->create([
        'status' => 'in_progress', 'planned_end_at' => now()->subDays(2),
    ]);
    // Critical open alert
    Alert::query()->forceCreate([
        'tenant_id' => $tenant->id, 'severity' => 'critical', 'category' => 'maintenance',
        'title' => 'Crítica', 'message' => 'x', 'entity_type' => 'equipment',
        'entity_id' => $tenant->id, 'status' => 'open',
    ]);
    // Open issue report
    EquipmentIssueReport::factory()->for($tenant)->create(['status' => IssueReportStatus::Open]);

    $items = (new HomePageService($tenant->id))->attentionRequired('acme');

    expect($items)->toHaveCount(5)
        ->and(collect($items)->keyBy('key')->get('overdue_work_orders')['count'])->toBe(1)
        ->and(collect($items)->keyBy('key')->get('critical_alerts')['count'])->toBe(1)
        ->and(collect($items)->keyBy('key')->get('pending_issue_reports')['count'])->toBe(1)
        ->and($items[0])->toHaveKeys(['key', 'count', 'label', 'hint', 'icon', 'route', 'tone']);
});

it('attentionRequired reflects a new work order without waiting for the cache TTL', function () {
    $tenant = homePageTenant();
    $service = new HomePageService($tenant->id);

    expect(collect($service->attentionRequired('acme'))->keyBy('key')->get('overdue_work_orders')['count'])->toBe(0);

    WorkOrder::factory()->for($tenant)->create([
        'status' => 'in_progress', 'planned_end_at' => now()->subDays(2),
    ]);

    expect(collect($service->attentionRequired('acme'))->keyBy('key')->get('overdue_work_orders')['count'])->toBe(1);
});

it('attentionRequired reflects a new issue report without waiting for the cache TTL', function () {
    $tenant = homePageTenant();
    $service = new HomePageService($tenant->id);

    expect(collect($service->attentionRequired('acme'))->keyBy('key')->get('pending_issue_reports')['count'])->toBe(0);

    EquipmentIssueReport::factory()->for($tenant)->create(['status' => IssueReportStatus::Open]);

    expect(collect($service->attentionRequired('acme'))->keyBy('key')->get('pending_issue_reports')['count'])->toBe(1);
});

it('heroStatus escalates from stable to attention to critical', function () {
    $tenant = homePageTenant();
    $service = new HomePageService($tenant->id);

    $stable = $service->heroStatus([
        ['key' => 'critical_alerts', 'count' => 0],
        ['key' => 'overdue_work_orders', 'count' => 0],
        ['key' => 'pending_requests', 'count' => 0],
    ]);
    expect($stable['tone'])->toBe('brand')->and($stable['message'])->toContain('estable');

    $attention = $service->heroStatus([
        ['key' => 'critical_alerts', 'count' => 0],
        ['key' => 'overdue_work_orders', 'count' => 2],
        ['key' => 'pending_requests', 'count' => 1],
    ]);
    expect($attention['tone'])->toBe('warning')->and($attention['message'])->toContain('3 tareas');

    $critical = $service->heroStatus([
        ['key' => 'critical_alerts', 'count' => 1],
        ['key' => 'overdue_work_orders', 'count' => 5],
        ['key' => 'pending_requests', 'count' => 0],
    ]);
    expect($critical['tone'])->toBe('danger')->and($critical['message'])->toContain('inmediata');
});

it('recentActivity merges work orders, requests and alerts newest-first with actor', function () {
    $tenant = homePageTenant();

    WorkOrder::factory()->for($tenant)->create(['title' => 'OT reciente', 'created_at' => now()->subMinutes(1)]);
    Alert::query()->forceCreate([
        'tenant_id' => $tenant->id, 'severity' => 'critical', 'category' => 'maintenance',
        'title' => 'Alerta media', 'message' => 'x', 'entity_type' => 'equipment',
        'entity_id' => $tenant->id, 'status' => 'open', 'created_at' => now()->subMinutes(5),
    ]);

    $activity = (new HomePageService($tenant->id))->recentActivity();

    expect($activity)->not->toBeEmpty()
        ->and($activity[0]['title'])->toContain('OT reciente')
        ->and($activity[0])->toHaveKeys(['type', 'icon', 'tone', 'actor', 'action', 'title', 'meta', 'iso', 'at_human', 'time_human'])
        ->and($activity[0])->not->toHaveKey('at'); // no Carbon survives into the Livewire/cache graph
});

it('isolates data per tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    CurrentTenant::set($tenantA);
    CarouselSlide::factory()->for($tenantA)->create(['title' => 'A', 'is_active' => true, 'starts_at' => null, 'ends_at' => null]);

    CurrentTenant::set($tenantB);
    CarouselSlide::factory()->for($tenantB)->create(['title' => 'B', 'is_active' => true, 'starts_at' => null, 'ends_at' => null]);

    $slidesB = (new HomePageService($tenantB->id))->carouselSlides();

    expect(collect($slidesB)->pluck('title')->all())->toBe(['B']);
});

it('snapshot returns a HomePageData with every section and merged hero status', function () {
    $tenant = homePageTenant();

    $hero = ['greeting' => 'Buenos días', 'name' => 'Ana', 'company' => $tenant->name];
    $data = (new HomePageService($tenant->id))->snapshot('acme', $hero);

    expect($data->hero['greeting'])->toBe('Buenos días')
        ->and($data->hero['status'])->toHaveKeys(['message', 'tone'])
        ->and($data->attentionItems)->toHaveCount(5)
        ->and($data->quickActions)->toHaveCount(6)
        ->and($data->carouselSlides)->toBeArray()
        ->and($data->importantNotices)->toBeArray()
        ->and($data->newsAndCommunications)->toBeArray()
        ->and($data->recentActivity)->toBeArray();
});

it('myWorkOrders returns only the given technician\'s own open work orders', function () {
    $tenant = homePageTenant();
    $service = app(WorkOrderService::class);

    $technician = User::factory()->create();
    $otherTechnician = User::factory()->create();

    $ownOverdue = WorkOrder::factory()->for($tenant)->create([
        'status' => 'planned', 'planned_end_at' => now()->subDay(),
    ]);
    $service->assignTechnician($ownOverdue, $technician, TechnicianRole::Technician);

    $ownOnTime = WorkOrder::factory()->for($tenant)->create([
        'status' => 'in_progress', 'planned_end_at' => now()->addDay(),
    ]);
    $service->assignTechnician($ownOnTime, $technician, TechnicianRole::Technician);

    $notMine = WorkOrder::factory()->for($tenant)->create(['status' => 'planned']);
    $service->assignTechnician($notMine, $otherTechnician, TechnicianRole::Technician);

    $result = (new HomePageService($tenant->id))->myWorkOrders($technician->id, 'acme');

    expect($result['count'])->toBe(2)
        ->and($result['overdue'])->toBe(1)
        ->and(collect($result['items'])->pluck('id')->all())
        ->toEqualCanonicalizing([$ownOverdue->id, $ownOnTime->id]);
});

it('myWorkOrders is empty for a user with no assigned work orders', function () {
    $tenant = homePageTenant();
    $technician = User::factory()->create();

    $result = (new HomePageService($tenant->id))->myWorkOrders($technician->id, 'acme');

    expect($result['count'])->toBe(0)
        ->and($result['overdue'])->toBe(0)
        ->and($result['items'])->toBe([]);
});

afterEach(function () {
    CurrentTenant::clear();
});
