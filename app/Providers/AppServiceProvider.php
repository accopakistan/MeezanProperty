<?php

namespace App\Providers;

use App\Services\Ai\LlmRequirementParser;
use App\Services\Ai\RequirementParser;
use App\Services\Ai\RulesRequirementParser;
use App\Services\Billing\Gateways\GatewayManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // "rules" (default) is fully deterministic. "llm" asks an OpenAI-compatible
        // model to extract criteria and falls back to the rules on any failure.
        $this->app->bind(RequirementParser::class, fn ($app) => config('meezan.ai.driver') === 'llm' && filled(config('services.openai.key'))
            ? $app->make(LlmRequirementParser::class)
            : $app->make(RulesRequirementParser::class));

        $this->app->singleton(GatewayManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Stored times are UTC; $time->localTime() shows them in Pakistan time (config meezan.timezone).
        Carbon::macro('localTime', fn () => $this->copy()->setTimezone(config('meezan.timezone')));

        // Lazy loading throws during development and tests; in production it is
        // allowed but logged, so a missed eager load is visible without an error page.
        Model::preventLazyLoading();
        if ($this->app->isProduction()) {
            Model::handleLazyLoadingViolationUsing(fn (Model $model, string $relation) => Log::warning('Lazy-loaded relation', [
                'model' => $model::class,
                'relation' => $relation,
                'url' => request()?->fullUrl(),
            ]));
        }

        if ($proxies = config('meezan.security.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        Paginator::defaultView('components.pagination');

        RateLimiter::for('inquiries', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perDay(40)->by($request->ip()),
        ]);

        RateLimiter::for('ai-finder', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('registrations', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        // Place search proxies OpenStreetMap Nominatim, which allows light use only.
        RateLimiter::for('location-search', fn (Request $request) => Limit::perMinute(15)->by($request->user()?->id ?: $request->ip()));
    }
}
