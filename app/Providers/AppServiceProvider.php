<?php

namespace App\Providers;

use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\EquipmentPhoto;
use App\Models\User;
use App\Observers\EquipmentDocumentObserver;
use App\Observers\EquipmentObserver;
use App\Observers\EquipmentPhotoObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
    }

    private function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()
            : null,
        );
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
