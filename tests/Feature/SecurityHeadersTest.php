<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private function home(): TestResponse
    {
        return $this->get(route('home'))->assertOk();
    }

    /**
     * @return array<string, string>
     */
    private function csp(TestResponse $response): array
    {
        $directives = [];
        foreach (explode(';', (string) $response->headers->get('Content-Security-Policy')) as $part) {
            $words = explode(' ', trim($part), 2);
            $directives[$words[0]] = $words[1] ?? '';
        }

        return $directives;
    }

    public function test_the_basic_browser_protections_are_sent(): void
    {
        $this->home()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        $this->assertStringContainsString('geolocation=()', (string) $this->home()->headers->get('Permissions-Policy'));
    }

    public function test_scripts_need_our_nonce_and_inline_scripts_carry_it(): void
    {
        $response = $this->home();
        $csp = $this->csp($response);

        $this->assertMatchesRegularExpression("/'nonce-([A-Za-z0-9]+)'/", $csp['script-src']);
        $this->assertStringNotContainsString("'unsafe-inline'", $csp['script-src'], 'Inline scripts need the nonce.');
        $this->assertStringNotContainsString("'unsafe-eval'", $csp['script-src']);

        preg_match("/'nonce-([A-Za-z0-9]+)'/", $csp['script-src'], $m);
        $html = (string) $response->getContent();

        // Every script that can run carries this response's nonce...
        preg_match_all('/<script(?![^>]*type="application\/json")[^>]*>/', $html, $scripts);
        $this->assertNotEmpty($scripts[0]);
        foreach ($scripts[0] as $tag) {
            $this->assertStringContainsString('nonce="'.$m[1].'"', $tag, "Script without the nonce: {$tag}");
        }
    }

    public function test_the_nonce_is_new_for_every_response(): void
    {
        $this->assertNotSame($this->csp($this->home())['script-src'], $this->csp($this->home())['script-src']);
    }

    public function test_framing_plugins_and_foreign_forms_are_refused(): void
    {
        $csp = $this->csp($this->home());

        $this->assertSame("'none'", $csp['frame-ancestors']);
        $this->assertSame("'none'", $csp['object-src']);
        $this->assertSame("'self'", $csp['form-action']);
        $this->assertSame("'self'", $csp['base-uri']);
    }

    public function test_midtrans_snap_is_allowed_to_load_and_show_its_popup(): void
    {
        $csp = $this->csp($this->home());

        $this->assertStringContainsString('https://*.midtrans.com', $csp['script-src']);
        $this->assertStringContainsString('https://*.midtrans.com', $csp['frame-src']);
    }

    public function test_hsts_only_in_production(): void
    {
        $this->home()->assertHeaderMissing('Strict-Transport-Security');

        $this->app->detectEnvironment(fn () => 'production');

        $this->home()->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->assertArrayHasKey('upgrade-insecure-requests', $this->csp($this->home()));
    }

    public function test_report_only_mode_reports_instead_of_blocking(): void
    {
        config(['security.csp.report_only' => true]);

        $this->home()
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_an_unknown_appearance_cookie_never_reaches_the_inline_script(): void
    {
        $html = (string) $this->withUnencryptedCookie('appearance', "dark';alert(1);//")
            ->get(route('home'))->getContent();

        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringContainsString("const appearance = 'system';", $html);
    }
}
