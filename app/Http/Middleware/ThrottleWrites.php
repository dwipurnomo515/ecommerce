<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * A rate limit for EVERY write request, so a new route can't be forgotten,
 * plus stricter limits where abuse costs real money or harms others.
 *
 * Reads (GET) are not limited here: browsing must stay fast, and caching
 * and the web server protect those.
 */
class ThrottleWrites
{
    /**
     * Route name => [max attempts, per minutes], counted per IP. These are
     * Fortify's routes, which come without a limit:
     *  - register: mass creation of fake accounts
     *  - password.email: every request SENDS AN EMAIL; unlimited, our
     *    server becomes a tool to flood someone's inbox
     *  - password.update: guessing reset tokens
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private const STRICT = [
        'register.store' => [10, 60],
        'password.email' => [5, 15],
        'password.update' => [10, 15],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || $request->is('webhooks/*')) {
            // Webhooks: Midtrans' retries must never be refused.
            return $next($request);
        }

        $name = $request->route()?->getName();

        if ($name !== null && isset(self::STRICT[$name])) {
            [$max, $minutes] = self::STRICT[$name];
            $this->limit("strict:{$name}:{$request->ip()}", $max, $minutes * 60);
        }

        // Per account when logged in (shared Wi-Fi doesn't punish
        // everyone), per IP otherwise.
        $who = $request->user()?->getAuthIdentifier() ?? $request->ip();
        $this->limit("writes:{$who}", config()->integer('security.writes_per_minute'), 60);

        return $next($request);
    }

    private function limit(string $key, int $max, int $seconds): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw new ThrottleRequestsException(
                'Terlalu banyak permintaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
                headers: ['Retry-After' => (string) RateLimiter::availableIn($key)],
            );
        }

        RateLimiter::hit($key, $seconds);
    }
}
