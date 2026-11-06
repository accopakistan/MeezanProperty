<?php

namespace App\Providers;

use App\Services\Agents\AgentEvents;
use App\Services\Agents\AiProvider;
use App\Services\Ai\LlmRequirementParser;
use App\Services\Ai\RequirementParser;
use App\Services\Ai\RulesRequirementParser;
use App\Services\Billing\Gateways\GatewayManager;
use App\Services\InvestmentIndex\IndexBrowser;
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
        // One provider manager for the whole application (it holds the current chat channel).
        $this->app->singleton(AiProvider::class);

        // "rules" (default) is fully deterministic. "llm" asks the configured language
        // model to extract criteria and falls back to the rules on any failure.
        $this->app->bind(RequirementParser::class, fn ($app) => config('meezan.ai.driver') === 'llm' && $app->make(AiProvider::class)->available()
            ? $app->make(LlmRequirementParser::class)
            : $app->make(RulesRequirementParser::class));

        $this->app->singleton(GatewayManager::class);
        // One Index browser per request (or queued job), so the published run is looked up once.
        $this->app->scoped(IndexBrowser::class);
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

            // Only the client IP and HTTPS. Clients can pass the forwarded host, port
            // and prefix through a CDN, and those would all end up in generated links.
            TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
        }

        Paginator::defaultView('components.pagination');

        // Listing, dealer and lead events that queue AI agent work (off unless MEEZAN_AGENT_EVENTS=true).
        AgentEvents::register();

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
