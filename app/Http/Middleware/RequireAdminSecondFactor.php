<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin pages only with a second factor: a confirmed authenticator app
 * (2FA) or a passkey. Runs after `can:admin`, so it only ever concerns
 * admins.
 */
class RequireAdminSecondFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! config('security.admin_requires_second_factor') || $user === null || self::hasSecondFactor($user)) {
            return $next($request);
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => 'Halaman admin butuh verifikasi dua langkah. Aktifkan 2FA atau tambahkan passkey dulu.',
        ]);

        return to_route('security.edit');
    }

    public static function hasSecondFactor(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null || $user->passkeys()->exists();
    }
}
