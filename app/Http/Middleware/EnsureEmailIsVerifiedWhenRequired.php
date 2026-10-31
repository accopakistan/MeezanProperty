<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's "verified" check, switched on by MEEZAN_REQUIRE_EMAIL_VERIFICATION.
 * Off by default so nobody is locked out while outgoing mail is not set up.
 * Admins are never blocked.
 */
class EnsureEmailIsVerifiedWhenRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (config('meezan.require_verified_email')
            && $user instanceof MustVerifyEmail
            && ! $user->hasVerifiedEmail()
            && ! $user->isAdmin()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Please confirm your email address first.'], 409)
                : redirect()->route('verification.notice')->with('error', 'Please confirm your email address to continue.');
        }

        return $next($request);
    }
}
