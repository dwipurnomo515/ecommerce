<?php

namespace App\Providers;

use App\Models\User;
use App\Payments\Midtrans;
use App\Payments\PaymentGateway;
use App\Payouts\PayoutGateway;
use App\Payouts\SimulatedPayoutGateway;
use App\Refunds\RefundGateway;
use App\Refunds\SimulatedRefundGateway;
use App\Shipping\FlatRateShipping;
use App\Shipping\RajaOngkir;
use App\Shipping\ShippingProvider;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The one place that decides which shipping service the app uses.
        // Local development without a RajaOngkir key gets the clearly
        // labelled "Kurir Contoh"; tests and production always get the real
        // client (tests fake its HTTP calls).
        $this->app->singleton(ShippingProvider::class, fn () => $this->app->isLocal() && blank(config('services.rajaongkir.key'))
            ? new FlatRateShipping
            : new RajaOngkir(
                key: config('services.rajaongkir.key'),
                baseUrl: config()->string('services.rajaongkir.base_url'),
                couriers: config()->string('services.rajaongkir.couriers'),
            ));

        // Bank transfers to sellers: simulated until a real disbursement
        // provider is set up. A simulation must never move "money" on a
        // real server, so production refuses to start with it.
        $this->app->singleton(PayoutGateway::class, function () {
            if ($this->app->isProduction()) {
                throw new RuntimeException('No real payout provider configured; the simulation is not allowed in production.');
            }

            return new SimulatedPayoutGateway;
        });

        $this->app->singleton(RefundGateway::class, function () {
            if ($this->app->isProduction()) {
                throw new RuntimeException('No real refund provider configured; the simulation is not allowed in production.');
            }

            return new SimulatedRefundGateway;
        });

        $this->app->singleton(PaymentGateway::class, fn () => new Midtrans(
            serverKey: config('services.midtrans.server_key'),
            clientKey: config('services.midtrans.client_key'),
            production: config()->boolean('services.midtrans.production'),
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
