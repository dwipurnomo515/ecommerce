<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * request()->ip() feeds the login and write throttles and the audit log,
 * so who may change it (via X-Forwarded-For) is a security decision.
 */
class TrustedProxiesTest extends TestCase
{
    private const PROXY = '10.0.0.5';

    private const VISITOR = '203.0.113.9';

    protected function tearDown(): void
    {
        unset($_SERVER['TRUSTED_PROXIES'], $_ENV['TRUSTED_PROXIES']);

        parent::tearDown();
    }

    /**
     * Boots a fresh app with TRUSTED_PROXIES set, the way production does.
     */
    private function bootWith(?string $proxies): void
    {
        if ($proxies !== null) {
            $_SERVER['TRUSTED_PROXIES'] = $_ENV['TRUSTED_PROXIES'] = $proxies;
        }

        $this->refreshApplication();

        Route::get('/_test/ip', fn (Request $request) => $request->ip().' '.$request->getScheme());
    }

    private function ipSeenFrom(string $remoteAddr): string
    {
        return (string) $this->withServerVariables(['REMOTE_ADDR' => $remoteAddr])
            ->withHeaders(['X-Forwarded-For' => self::VISITOR, 'X-Forwarded-Proto' => 'https'])
            ->get('/_test/ip')
            ->assertOk()
            ->getContent();
    }

    public function test_by_default_forwarded_headers_are_ignored(): void
    {
        $this->bootWith(null);

        $this->assertSame(self::PROXY.' http', $this->ipSeenFrom(self::PROXY));
    }

    public function test_a_trusted_proxy_passes_on_the_visitor_ip_and_https(): void
    {
        $this->bootWith('192.168.1.1, 10.0.0.0/8');

        $this->assertSame(self::VISITOR.' https', $this->ipSeenFrom(self::PROXY));
    }

    public function test_anyone_else_cannot_fake_their_ip(): void
    {
        $this->bootWith('10.0.0.0/8');

        // Talks to us directly and claims to be someone else: ignored.
        $this->assertSame('198.51.100.7 http', $this->ipSeenFrom('198.51.100.7'));
    }
}
