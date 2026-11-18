<?php

namespace App\Domain\Commercial\Services;

use App\Domain\Commercial\Enums\DispatchStatus;
use App\Domain\Notifications\InboxNotification;
use App\Domain\Notifications\NotificationKind;
use App\Domain\Notifications\Recipients;
use App\Filament\Pages\TanquesAceite;
use App\Filament\Resources\Commercial\SalesDispatch\SalesDispatchResource;
use App\Filament\Resources\Commercial\SalesOffer\SalesOfferResource;
use App\Models\OilTank;
use App\Models\SalesDispatch;
use App\Models\SalesOffer;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Los avisos diarios del módulo comercial, por la campana (y el celular). Cada tema llega
 * en un solo aviso que junta todo lo nuevo —no uno por factura— y nada se avisa dos veces:
 *
 *  - Facturas que se vencieron sin pagarse (a la Directora Administrativa).
 *  - Cargues que el cliente recibió hace más de {@see self::UNINVOICED_AFTER_DAYS} días y
 *    siguen sin factura (a la Directora).
 *  - Ofertas a las que les queda una tractomula o menos (a la Directora).
 *  - Tanques sin medir hace más de {@see OilTankService::STALE_AFTER_DAYS} días, y acidez
 *    por encima del límite (a proceso y a la Directora).
 */
class CommercialNotices
{
    public const UNINVOICED_AFTER_DAYS = 3;

    /** Una tractomula: a una oferta con este saldo o menos le queda un cargue. */
    public const LAST_TRUCK_TONS = 34.0;

    private const DIRECTOR = ['direccion-administrativa'];

    private const PLANT = ['proceso', 'direccion-administrativa'];

    public function __construct(private OilTankService $tanks) {}

    /** @return array{overdue: int, uninvoiced: int, offers: int, tanks: int} cuántas cosas se avisaron */
    public function send(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::parse(CarbonImmutable::now('America/Bogota')->toDateString());
        $totals = ['overdue' => 0, 'uninvoiced' => 0, 'offers' => 0, 'tanks' => 0];

        Tenant::query()->each(function (Tenant $tenant) use ($today, &$totals): void {
            $totals['overdue'] += $this->overdueInvoices($tenant, $today);
            $totals['uninvoiced'] += $this->uninvoicedLoads($tenant, $today);
            $totals['offers'] += $this->offersNearlyDone($tenant);
            $totals['tanks'] += $this->tanks($tenant);
        });

        return $totals;
    }

    private function overdueInvoices(Tenant $tenant, CarbonImmutable $today): int
    {
        $invoices = SalesDispatch::withoutGlobalScopes()
            ->where('sales_dispatches.tenant_id', $tenant->id)
            ->whereNull('sales_dispatches.deleted_at')
            ->overdue($today->toDateString())
            ->whereNull('overdue_notified_at')
            ->with(['customer' => fn ($query) => $query->withoutGlobalScopes()])
            ->get();

        if ($invoices->isEmpty()) {
            return 0;
        }

        $amount = $invoices->sum(fn (SalesDispatch $d): float => (float) $d->invoice_amount);
        $count = $invoices->count();

        $this->notify($tenant, self::DIRECTOR,
            $count === 1 ? 'Se venció una factura sin pagar' : "Se vencieron {$count} facturas sin pagar",
            'Por $'.self::millions($amount).': '.self::byCustomer($invoices).'.',
            'danger',
            fn (): string => SalesDispatchResource::getUrl('index', ['tab' => DispatchStatus::Facturado->value, 'filters' => ['overdue' => ['isActive' => true]]], panel: 'admin', tenant: $tenant),
            'Ver facturas',
        );

        SalesDispatch::withoutGlobalScopes()->whereIn('id', $invoices->modelKeys())->update(['overdue_notified_at' => now()]);

        return $count;
    }

    private function uninvoicedLoads(Tenant $tenant, CarbonImmutable $today): int
    {
        $limit = $today->subDays(self::UNINVOICED_AFTER_DAYS)->toDateString();

        $loads = SalesDispatch::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->where('status', DispatchStatus::Descargado->value)
            ->whereNull('uninvoiced_notified_at')
            ->whereRaw('coalesce(unloaded_on, loaded_on) <= ?::date', [$limit])
            ->with(['customer' => fn ($query) => $query->withoutGlobalScopes()])
            ->get();

        if ($loads->isEmpty()) {
            return 0;
        }

        $tons = $loads->sum(fn (SalesDispatch $d): float => (float) $d->arrival_weight_tons);
        $count = $loads->count();

        $this->notify($tenant, self::DIRECTOR,
            ($count === 1 ? 'Un cargue recibido' : "{$count} cargues recibidos").' sin facturar',
            'El cliente los recibió hace más de '.self::UNINVOICED_AFTER_DAYS.' días ('.number_format($tons, 2, ',', '.').' tn): '.self::byCustomer($loads).'.',
            'warning',
            fn (): string => SalesDispatchResource::getUrl('index', ['tab' => DispatchStatus::Descargado->value], panel: 'admin', tenant: $tenant),
            'Ver cargues',
        );

        SalesDispatch::withoutGlobalScopes()->whereIn('id', $loads->modelKeys())->update(['uninvoiced_notified_at' => now()]);

        return $count;
    }

