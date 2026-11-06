<?php

use App\Domain\Assets\Enums\IssueSeverity;
use App\Domain\Notifications\AlertNotification;
use App\Domain\Notifications\InboxNotification;
use App\Domain\Notifications\NotificationKind;
use App\Domain\Notifications\NotificationPresenter;
use App\Domain\Notifications\Recipients;
use App\Domain\Notifications\WorkOrderAssignedNotification;
use App\Domain\Safety\Services\SafetyFindingService;
use App\Filament\Pages\Notificaciones;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Livewire\Notifications\Campana;
use App\Models\Equipment;
use App\Models\EquipmentIssueReport;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * El sistema de avisos: la campana muestra todo lo que le llega a cada persona —también
 * lo que se guardaba solo para la app móvil—, cada aviso se marca leído o no leído, y el
 * centro de notificaciones los reúne. Y a cada rol le llega lo suyo.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);

    $persona = function (string $rol): User {
        $user = User::factory()->create(['is_active' => true]);
        $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
        $user->assignRole($rol);

        return $user;
    };

    $this->mantenimiento = $persona('mantenimiento');
    $this->admin = $persona('administrador-general');
    $this->almacen = $persona('almacen');
    $this->sst = $persona('sst');

    $this->actingAs($this->mantenimiento);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

/** Un aviso como los que se guardaban para la app móvil: sin el formato de la campana. */
function avisoMovil(User $user, string $type, array $data, bool $leido = false): void
{
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => $type,
        'data' => $data,
        'read_at' => $leido ? now() : null,
    ]);
}

it('shows in the bell the notifications that were saved only for the mobile app', function (): void {
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id]);
    avisoMovil($this->mantenimiento, WorkOrderAssignedNotification::class, [
        'title' => 'Nueva OT asignada', 'body' => 'OT-1: Cambiar rodamiento', 'work_order_id' => $workOrder->id, 'url' => '/mobile/work-orders/'.$workOrder->id,
    ]);
    FilamentNotification::make()->title('Reporte listo')->sendToDatabase($this->mantenimiento);

    $campana = Livewire::test(Campana::class);

    expect($campana->instance()->getUnreadNotificationsCount())->toBe(2);
    $campana->assertSee('Nueva OT asignada')->assertSee('Reporte listo')->assertSee('Marcar como leída')->assertSee('Ver todas');

    $legacy = $this->mantenimiento->notifications()->where('type', WorkOrderAssignedNotification::class)->sole();
    $shown = app(NotificationPresenter::class)->present($legacy);

    expect($shown['kind'])->toBe(NotificationKind::WorkOrder)
        ->and($shown['url'])->toContain('/work-orders/'.$workOrder->id);
});

it('marks a notification as read and back as unread from the bell', function (): void {
    FilamentNotification::make()->title('Hallazgo nuevo')->sendToDatabase($this->mantenimiento);
    $id = $this->mantenimiento->notifications()->sole()->id;

    $campana = Livewire::test(Campana::class)->call('markNotificationAsRead', $id);
    expect($this->mantenimiento->unreadNotifications()->count())->toBe(0);
    $campana->assertSee('Marcar como no leída');

    $campana->call('markNotificationAsUnread', $id);
    expect($this->mantenimiento->unreadNotifications()->count())->toBe(1);
});

