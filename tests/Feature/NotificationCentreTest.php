<?php

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;
use App\Notifications\ListingReviewed;
use App\Notifications\NewInquiryReceived;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

function sendInquiryTo(Property $property): void
{
    test()->post(route('inquiries.store', $property), [
        'name' => 'Hamza', 'email' => uniqid().'@example.com', 'phone' => '0300 1234567',
        'message' => 'Is this still available for a visit on Saturday?', 'preferred_contact' => 'call', 'consent' => '1',
    ]);
}

it('stores an in-app entry for the dealer and shows an unread count in the portal', function () {
    $property = Property::factory()->create(['title' => 'Bell Test House']);
    $dealer = $property->dealer;

    sendInquiryTo($property);

    $entry = $dealer->notifications()->sole();
    expect($entry->type)->toBe(NewInquiryReceived::class)
        ->and($entry->data)->toMatchArray(['title' => 'New inquiry from Hamza', 'url' => route('dealer.leads'), 'icon' => 'message'])
        ->and($entry->data['body'])->toContain($property->reference)
        ->and(json_encode($entry->data))->not->toContain('0300');

    $this->actingAs($dealer)->get(route('dealer.dashboard'))->assertSee('Notifications, 1 unread');
    $this->actingAs($dealer)->get(route('notifications.index'))->assertOk()->assertSee('New inquiry from Hamza')->assertSee('Mark all as read');
});

it('marks an entry read when opened and follows only internal links', function () {
    $property = Property::factory()->create();
    sendInquiryTo($property);
    $dealer = $property->dealer;
    $entry = $dealer->notifications()->sole();

    $this->actingAs($dealer)->get(route('notifications.open', $entry->id))->assertRedirect(route('dealer.leads'));
    expect($entry->fresh()->read_at)->not->toBeNull();

    // A stored link to another site is never followed.
    $dealer->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['title' => 'x', 'url' => 'https://evil.example/phish']]);
    $evil = $dealer->notifications()->where('type', 'test')->sole();
    $this->actingAs($dealer)->get(route('notifications.open', $evil->id))->assertRedirect(route('notifications.index'));
});

it('keeps each user’s notifications private', function () {
    $property = Property::factory()->create();
    sendInquiryTo($property);
    $entry = $property->dealer->notifications()->sole();
    $other = User::factory()->dealer()->create();

    $this->actingAs($other)->get(route('notifications.open', $entry->id))->assertNotFound();
    $this->actingAs($other)->get(route('notifications.index'))->assertDontSee('New inquiry from Hamza');
    auth()->logout();
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
});

it('marks everything read and filters unread', function () {
    $property = Property::factory()->create();
    sendInquiryTo($property);
    sendInquiryTo($property);
    $dealer = $property->dealer;
    expect($dealer->unreadNotifications()->count())->toBe(2);

    $this->actingAs($dealer)->get(route('notifications.index', ['filter' => 'unread']))->assertSee('Unread (2)');
    $this->actingAs($dealer)->post(route('notifications.read-all'))->assertSessionHas('status');

    expect($dealer->unreadNotifications()->count())->toBe(0);
    $this->actingAs($dealer)->get(route('notifications.index', ['filter' => 'unread']))->assertSee('You are all caught up');
});

it('gives admins and buyers a notification centre too', function () {
    $admin = User::factory()->admin()->create();
    $dealer = User::factory()->dealer()->create();
    $this->actingAs($dealer)->post(route('dealer.properties.submit', Property::factory()->forDealer($dealer)->status(PropertyStatus::Draft)->create()));

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('Notifications, 1 unread');
    $this->actingAs($admin)->get(route('notifications.index'))->assertSee('Listing to review');
    $this->actingAs(User::factory()->create())->get(route('notifications.index'))->assertOk()->assertSee('No notifications yet');
});

it('respects the in-app preference', function () {
    $property = Property::factory()->create();
    $property->dealer->forceFill(['notify_in_app' => false])->save();

    $property->dealer->notify(new ListingReviewed($property, 'approve'));

    expect($property->dealer->notifications()->count())->toBe(0);
});

it('prunes old notifications on schedule', function () {
    $dealer = User::factory()->dealer()->create();
    $make = fn (array $attrs) => DB::table('notifications')->insert($attrs + ['id' => (string) Str::uuid(), 'type' => 'test', 'notifiable_type' => $dealer->getMorphClass(), 'notifiable_id' => $dealer->id, 'data' => '{}']);
    $make(['read_at' => now()->subDays(100), 'created_at' => now()->subDays(100)]);
    $make(['read_at' => null, 'created_at' => now()->subDays(200)]);
    $make(['read_at' => now()->subDay(), 'created_at' => now()->subDays(10)]);
    $make(['read_at' => null, 'created_at' => now()->subDays(100)]);

    $event = collect(app(Schedule::class)->events())->first(fn ($e) => $e->description === 'prune-notifications');
    $event->run(app());

    expect(DB::table('notifications')->count())->toBe(2);
});
