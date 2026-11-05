<?php

use App\Http\Middleware\EnsureEmailIsVerifiedWhenRequired;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustAppHostsOnly;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // First in the stack, so a foreign Host gets no further than this.
        $middleware->prepend(TrustAppHostsOnly::class);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'verified.required' => EnsureEmailIsVerifiedWhenRequired::class,
        ]);

        // WhatsApp webhooks are authenticated by their HMAC signature instead.
        $middleware->validateCsrfTokens(except: ['webhooks/whatsapp', 'webhooks/payments/*']);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route(auth()->user()->role->homeRoute()));

        $middleware->web(append: [SecurityHeaders::class]);
        // Trusted proxies are applied in AppServiceProvider from config, so they
        // still work when the configuration is cached.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
