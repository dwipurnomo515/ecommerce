<?php

namespace App\Http\Middleware;

use App\Models\ProductImage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side defences, sent with every web response.
 *
 * The CSP is the important one: even if an attacker ever manages to inject
 * HTML into a page (XSS), the browser refuses to run scripts that are not
 * ours: inline scripts need this request's random nonce, and external
 * scripts must come from an allowed origin.
 */
class SecurityHeaders
{
    /** Midtrans Snap: the script, its popup iframe, its API calls and images. */
    private const MIDTRANS = 'https://*.midtrans.com';

    public function handle(Request $request, Closure $next): Response
    {
        // A fresh random nonce for this response. @vite, @viteReactRefresh
        // and our own inline <script nonce="..."> pick it up.
        Vite::useCspNonce();

        $response = $next($request);

        $headers = [
            // Never guess a file's type from its content (an uploaded file
            // can't be "upgraded" to a script).
            'X-Content-Type-Options' => 'nosniff',
            // Other sites may not frame us (clickjacking: a hidden "Bayar"
            // button under a fake one). Old-browser twin of frame-ancestors.
            'X-Frame-Options' => 'DENY',
            // Other sites see only our origin, never full URLs with order codes.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // Features this shop never uses are switched off entirely.
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), usb=(), interest-cohort=()',
            // Keeps other windows from reaching into ours; popups still work
            // (some payment methods open one).
            'Cross-Origin-Opener-Policy' => 'same-origin-allow-popups',
        ];

        if (app()->isProduction()) {
            // "Only ever talk to us over HTTPS", remembered for a year.
            // Production only: development runs on plain HTTP.
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        if (config('security.csp.enabled')) {
            $name = config('security.csp.report_only') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $headers[$name] = $this->policy();
        }

        foreach ($headers as $header => $value) {
            $response->headers->set($header, $value);
        }

        return $response;
    }

    private function policy(): string
    {
        $dev = $this->viteDevServer();       // e.g. http://localhost:5173, or null
        $media = $this->mediaOrigin();       // S3/CDN later; null while same-origin
        // Nulls (no dev server, same-origin media) are simply left out.
        $extra = fn (?string ...$origins) => implode(' ', array_filter($origins));

        $directives = [
            'default-src' => "'self'",
            'script-src' => $extra("'self'", "'nonce-".Vite::cspNonce()."'", self::MIDTRANS, $dev),
            // 'unsafe-inline' for styles: UI components position popovers
            // with style="" attributes. Injected CSS is far less dangerous
            // than injected script, which stays locked down above.
            'style-src' => $extra("'self'", "'unsafe-inline'", $dev),
            'img-src' => $extra("'self'", 'data:', 'blob:', self::MIDTRANS, $media, $dev),
            'font-src' => $extra("'self'", 'data:', $dev),
            'connect-src' => $extra("'self'", self::MIDTRANS, $dev, $dev ? str_replace('http', 'ws', $dev) : null),
            'frame-src' => self::MIDTRANS,
            'frame-ancestors' => "'none'",
            'object-src' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
        ];

        if (app()->isProduction()) {
            $directives['upgrade-insecure-requests'] = '';
        }

        return collect($directives)->map(fn (string $v, string $k) => trim("{$k} {$v}"))->implode('; ');
    }

    /** The Vite dev server's origin while `npm run dev` runs, else null. */
    private function viteDevServer(): ?string
    {
        if (! Vite::isRunningHot()) {
            return null;
        }

        $url = trim((string) @file_get_contents(Vite::hotFile()));

        return $this->origin($url);
    }

    /** Where product photos are served from, if not from this site. */
    private function mediaOrigin(): ?string
    {
        $origin = $this->origin(ProductImage::disk()->url('x'));

        return $origin === $this->origin(config()->string('app.url')) ? null : $origin;
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
