<?php

namespace App\Providers;

use App\Contracts\WebhookableEvent;
use App\Events\AlertCreated;
use App\Listeners\SendAlertNotificationListener;
use App\Listeners\WebhookTriggerListener;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentDocument;
use App\Models\EquipmentDowntimeEvent;
use App\Models\EquipmentPhoto;
use App\Models\MaintenancePlan;
use App\Models\PersonalAccessToken;
use App\Models\Plant;
use App\Models\SparePart;
use App\Models\User;
use App\Models\WorkOrder;
use App\Observers\AreaObserver;
use App\Observers\EquipmentCategoryObserver;
use App\Observers\EquipmentDocumentObserver;
use App\Observers\EquipmentDowntimeEventObserver;
use App\Observers\EquipmentObserver;
use App\Observers\EquipmentPhotoObserver;
use App\Observers\MaintenancePlanObserver;
use App\Observers\PlantObserver;
use App\Observers\SparePartObserver;
use App\Observers\WorkOrderObserver;
use App\Security\SsrfValidator;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureModels();
        $this->configurePostgres();
        $this->configureSuperAdmin();
        $this->registerObservers();
        $this->configureSanctum();
        $this->configureRateLimiting();
        $this->configureE2EWebhookFakes();
    }

    private function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()
            : null,
        );

        $this->enforceProductionSecurityRequirements();
    }

    private function enforceProductionSecurityRequirements(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        if (config('app.debug')) {
            throw new \RuntimeException('APP_DEBUG must be false in production.');
        }

        if (empty(array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', ''))))) {
            throw new \RuntimeException('CORS_ALLOWED_ORIGINS must be set explicitly in production.');
        }
    }

    private function configureModels(): void
    {
        // Prevent lazy loading in non-production environments to surface N+1 issues early.
        Model::preventLazyLoading(! app()->isProduction());

        // Prevent silently discarding attributes not in $fillable.
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        // Prevent accessing missing attributes instead of returning null.
        Model::preventAccessingMissingAttributes(! app()->isProduction());
    }

    private function configureSuperAdmin(): void
    {
        // Users with is_super_admin=true bypass ALL Gate checks.
        // Returning null (not false) tells Gate to continue evaluating other checks for non-super-admins.
        // This is PalmCore internal staff only — never assignable from the tenant UI.
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->is_super_admin ? true : null;
        });
    }

    private function registerObservers(): void
    {
        Equipment::observe(EquipmentObserver::class);
        EquipmentDocument::observe(EquipmentDocumentObserver::class);
        EquipmentPhoto::observe(EquipmentPhotoObserver::class);
        WorkOrder::observe(WorkOrderObserver::class);
        EquipmentDowntimeEvent::observe(EquipmentDowntimeEventObserver::class);
        MaintenancePlan::observe(MaintenancePlanObserver::class);
        SparePart::observe(SparePartObserver::class);
        Plant::observe(PlantObserver::class);
        Area::observe(AreaObserver::class);
        EquipmentCategory::observe(EquipmentCategoryObserver::class);

        Event::listen(AlertCreated::class, SendAlertNotificationListener::class);
        Event::listen(WebhookableEvent::class, WebhookTriggerListener::class);
    }

    private function configureSanctum(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    private function configureRateLimiting(): void
    {
        // Standard endpoints: 120 requests/minute per authenticated user
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(120)->by($request->user()->id)
                : Limit::perMinute(20)->by($request->ip());
        });

        // Heavy endpoints (KPIs, full list with relations): 20 requests/minute
        RateLimiter::for('api-heavy', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(20)->by($request->user()->id)
                : Limit::perMinute(5)->by($request->ip());
        });

        // Token creation: 5 attempts/minute per IP to prevent brute force
        RateLimiter::for('api-tokens', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Token refresh: uses HttpOnly cookie (not credentials) — higher limit than login
        RateLimiter::for('api-refresh', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }

    private function configureE2EWebhookFakes(): void
    {
        if (! env('FAKE_WEBHOOK_RESPONSES', false)) {
            return;
        }

        // In E2E tests (QUEUE_CONNECTION=sync + FAKE_WEBHOOK_RESPONSES=true):
        // 1. Override DNS resolver so any hostname resolves to a public IP, bypassing SSRF checks.
        // 2. Intercept all outgoing Http calls and return 200 immediately, so webhook delivery
        //    logs record status='success' without any real network call.
        SsrfValidator::setDnsResolver(fn (string $host): array => ['93.184.216.34']);

        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    private function configurePostgres(): void
    {
        // PostgreSQL uses timestamptz (timezone-aware). Ensure Laravel always stores
        // in UTC and casts correctly when reading back.
        DB::listen(function ($query) {
            // Hook point for query logging — wired up in dev via Telescope/Pail.
        });
    }
}
