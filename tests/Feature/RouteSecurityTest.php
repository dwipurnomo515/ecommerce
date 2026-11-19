<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireAdminSecondFactor;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

/**
 * Guard rails over ALL routes, so that a route added later can't silently
 * skip protection: these tests read the route table itself.
 */
class RouteSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** Routes anyone may open without logging in, on purpose. */
    private const PUBLIC = [
        'home', 'shop.search', 'shop.categories.show', 'shop.stores.show', 'shop.products.show',
        'webhooks.midtrans', // protected by the Midtrans signature instead
        'well-known.passkeys',
    ];

    /**
     * Our own routes (not Laravel's, Fortify's or other packages').
     *
     * @return list<Route>
     */
    private function appRoutes(): array
    {
        return array_values(array_filter(
            Router::getRoutes()->getRoutes(),
            fn (Route $r) => str_starts_with($r->getActionName(), 'App\\') || $r->getActionName() === 'Closure',
        ));
    }

    /**
     * @return list<string>
     */
    private function middleware(Route $route): array
    {
        return Router::gatherRouteMiddleware($route);
    }

    public function test_every_app_route_needs_a_login_unless_it_is_knowingly_public(): void
    {
        $unexpected = [];

        foreach ($this->appRoutes() as $route) {
            $name = $route->getName() ?? $route->uri();
            // gatherRouteMiddleware() returns aliases as declared: 'auth', 'auth:sanctum'...
            $guarded = collect($this->middleware($route))->contains(fn (string $m) => $m === 'auth' || str_starts_with($m, 'auth:'));

            if (! $guarded && ! in_array($name, self::PUBLIC, true) && ! str_starts_with($route->uri(), 'dev/')) {
                $unexpected[] = $name;
            }
        }

        $this->assertSame([], $unexpected, 'Routes without login that are not on the public list.');
    }

    public function test_every_admin_route_requires_the_admin_gate_and_a_second_factor(): void
    {
        $admin = array_filter($this->appRoutes(), fn (Route $r) => str_starts_with($r->uri(), 'admin/'));
        $this->assertNotEmpty($admin);

        foreach ($admin as $route) {
            $mw = $this->middleware($route);
            $this->assertContains('can:admin', $mw, "{$route->uri()} lacks can:admin");
            $this->assertContains(RequireAdminSecondFactor::class, $mw, "{$route->uri()} lacks the second factor");
        }
    }

    public function test_seller_routes_require_a_verified_email(): void
    {
        foreach (array_filter($this->appRoutes(), fn (Route $r) => str_starts_with($r->uri(), 'seller/')) as $route) {
            $this->assertContains('verified', $this->middleware($route), "{$route->uri()} lacks verified");
        }
    }

    public function test_an_admin_without_a_second_factor_is_sent_to_set_one_up(): void
    {
        $admin = User::factory()->adminWithoutSecondFactor()->create();

        $this->actingAs($admin)->get(route('admin.stores.index'))->assertRedirect(route('security.edit'));
        $this->actingAs($admin)->post(route('admin.stores.approve', Store::factory()->create()))->assertRedirect(route('security.edit'));

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.stores.index'))->assertOk();
    }

    public function test_the_second_factor_requirement_can_be_switched_off_locally(): void
    {
        config(['security.admin_requires_second_factor' => false]);

        $this->actingAs(User::factory()->adminWithoutSecondFactor()->create())
            ->get(route('admin.stores.index'))
            ->assertOk();
    }

    public function test_password_reset_emails_cannot_be_used_to_flood_an_inbox(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post(route('password.email'), ['email' => 'korban@example.com']);
        }

        $this->post(route('password.email'), ['email' => 'korban@example.com'])->assertTooManyRequests();
    }

    public function test_mass_registration_is_limited(): void
    {
        foreach (range(1, 10) as $i) {
            auth()->logout();
            $this->post(route('register.store'), [
                'name' => "Akun {$i}", 'email' => "akun{$i}@example.com",
                'password' => 'Rahasia-Sekali-123', 'password_confirmation' => 'Rahasia-Sekali-123',
            ]);
        }
        auth()->logout();

        $this->post(route('register.store'), ['name' => 'Akun 11', 'email' => 'akun11@example.com'])->assertTooManyRequests();
    }

    public function test_every_write_request_has_a_per_user_ceiling(): void
    {
        config(['security.writes_per_minute' => 3]);
        $user = User::factory()->create();

        foreach (range(1, 3) as $i) {
            $this->actingAs($user)->post(route('cart.store'), ['variant_id' => 999, 'quantity' => 1]);
        }

        $this->actingAs($user)->post(route('cart.store'), ['variant_id' => 999, 'quantity' => 1])->assertTooManyRequests();
        // Reading stays free.
        $this->actingAs($user)->get(route('home'))->assertOk();
    }

    public function test_the_unused_private_storage_routes_are_gone(): void
    {
        $this->assertFalse(Router::has('storage.local'));
        $this->assertFalse(Router::has('storage.local.upload'));
    }
}
