<?php

namespace App\Providers;

use App\Models\User;
use App\Shipping\RajaOngkir;
use App\Shipping\ShippingProvider;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The one place that decides which shipping service the app uses.
        $this->app->singleton(ShippingProvider::class, fn () => new RajaOngkir(
            key: config('services.rajaongkir.key'),
            baseUrl: config()->string('services.rajaongkir.base_url'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Abilities that are not tied to a single model. Used in routes as
     * `->middleware('can:admin')` and in code as `Gate::allows('admin')`.
     */
    protected function configureAuthorization(): void
    {
        Gate::define('admin', fn (User $user): bool => $user->isAdmin());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Outside production, fail loudly on: lazy loading (N+1 queries),
        // filling a non-fillable attribute, and reading a missing attribute.
        // In production these would only hurt users, so they stay off there.
        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