it('lists the unread first in the notification center and lets you read, unread and delete them', function (): void {
    avisoMovil($this->mantenimiento, AlertNotification::class, ['title' => 'Reporte de novedad pendiente', 'body' => 'Ruido en la prensa', 'severity' => 'critical']);
    avisoMovil($this->mantenimiento, AlertNotification::class, ['title' => 'Alerta vieja', 'body' => 'Ya vista'], leido: true);
    avisoMovil($this->admin, AlertNotification::class, ['title' => 'Aviso de otra persona', 'body' => 'No se ve']);

    $sinLeer = $this->mantenimiento->notifications()->whereNull('read_at')->sole();
    $leida = $this->mantenimiento->notifications()->whereNotNull('read_at')->sole();

    $pantalla = Livewire::test(Notificaciones::class)
        ->assertOk()
        ->assertSee('Tiene 1 aviso sin leer.')
        ->assertCanSeeTableRecords([$sinLeer])
        ->assertCanNotSeeTableRecords([$leida])
        ->assertDontSee('Aviso de otra persona');

    $pantalla->call('setVista', 'todas')->assertCanSeeTableRecords([$sinLeer, $leida]);

    $pantalla->callTableAction('marcar-leida', $sinLeer);
    expect($sinLeer->fresh()->read_at)->not->toBeNull();

    $pantalla->callTableAction('marcar-no-leida', $leida);
    expect($leida->fresh()->read_at)->toBeNull();

    $pantalla->callAction('todas-leidas');
    expect($this->mantenimiento->unreadNotifications()->count())->toBe(0)
        ->and($this->admin->unreadNotifications()->count())->toBe(1);

    $pantalla->callTableBulkAction('marcar-no-leidas', [$sinLeer, $leida]);
    expect($this->mantenimiento->unreadNotifications()->count())->toBe(2);

    $pantalla->callTableAction('borrar', $leida);
    expect($this->mantenimiento->notifications()->count())->toBe(1);
});

it('filters the notification center by kind, old notifications included', function (): void {
    avisoMovil($this->mantenimiento, AlertNotification::class, ['title' => 'Alerta', 'body' => 'x']);
    $this->mantenimiento->notify(new InboxNotification(NotificationKind::Sst, 'Nuevo hallazgo SST H-001', 'Escalón', 'warning', null, $this->tenant->id));

    $sst = $this->mantenimiento->notifications()->where('data->kind', 'sst')->sole();
    $alerta = $this->mantenimiento->notifications()->where('type', AlertNotification::class)->sole();

    Livewire::test(Notificaciones::class)
        ->filterTable('kind', 'sst')
        ->assertCanSeeTableRecords([$sst])
        ->assertCanNotSeeTableRecords([$alerta])
        ->filterTable('kind', 'alert')
        ->assertCanSeeTableRecords([$alerta])
        ->assertCanNotSeeTableRecords([$sst]);
});

it('tells maintenance and management about a new issue report, except the ones SST already announced', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Prensa 15']);

    EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id, 'equipment_id' => $equipment->id, 'status' => 'open',
        'severity' => IssueSeverity::High->value, 'description' => 'Fuga de aceite', 'reporter_name' => 'Operario',
    ]);

    $aviso = $this->mantenimiento->notifications()->sole();
    expect($aviso->data['title'])->toBe('Reporte de novedad: Prensa 15')
        ->and($aviso->data['kind'])->toBe('issue_report')
        ->and($aviso->data['status'])->toBe('danger')
        ->and($this->admin->notifications()->count())->toBe(1)
        ->and($this->almacen->notifications()->count())->toBe(0);

    EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id, 'equipment_id' => $equipment->id, 'status' => 'open',
        'reporter_position' => SafetyFindingService::REPORTER_POSITION,
    ]);

    expect($this->mantenimiento->notifications()->count())->toBe(1);
});

it('finds the people of a role in the company even without a company in context', function (): void {
    CurrentTenant::clear();
    setPermissionsTeamId(null);

    expect(Recipients::withRoles($this->tenant->id, ['almacen'])->pluck('id')->all())->toBe([$this->almacen->id])
        ->and(Recipients::withRoles($this->tenant->id, ['mantenimiento', 'administrador-general'], except: $this->admin)->pluck('id')->all())->toBe([$this->mantenimiento->id]);
});

it('saves the new notifications in the bell format with their kind, link and company', function (): void {
    $this->sst->notify(new InboxNotification(NotificationKind::Sst, 'Hallazgo H-002 vencido', 'Falta: Mantenimiento.', 'danger', 'https://fronda.app/x', $this->tenant->id, 'Ver hallazgo'));

    $data = $this->sst->notifications()->sole()->data;

    expect($data['format'])->toBe('filament')
        ->and($data['kind'])->toBe('sst')
        ->and($data['tenant_id'])->toBe($this->tenant->id)
        ->and($data['actions'][0]['label'])->toBe('Ver hallazgo')
        ->and($data['actions'][0]['url'])->toBe('https://fronda.app/x');
});
