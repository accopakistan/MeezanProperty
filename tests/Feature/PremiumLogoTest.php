<?php

use App\Models\User;
use App\Support\Seo;

// The owner's final brand asset, used as supplied (the filename has spaces, so URLs encode them).
const PREMIUM_LOGO = 'images/logo/Meezan%20Property%20Premium%20Logo.png';

it('uses the premium logo file that exists on disk', function () {
    expect(public_path('images/logo/Meezan Property Premium Logo.png'))->toBeFile();
});

it('shows the premium logo in the header and footer of public pages', function (string $uri) {
    $html = $this->get($uri)->assertOk()->getContent();

    // Header (fetched first) and footer (on its own white background for the dark footer).
    expect(substr_count($html, PREMIUM_LOGO))->toBeGreaterThanOrEqual(2)
        ->and($html)->toContain('fetchpriority="high"')
        ->and($html)->toContain('border-radius:0.75rem')
        ->not->toContain('images/logo/meezan-logo.png')
        ->not->toContain('images/logo/meezan-logo-full-light.png');
})->with(['/', '/buy', '/rent']);

it('shows the premium logo on the sign-in and registration pages', function (string $uri) {
    expect($this->get($uri)->assertOk()->getContent())->toContain(PREMIUM_LOGO)
        ->not->toContain('images/logo/meezan-logo.png');
})->with(['/login', '/register']);

it('shows the premium logo in the admin and dealer dashboards', function () {
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))
        ->assertOk()->assertSee(PREMIUM_LOGO, false)->assertDontSee('images/logo/meezan-logo-light.png', false);

    $this->actingAs(User::factory()->dealer()->create())->get(route('dealer.dashboard'))
        ->assertOk()->assertSee(PREMIUM_LOGO, false);
});

it('shows the premium logo on error pages and in the organisation schema', function () {
    expect($this->get('/no-such-page-for-logo-test')->assertNotFound()->getContent())->toContain(PREMIUM_LOGO)
        ->and(Seo::organization()['logo'])->toEndWith(PREMIUM_LOGO);
});
