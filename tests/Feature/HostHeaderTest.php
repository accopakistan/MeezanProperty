<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/*
 * A faked Host must never reach a generated link: a poisoned password-reset
 * email sends the victim's token to the attacker's site.
 *
 * A full URL in get() and post() sets the request's Host header, which is how
 * these tests fake it (Symfony's Request::create overwrites a Host set by
 * withHeader).
 */

const FOREIGN_HOST = 'evil.example';

// Trusted hosts and proxies are static on the Request, so they outlive a test.
$clearRequestState = function () {
    Request::setTrustedHosts([]);
    Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
    TrustProxies::flushState();
};

beforeEach($clearRequestState);
afterEach($clearRequestState);

/**
 * Applies TRUSTED_PROXIES the way a real boot does.
 */
function trustProxiesFromConfig(string $proxies): void
{
    config(['meezan.security.trusted_proxies' => $proxies]);

    app()->getProvider(AppServiceProvider::class)->boot();
}

it('refuses a page requested under a foreign Host', function () {
    $response = $this->get('http://'.FOREIGN_HOST.'/');

    $response->assertBadRequest();
    expect($response->getContent())->not->toContain(FOREIGN_HOST);
});

it('sends no password reset and writes no token for a foreign Host', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('http://'.FOREIGN_HOST.'/forgot-password', ['email' => $user->email])->assertBadRequest();

    Notification::assertNothingSent();
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});

it('rejects a Host that only contains or extends the real one', function (string $host) {
    config(['app.url' => 'https://meezanproperty.com']);

    $this->get('http://'.$host.'/')->assertBadRequest();
})->with([
    'a subdomain' => 'evil.meezanproperty.com',
    'a longer domain' => 'meezanproperty.com.evil.example',
    'a prefix' => 'evilmeezanproperty.com',
]);

it('still serves the app under local addresses in local and testing', function (string $host) {
    $this->get("http://{$host}/")->assertOk();
})->with(['localhost', '127.0.0.1', '[::1]']);

it('builds a reset link on the real host', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('http://localhost/forgot-password', ['email' => $user->email])->assertRedirect();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $link = $notification->toMail($user)->actionUrl;

        return str_starts_with($link, 'http://localhost/reset-password/');
    });
});

it('in production trusts only the APP_URL host and its www form', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.url' => 'https://meezanproperty.com']);

    $this->get('https://meezanproperty.com/')->assertOk()
        ->assertSee('<link rel="canonical" href="https://meezanproperty.com">', false);
    // The www form is trusted, then sent permanently to the main address with its path and query.
    $this->get('https://www.meezanproperty.com/properties?purpose=rent')->assertStatus(301)->assertRedirect('https://meezanproperty.com/properties?purpose=rent');
    $this->get('https://localhost/')->assertBadRequest();
    $this->get('https://'.FOREIGN_HOST.'/')->assertBadRequest();
});

it('fails closed in production when APP_URL has no usable host', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.url' => 'meezanproperty.com']);

    $this->get('https://meezanproperty.com/')->assertBadRequest();
});

it('ignores X-Forwarded-Host even from a trusted proxy', function (string $proxies) {
    Notification::fake();
    $user = User::factory()->create();
    trustProxiesFromConfig($proxies);

    $headers = ['X-Forwarded-Host' => FOREIGN_HOST];

    $page = $this->withHeaders($headers)->get('http://localhost/');
    $page->assertOk()->assertDontSee(FOREIGN_HOST, false);

    $this->withHeaders($headers)->post('http://localhost/forgot-password', ['email' => $user->email])->assertRedirect();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        return ! str_contains($notification->toMail($user)->actionUrl, FOREIGN_HOST);
    });
})->with(['every caller' => '*', 'a listed proxy' => '127.0.0.1']);

it('ignores forwarded port and prefix from a trusted proxy', function () {
    trustProxiesFromConfig('127.0.0.1');

    $this->withHeaders(['X-Forwarded-Port' => '8443', 'X-Forwarded-Prefix' => '/evil'])->get('http://localhost/')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="http://localhost">', false);
});

it('keeps forwarded client IP and HTTPS from a listed proxy, and only from that proxy', function () {
    trustProxiesFromConfig('127.0.0.1');
    $forwarded = ['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'];

    $this->withHeaders($forwarded)->get('http://localhost/')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://localhost">', false);
    expect(request()->ip())->toBe('203.0.113.9');

    // The same headers from an address that is not the listed proxy change nothing.
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->withHeaders($forwarded)->get('http://localhost/')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="http://localhost">', false);
    expect(request()->ip())->toBe('198.51.100.7');
});
