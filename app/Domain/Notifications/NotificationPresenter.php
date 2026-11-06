<?php

namespace App\Domain\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lee cualquier aviso guardado y lo deja listo para mostrar: título, cuerpo, tipo, color y
 * a dónde lleva «Abrir».
 *
 * Hay dos formas guardadas. Los avisos nuevos ({@see InboxNotification}) y las descargas
 * vienen en el formato de la campana. Los viejos de alertas, OT, stock, preventivos y
 * MTBF se guardaban para la app móvil —con enlaces /mobile/...— y la campana del panel no
 * los mostraba: se traducen aquí, con su enlace en el panel.
 */
class NotificationPresenter
{
    /** El tipo de aviso según la clase que lo mandó. */
    private const KIND_BY_TYPE = [
        'AlertNotification' => NotificationKind::Alert,
        'WorkOrderAssignedNotification' => NotificationKind::WorkOrder,
        'WorkOrderOverdueNotification' => NotificationKind::WorkOrder,
        'WorkOrderStatusChangedNotification' => NotificationKind::WorkOrder,
        'MaintenanceRequestEmergencyNotification' => NotificationKind::WorkOrder,
        'LowStockNotification' => NotificationKind::Inventory,
        'MaintenancePlanOverdueNotification' => NotificationKind::Maintenance,
        'ScheduleUpcomingNotification' => NotificationKind::Maintenance,
        'MtbfAlertNotification' => NotificationKind::Reliability,
        'PlatformHealthAlertNotification' => NotificationKind::Platform,
    ];

    public function __construct(private PanelLinks $links) {}

    /**
     * @return array{title: string, body: ?string, kind: NotificationKind, status: string, url: ?string}
     */
    public function present(DatabaseNotification $notification): array
    {
        $data = (array) $notification->data;
        $kind = $this->kind($notification, $data);

        return [
            'title' => (string) ($data['title'] ?? 'Aviso'),
            'body' => isset($data['body']) ? strip_tags((string) $data['body']) : null,
            'kind' => $kind,
            'status' => $this->status($data, $kind),
            'url' => $this->url($data),
        ];
    }

    /**
     * El aviso como lo pinta la campana: con su «Abrir» y el botón para marcarlo leído o
     * volverlo a dejar sin leer.
     */
    public function toFilament(DatabaseNotification $notification): FilamentNotification
    {
        $shown = $this->present($notification);
        $data = (array) $notification->data;

        if (($data['format'] ?? null) === 'filament') {
            try {
                $filament = FilamentNotification::fromDatabase($notification);
            } catch (Throwable) {
                $filament = FilamentNotification::make((string) $notification->getKey())->title($shown['title'])->body($shown['body']);
            }
        } else {
            $filament = FilamentNotification::make((string) $notification->getKey())
                ->title($shown['title'])
                ->body($shown['body'])
                ->status($shown['status']);
        }

        $filament->id((string) $notification->getKey())
            ->icon($data['icon'] ?? $shown['kind']->icon())
            ->iconColor($shown['status']);

        $actions = collect($filament->getActions())
            ->reject(fn ($action): bool => $action instanceof Action && in_array($action->getName(), ['marcar-leida', 'marcar-no-leida'], true));

        $hasLink = $actions->contains(fn ($action): bool => $action instanceof Action && filled($action->getUrl()));

        if (! $hasLink && $shown['url']) {
            $actions->prepend(Action::make('abrir')->label('Abrir')->url($shown['url'])->markAsRead());
        }

        $actions->push($notification->unread()
            ? Action::make('marcar-leida')->label('Marcar como leída')->link()->color('gray')->markAsRead()
            : Action::make('marcar-no-leida')->label('Marcar como no leída')->link()->color('gray')->markAsUnread());

        return $filament->actions($actions->values()->all());
    }

    /**
     * Los avisos de un tipo, en la base: por el tipo guardado o, en los viejos, por la
     * clase que los mandó. Las mismas reglas que {@see self::kind()}.
     */
    public static function whereKind(Builder $query, NotificationKind $kind): Builder
    {
        $types = collect(self::KIND_BY_TYPE)
            ->filter(fn (NotificationKind $mapped): bool => $mapped === $kind)
            ->keys()
            ->flatMap(fn (string $class): array => ["App\\Domain\\Notifications\\{$class}", "App\\Notifications\\{$class}"])
            ->all();

        return $query->where(function (Builder $query) use ($kind, $types): void {
            $query->where('data->kind', $kind->value);

            if ($types !== []) {
                $query->orWhereIn('type', $types);
            }

            if ($kind === NotificationKind::Sst) {
                $query->orWhere(fn (Builder $old) => $old->whereNull('data->kind')->where('data->icon', 'heroicon-o-shield-exclamation'));
            }

            if ($kind === NotificationKind::Report) {
                $query->orWhere(fn (Builder $old) => $old->whereNull('data->kind')->where('data->format', 'filament')->whereRaw('data::text like ?', ['%"name":"download"%']));
            }
        });
    }

    /** @param  array<string, mixed>  $data */
    private function kind(DatabaseNotification $notification, array $data): NotificationKind
    {
        if (isset($data['kind']) && ($kind = NotificationKind::tryFrom((string) $data['kind']))) {
            return $kind;
        }

        if ($kind = self::KIND_BY_TYPE[class_basename((string) $notification->type)] ?? null) {
            return $kind;
        }

        // Los de SST del primer día, antes de que los avisos llevaran su tipo.
        if (($data['icon'] ?? null) === 'heroicon-o-shield-exclamation' || Str::contains((string) ($data['title'] ?? ''), 'allazgo')) {
            return NotificationKind::Sst;
        }

        $actions = collect($data['actions'] ?? []);

        if ($actions->contains(fn ($action): bool => in_array($action['name'] ?? null, ['download', 'descargar'], true))) {
            return NotificationKind::Report;
        }

        return NotificationKind::General;
    }

    /** @param  array<string, mixed>  $data */
    private function status(array $data, NotificationKind $kind): string
    {
        $status = $data['status'] ?? null;

        if (in_array($status, ['success', 'warning', 'danger', 'info'], true)) {
            return $status;
        }

        $severity = $data['severity'] ?? null;

        return match (true) {
            $severity === 'critical' => 'danger',
            $severity === 'warning' => 'warning',
            in_array($kind, [NotificationKind::Inventory, NotificationKind::Reliability], true) => 'danger',
            in_array($kind, [NotificationKind::Maintenance, NotificationKind::WorkOrder], true) => 'warning',
            default => 'info',
        };
    }

    /** @param  array<string, mixed>  $data */
    private function url(array $data): ?string
    {
        foreach ($data['actions'] ?? [] as $action) {
            if (filled($action['url'] ?? null)) {
                return $action['url'];
            }
        }

        $url = $data['url'] ?? null;

        if (filled($url) && ! Str::startsWith((string) $url, '/mobile')) {
            return $url;
        }

        // Los avisos de la app móvil: al registro en el panel.
        foreach (['work_order_id' => 'work_order', 'alert_id' => 'alert', 'maintenance_plan_id' => 'maintenance_plan', 'spare_part_id' => 'spare_part', 'equipment_id' => 'equipment', 'maintenance_request_id' => 'maintenance_request'] as $key => $type) {
            if (filled($data[$key] ?? null) && ($link = $this->links->to($type, (string) $data[$key]))) {
                return $link;
            }
        }

        return null;
    }
}