    private function offersNearlyDone(Tenant $tenant): int
    {
        $offers = SalesOffer::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->whereNull('nearly_closed_notified_at')
            ->open()
            ->withExecution()
            ->with(['customer' => fn ($query) => $query->withoutGlobalScopes()])
            ->get()
            ->filter(fn (SalesOffer $offer): bool => $offer->dispatches_count > 0 && $offer->remainingTons() <= self::LAST_TRUCK_TONS);

        if ($offers->isEmpty()) {
            return 0;
        }

        $this->notify($tenant, self::DIRECTOR,
            $offers->count() === 1 ? 'A una oferta le queda un cargue' : "A {$offers->count()} ofertas les queda un cargue",
            $offers->map(fn (SalesOffer $o): string => "{$o->number} ({$o->customer?->name}): quedan ".number_format($o->remainingTons(), 2, ',', '.').' tn')->implode('; ').'. Es hora de negociar la siguiente.',
            'info',
            fn (): string => SalesOfferResource::getUrl('index', panel: 'admin', tenant: $tenant),
            'Ver ofertas',
        );

        SalesOffer::withoutGlobalScopes()->whereIn('id', $offers->modelKeys())->update(['nearly_closed_notified_at' => now()]);

        return $offers->count();
    }

    /**
     * Tanques sin medir y acidez alta. Se avisa una vez por medición: mientras nadie mida,
     * no se repite cada mañana.
     */
    private function tanks(Tenant $tenant): int
    {
        if (! OilTank::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('deleted_at')->where('is_active', true)->exists()) {
            return 0;
        }

        $latest = $this->tanks->latest($tenant->id);
        $measuredOn = $latest !== null ? $latest['date']->toDateString() : 'nunca';
        $sent = 0;
        $url = fn (): string => TanquesAceite::getUrl(panel: 'admin', tenant: $tenant);

        if ($latest === null || $latest['stale']) {
            if (Cache::add("comercial:tanques-sin-medir:{$tenant->id}:{$measuredOn}", true, now()->addDays(60))) {
                $this->notify($tenant, self::PLANT,
                    $latest === null ? 'Los tanques de aceite no se han medido' : "Los tanques llevan {$latest['age_days']} días sin medir",
                    'Sin la medición del día no se sabe cuánto aceite hay para vender ni cuánto se produjo.',
                    'warning', $url, 'Medir tanques',
                );
                $sent++;
            }

            return $sent;
        }

        $high = collect($latest['tanks'])->filter(fn (array $tank): bool => $tank['acidity'] !== null && $tank['acidity'] > OilTankService::ACIDITY_LIMIT);

        if ($high->isNotEmpty() && Cache::add("comercial:acidez-alta:{$tenant->id}:{$measuredOn}", true, now()->addDays(60))) {
            $names = OilTank::withoutGlobalScopes()->whereIn('id', $high->keys())->pluck('name', 'id');
            $this->notify($tenant, self::PLANT,
                'Acidez alta en los tanques',
                $high->map(fn (array $tank, string $id): string => ($names[$id] ?? 'Tanque').' '.number_format($tank['acidity'], 2, ',', '.').' %')->implode(', ')
                    .' el '.$latest['date']->format('d/m/Y').' (límite '.number_format(OilTankService::ACIDITY_LIMIT, 0).' %).',
                'danger', $url, 'Ver tanques',
            );
            $sent++;
        }

        return $sent;
    }

    /**
     * @param  list<string>  $roles
     * @param  callable(): string  $url
     */
    private function notify(Tenant $tenant, array $roles, string $title, string $body, string $status, callable $url, string $actionLabel): void
    {
        $users = Recipients::withRoles($tenant->id, $roles);

        if ($users->isEmpty()) {
            return;
        }

        try {
            $link = $url();
        } catch (Throwable) {
            $link = null;
        }

        Notification::send($users, new InboxNotification(
            kind: in_array('proceso', $roles, true) ? NotificationKind::Production : NotificationKind::Commercial,
            title: $title,
            body: $body,
            status: $status,
            url: $link,
            tenantId: $tenant->id,
            actionLabel: $actionLabel,
        ));
    }

    /** «Manuelita (2), BioD (1)». @param Collection<int, SalesDispatch> $dispatches */
    private static function byCustomer(Collection $dispatches): string
    {
        return $dispatches->groupBy(fn (SalesDispatch $d): string => $d->customer?->name ?? 'Sin cliente')
            ->map(fn (Collection $group, string $name): string => "{$name} ({$group->count()})")
            ->implode(', ');
    }

    private static function millions(float $amount): string
    {
        return number_format($amount / 1_000_000, 1, ',', '.').' M';
    }
}
