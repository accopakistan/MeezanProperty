<?php

use App\Models\User;
use App\Notifications\VerifyEmailAddress;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function verificationUrl(User $user): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
}

it('sends a queued confirmation email when a buyer registers', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Sana Malik', 'email' => 'sana@example.com', 'phone' => '03001234567',
        'password' => 'a-long-Password-1', 'password_confirmation' => 'a-long-Password-1', 'terms' => '1',
    ])->assertRedirect();

    $user = User::where('email', 'sana@example.com')->sole();
    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmailAddress::class, fn ($n) => $n->afterCommit === true);
});

it('confirms the address from the signed link, once', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(verificationUrl($user))->assertRedirect(route('account.dashboard'));
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    $this->actingAs($user)->get(verificationUrl($user))->assertRedirect(route('account.dashboard'));
});

it('rejects tampered or unsigned links', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden();
    $this->actingAs($user)->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('someone@else.com')]))->assertForbidden();
    $this->actingAs(User::factory()->unverified()->create())->get(verificationUrl($user))->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('shows a reminder banner with a resend button to unconfirmed users only', function () {
    Notification::fake();
    $unconfirmed = User::factory()->unverified()->create();

    $this->actingAs($unconfirmed)->get(route('account.dashboard'))->assertSee('Please confirm your email address');
    $this->actingAs(User::factory()->create())->get(route('account.dashboard'))->assertDontSee('Please confirm your email address');

    $this->actingAs($unconfirmed)->post(route('verification.send'))->assertSessionHas('status');
    Notification::assertSentTo($unconfirmed, VerifyEmailAddress::class);
});

it('does not block unconfirmed users while the requirement is switched off', function () {
    config(['meezan.require_verified_email' => false]);
    $dealer = User::factory()->dealer()->unverified()->create();

    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['action' => 'draft']))
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($dealer->properties()->count())->toBe(1);
});

it('requires a confirmed email for listings and shared requirements when switched on', function () {
    config(['meezan.require_verified_email' => true]);
    $dealer = User::factory()->dealer()->unverified()->create();
    $buyer = User::factory()->unverified()->create();

    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['action' => 'draft']))
        ->assertRedirect(route('verification.notice'));
    $this->actingAs($buyer)->post(route('account.requirements.store'), ['purpose' => 'sale', 'city' => 'Lahore'])
        ->assertRedirect(route('verification.notice'));
    expect($dealer->properties()->count())->toBe(0);

    // Reading pages still works, and confirmed users are not affected.
    $this->actingAs($dealer)->get(route('dealer.properties.index'))->assertOk();
    $verified = User::factory()->dealer()->create();
    $this->actingAs($verified)->post(route('dealer.properties.store'), listingPayload(['action' => 'draft']))->assertSessionHasNoErrors();
    expect($verified->properties()->count())->toBe(1);
});

it('never blocks admins and sends confirmed users away from the notice page', function () {
    config(['meezan.require_verified_email' => true]);
    $admin = User::factory()->admin()->unverified()->create();

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Please confirm your email address');
    $this->actingAs(User::factory()->create())->get(route('verification.notice'))->assertRedirect(route('account.dashboard'));
    $this->actingAs(User::factory()->unverified()->create())->get(route('verification.notice'))->assertOk()->assertSee('Send a new link');
});
