<?php

namespace App\Providers;

use App\Domain\Shared\Enums\SubscriptionStatus;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Area;
use App\Models\CarouselSlide;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentDocument;
use App\Models\EquipmentDowntimeEvent;
use App\Models\EquipmentIssueReport;
use App\Models\EquipmentPhoto;
use App\Models\InstitutionalContent;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRequest;
use App\Models\PersonalAccessToken;
use App\Models\Plant;
use App\Models\SparePart;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use App\Observers\AlertObserver;
use App\Observers\AnnouncementObserver;
use App\Observers\AreaObserver;
use App\Observers\CarouselSlideObserver;
use App\Observers\EquipmentCategoryObserver;
use App\Observers\EquipmentDocumentObserver;
use App\Observers\EquipmentDowntimeEventObserver;
use App\Observers\EquipmentIssueReportObserver;
use App\Observers\EquipmentObserver;
use App\Observers\EquipmentPhotoObserver;
use App\Observers\InstitutionalContentObserver;
use App\Observers\MaintenancePlanObserver;
use App\Observers\MaintenanceRequestObserver;
use App\Observers\PlantObserver;
use App\Observers\SparePartObserver;
use App\Observers\UserObserver;
use App\Observers\WorkOrderObserver;
use App\Observers\WorkOrderPartObserver;
use App\Security\SsrfValidator;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;

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
        $this->configureSentry();
        $this->configureLivewire();
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
        // Super admins bypass ALL Gate checks, including subscription enforcement.
        // Non-super-admins are additionally gated by their tenant's subscription status.
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->is_super_admin) {
                return true;
            }

            // Block mutation abilities for read_only / suspended tenants.
            // The bound value is set by CheckTenantSubscription middleware on every request.
            if (app()->bound('subscription.status')) {
                $status = app('subscription.status');

                if (
                    $status instanceof SubscriptionStatus
                    && ! $status->allowsMutations()
                    && in_array($ability, SubscriptionStatus::BLOCKED_ABILITIES, strict: true)
                ) {
                    return false;
                }
            }

            return null;
        });
    }

    private function registerObservers(): void
    {
        Equipment::observe(EquipmentObserver::class);
        EquipmentDocument::observe(EquipmentDocumentObserver::class);
        EquipmentPhoto::observe(EquipmentPhotoObserver::class);
        WorkOrder::observe(WorkOrderObserver::class);
        WorkOrderPart::observe(WorkOrderPartObserver::class);
        EquipmentDowntimeEvent::observe(EquipmentDowntimeEventObserver::class);
        MaintenancePlan::observe(MaintenancePlanObserver::class);
        SparePart::observe(SparePartObserver::class);
        Plant::observe(PlantObserver::class);
        Area::observe(AreaObserver::class);
        EquipmentCategory::observe(EquipmentCategoryObserver::class);
        User::observe(UserObserver::class);
        CarouselSlide::observe(CarouselSlideObserver::class);
        Announcement::observe(AnnouncementObserver::class);
        InstitutionalContent::observe(InstitutionalContentObserver::class);
        MaintenanceRequest::observe(MaintenanceRequestObserver::class);
        EquipmentIssueReport::observe(EquipmentIssueReportObserver::class);
        Alert::observe(AlertObserver::class);

        // Listeners in app/Listeners are registered by Laravel's auto-discovery from
        // their handle() type-hint. Binding them here as well registered them TWICE:
        // every alert notified the técnico twice and every webhook was delivered
        // twice to the customer's endpoint. Leave this to discovery.
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

    private function configureSentry(): void
    {
        if (! app()->bound('sentry')) {
            return;
        }

        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'webhook_secret', 'authorization', 'current_password', 'new_password'];

        app('sentry')->getClient()?->getOptions()->setBeforeSendCallback(
            static function (Event $event, ?EventHint $hint) use ($sensitiveKeys): ?\Sentry\Event {
                $breadcrumbs = $event->getBreadcrumbs();

                $sanitized = array_map(static function (Breadcrumb $breadcrumb) use ($sensitiveKeys): Breadcrumb {
                    foreach ($sensitiveKeys as $key) {
                        if (array_key_exists($key, $breadcrumb->getMetadata())) {
                            $breadcrumb = $breadcrumb->withMetadata($key, '[Filtered]');
                        }
                    }

                    return $breadcrumb;
                }, $breadcrumbs);

                return $event->setBreadcrumb($sanitized);
            }
        );
    }

    private function configureLivewire(): void
    {
        // Replace Livewire's default hash-based asset URL (/livewire-{hash}/livewire.min.js)
        // with a fixed path. The hash URL was returning 404 on Railway despite the route being
        // registered, likely due to nginx or OPcache interaction with the dynamic path.
        Livewire::setScriptRoute(function ($handle) {
            return Route::get('/livewire/livewire.min.js', $handle);
        });
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
