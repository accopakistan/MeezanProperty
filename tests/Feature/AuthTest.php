<?php

use App\Enums\DealerStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;

it('registers a buyer', function () {
    $this->post('/register', [
        'name' => 'Ali Raza',
        'email' => 'ali@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        'terms' => '1',
    ])->assertRedirect(route('account.dashboard'));

    $user = User::sole();
    expect($user->role)->toBe(Role::Buyer)->and($user->password)->not->toBe('secret-password');
    $this->assertAuthenticatedAs($user);
});

it('cannot self-assign a role during registration', function () {
    $this->post('/register', [
        'name' => 'Sneaky',
        'email' => 'sneaky@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        'terms' => '1',
        'role' => 'admin',
        'status' => 'active',
    ]);

    expect(User::sole()->role)->toBe(Role::Buyer);
});

it('registers a dealer whose verification starts pending', function () {
    $this->post('/dealer/register', [
        'name' => 'Imran Ahmed',
        'email' => 'imran@agency.pk',
        'phone' => '0321 5555555',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        'business_type' => 'agency',
        'agency_name' => 'Ahmed Estate',
        'city' => 'Lahore',
        'areas' => [area('Gulberg')->id, area('DHA Phase 6')->id],
        'terms' => '1',
    ])->assertRedirect(route('dealer.dashboard'));

    $dealer = User::sole();
    expect($dealer->role)->toBe(Role::Dealer)
        ->and($dealer->dealerProfile->verification_status)->toBe(DealerStatus::Pending)
        ->and($dealer->dealerProfile->areas)->toHaveCount(2)
        ->and(AuditLog::where('action', 'dealer.registered')->exists())->toBeTrue();
});

it('requires operating areas for dealers', function () {
    $this->post('/dealer/register', [
        'name' => 'Imran', 'email' => 'i@a.pk', 'phone' => '03215555555',
        'password' => 'secret-password', 'password_confirmation' => 'secret-password',
        'business_type' => 'individual', 'agency_name' => 'X', 'city' => 'Lahore', 'terms' => '1',
    ])->assertSessionHasErrors('areas');
});

it('signs users in and sends them to their area', function (string $factoryState, string $route) {
    $factory = User::factory();
    $user = ($factoryState === 'buyer' ? $factory : $factory->{$factoryState}())->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route($route));
})->with([
    ['buyer', 'account.dashboard'],
    ['dealer', 'dealer.dashboard'],
    ['admin', 'admin.dashboard'],
]);

it('rejects wrong passwords and suspended accounts', function () {
    $user = User::factory()->create();
    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->assertGuest();

    $suspended = User::factory()->suspended()->create();
    $this->post('/login', ['email' => $suspended->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('throttles repeated failed sign-ins', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Too many sign-in attempts');
    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});
