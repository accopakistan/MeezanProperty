<?php

use App\Enums\DealerStatus;
use App\Models\Property;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('sends security headers with a strict script policy', function () {
    $response = $this->get('/')->assertOk();

    expect($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Permissions-Policy'))->toContain('geolocation=(self)')
        ->and($response->headers->get('Content-Security-Policy'))
        ->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]{20,}';/")
        ->toContain("frame-ancestors 'self'")
        ->toContain("object-src 'none'")
        ->toContain('https://tile.openstreetmap.org')
        ->not->toContain('unsafe-eval')
        ->not->toMatch("/script-src[^;]*'unsafe-inline'/")
        // Plain HTTP outside production: no HSTS, so local development keeps working.
        ->and($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('tags only the page’s own scripts with a fresh nonce per request', function () {
    $first = $this->get('/');
    $second = $this->get('/');
    preg_match("/'nonce-([^']+)'/", $first->headers->get('Content-Security-Policy'), $a);
    preg_match("/'nonce-([^']+)'/", $second->headers->get('Content-Security-Policy'), $b);

    expect($a[1])->not->toBe($b[1]);
    // Every executable <script> on the page carries the nonce; JSON-LD is data, not script.
    preg_match_all('/<script(?![^>]*application\/ld\+json)[^>]*>/', $first->getContent(), $tags);
    foreach ($tags[0] as $tag) {
        expect($tag)->toContain('nonce="'.$a[1].'"');
    }
});

it('keeps headers a controller set deliberately and can switch the policy off', function () {
    $dealer = User::factory()->dealer()->create();
    $this->actingAs($dealer)->get(route('dealer.performance.export'))->assertHeader('X-Frame-Options', 'SAMEORIGIN');

    config(['meezan.security.csp' => false]);
    expect($this->get('/')->headers->has('Content-Security-Policy'))->toBeFalse();
});

it('renders pages without inline event handlers, which the policy would block', function () {
    $dealer = User::factory()->dealer()->create();
    $property = Property::factory()->forDealer($dealer)->create();

    foreach (['/', '/properties', route('properties.show', $property)] as $url) {
        expect($this->get($url)->getContent())->not->toMatch('/\son(click|submit|change|load|error)=/i');
    }
    foreach ([route('dealer.properties.edit', $property), route('dealer.properties.index'), route('dealer.verification')] as $url) {
        expect($this->actingAs($dealer)->get($url)->getContent())->not->toMatch('/\son(click|submit|change|load|error)=/i');
    }
});

it('shows branded error pages without debug details when debug is off', function () {
    config(['app.debug' => false]);
    Route::get('/__test-crash', fn () => throw new RuntimeException('secret internal detail'))->middleware('web');

    $this->get('/__test-crash')->assertStatus(500)->assertSee('Something went wrong')->assertDontSee('secret internal detail')->assertDontSee('RuntimeException');
    $this->get('/no-such-page-here')->assertNotFound()->assertSee('Page not found')->assertSee('Search properties');
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden()->assertSee('Access denied');
});

it('keeps database queries per page within budget as listings grow', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    $admin = User::factory()->admin()->create();
    $count = function (string $url, ?User $user = null): int {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        ($user ? $this->actingAs($user) : $this)->get($url)->assertOk();
        app('events')->forget(QueryExecuted::class);

        return $queries;
    };
    $seed = fn (int $n) => Property::factory()->forDealer($dealer)->count($n)->create()
        ->each(fn ($p) => $p->images()->create(['path' => 'x.webp', 'sort_order' => 1]));

    $seed(3);
    $small = ['/properties' => $count('/properties'), '/map' => $count('/map'), '/dealer/properties' => $count('/dealer/properties', $dealer)];
    $seed(20);
    $large = ['/properties' => $count('/properties'), '/map' => $count('/map'), '/dealer/properties' => $count('/dealer/properties', $dealer)];

    // No N+1: more listings must not mean more queries. (The first request can
    // run one-off setup queries, so later counts may be lower, never higher.)
    foreach ($small as $url => $queries) {
        expect($large[$url])->toBeLessThanOrEqual($queries, "{$url} runs more queries as listings grow");
    }

    // Budgets with headroom over today's counts (Phase 5 measurement).
    $budgets = ['/' => 20, '/properties' => 20, '/map' => 25, '/agents' => 10, '/areas' => 10];
    foreach ($budgets as $url => $max) {
        expect($count($url))->toBeLessThanOrEqual($max, "{$url} exceeded its query budget");
    }
    expect($count('/dealer/dashboard', $dealer))->toBeLessThanOrEqual(30)
        ->and($count('/dealer/performance', $dealer))->toBeLessThanOrEqual(45)
        ->and($count('/admin', $admin))->toBeLessThanOrEqual(45);
});

it('reports production readiness and fails on unsafe settings', function () {
    config(['app.debug' => true, 'mail.default' => 'log']);

    $this->artisan('meezan:production-check')
        ->expectsOutputToContain('APP_DEBUG is off')
        ->expectsOutputToContain('Outgoing mail is configured')
        ->assertFailed();
});

it('drains the queue from the scheduler only when asked to', function () {
    $events = fn () => collect(app(Schedule::class)->events())->map->command->filter()->implode(' ');

    expect($events())->not->toContain('queue:work');
});
