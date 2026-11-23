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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Ssr\SsrErrorType;
use Inertia\Ssr\SsrRenderFailed;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /** Cache key set while the SSR server is known to be unreachable. */
    public const SSR_DOWN = 'inertia-ssr-down';

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
        $this->configureSsr();
    }

    /**
     * Circuit breaker for server-side rendering. When the SSR server can't
     * be reached, finding that out costs seconds (a stopped container's
     * name takes ~3 s to fail in Docker DNS). Without this, EVERY page
     * would pay that until it is back. Now one visitor pays it, and for
     * the next 30 seconds pages skip SSR (the browser renders them).
     * Render errors in one page don't trip it: other pages still work.
     */
    private function configureSsr(): void
    {
        Event::listen(function (SsrRenderFailed $event): void {
            if ($event->type === SsrErrorType::Connection) {
                Cache::put(self::SSR_DOWN, true, now()->addSeconds(30));
                Log::warning('SSR server unreachable, skipping SSR for 30 s', ['error' => $event->error]);
            }
        });

        // A disable condition REPLACES config('inertia.ssr.enabled') inside
        // Inertia, so the switch has to be checked here as well.
        Inertia::disableSsr(fn (): bool => ! config('inertia.ssr.enabled') || Cache::has(self::SSR_DOWN));
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
