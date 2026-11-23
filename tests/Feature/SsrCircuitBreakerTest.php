<?php

namespace Tests\Feature;

use Illuminate\Foundation\Vite;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

/**
 * When the SSR server is unreachable, only the first visitor waits for
 * that to be discovered; pages skip SSR for 30 seconds after it.
 */
class SsrCircuitBreakerTest extends TestCase
{
    private const SSR_URL = 'http://ssr.test:13714/render';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.url' => 'http://ssr.test:13714',
            'inertia.ssr.ensure_bundle_exists' => false,
        ]);
        // The production path (the SSR server), not the Vite dev server
        // that public/hot points to while `npm run dev` is running. Asset
        // tags render empty, so no built manifest is needed either.
        $this->swap(Vite::class, new class extends Vite
        {
            public function __invoke($entrypoints, $buildDirectory = null): HtmlString
            {
                return new HtmlString('');
            }

            public function isRunningHot(): bool
            {
                return false;
            }
        });
    }

    private function ssrCalls(): int
    {
        return count(Http::recorded(fn (Request $r) => $r->url() === self::SSR_URL));
    }

    public function test_switched_off_means_never_asked(): void
    {
        config(['inertia.ssr.enabled' => false]);
        Http::fake();

        $this->get(route('login'))->assertOk();

        Http::assertNothingSent();
    }

    public function test_a_server_rendered_page_contains_its_html(): void
    {
        Http::fake([self::SSR_URL => Http::response(['head' => ['<title>Masuk</title>'], 'body' => '<form id="ssr-form"></form>'])]);

        $this->get(route('login'))->assertOk()->assertSee('<form id="ssr-form"></form>', false);
    }

    public function test_an_unreachable_server_is_skipped_for_30_seconds(): void
    {
        Http::fake([self::SSR_URL => Http::failedConnection()]);

        // Still a working page: the browser renders it.
        $this->get(route('login'))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->assertSame(1, $this->ssrCalls(), 'Only the first visitor tries.');

        $this->travel(31)->seconds();
        $this->get(route('login'))->assertOk();
        $this->assertSame(2, $this->ssrCalls(), 'Tried again after the pause.');
    }

    public function test_a_render_error_in_one_page_does_not_switch_ssr_off(): void
    {
        Http::fake([self::SSR_URL => Http::response(['error' => 'window is not defined', 'type' => 'browser-api'], 500)]);

        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();

        $this->assertSame(2, $this->ssrCalls());
    }
}
