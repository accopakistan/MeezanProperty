<?php

use App\Enums\DealerStatus;
use App\Enums\PropertyStatus;
use App\Models\BuyerRequirement;
use App\Models\Inquiry;
use App\Models\Property;
use App\Models\User;
use App\Notifications\ContactMessageReceived;
use App\Notifications\LeadAssigned;
use App\Notifications\ListingReviewed;
use App\Notifications\ListingSubmittedForReview;
use App\Notifications\NewInquiryReceived;
use App\Notifications\QueuedMailNotification;
use App\Notifications\SavedSearchMatches;
use App\Services\Leads\LeadRouter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

function inquiryForm(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ayesha Khan',
        'email' => 'ayesha@example.com',
        'phone' => '0300 1234567',
        'message' => 'Is this house still available? I would like to visit this weekend.',
        'preferred_contact' => 'whatsapp',
        'consent' => '1',
    ], $overrides);
}

it('emails the listing dealer about a new inquiry without putting buyer contact details in the email', function () {
    Notification::fake();
    $property = Property::factory()->create(['title' => 'Corner House In Gulberg']);

    $this->post(route('inquiries.store', $property), inquiryForm())->assertSessionHasNoErrors();

    Notification::assertSentTo($property->dealer, NewInquiryReceived::class, function ($notification, $channels) use ($property) {
        $mail = $notification->toMail($property->dealer);
        $text = implode(' ', array_merge($mail->introLines, $mail->outroLines));

        return $channels === ['database', 'mail']
            && str_contains($mail->subject, $property->reference)
            && str_contains($text, 'Ayesha Khan')
            && str_contains($text, 'WhatsApp')
            && ! str_contains($text, '0300 1234567')
            && ! str_contains($text, 'ayesha@example.com')
            && $mail->actionUrl === route('dealer.leads');
    });
});

it('keeps only the in-app copy for a dealer who switched emails off, and notifies suspended users not at all', function () {
    Notification::fake();
    $quiet = Property::factory()->create();
    $quiet->dealer->forceFill(['notify_account_email' => false])->save();
    $suspended = Property::factory()->create();
    $suspended->dealer->forceFill(['status' => 'suspended'])->save();

    $this->post(route('inquiries.store', $quiet), inquiryForm());
    $this->post(route('inquiries.store', $suspended), inquiryForm(['email' => 'other@example.com']));

    // Email off: the notification centre still shows it. Suspended: via() returns
    // no channels, so the fake records nothing.
    Notification::assertSentTo($quiet->dealer, NewInquiryReceived::class, fn ($n, $channels) => $channels === ['database']);
    Notification::assertNotSentTo($suspended->dealer, NewInquiryReceived::class);
    expect($quiet->dealer->fresh()->wantsAccountEmail())->toBeFalse()
        ->and($suspended->dealer->fresh()->wantsAccountEmail())->toBeFalse();
});

it('emails dealers who receive a requirement lead or a reassigned lead', function () {
    Notification::fake();
    $gulberg = area('Gulberg');
    $dealer = User::factory()->dealer(DealerStatus::Verified, [$gulberg->id])->create();
    $requirement = new BuyerRequirement(['purpose' => 'sale', 'type' => 'house', 'city' => 'Lahore', 'area_ids' => [$gulberg->id], 'max_price' => 60_000_000, 'bedrooms' => 4]);
    $requirement->user_id = User::factory()->create()->id;
    $requirement->save();

    $lead = app(LeadRouter::class)->routeRequirement($requirement, true);

    Notification::assertSentTo($dealer, LeadAssigned::class, function ($notification) use ($dealer) {
        $text = implode(' ', $notification->toMail($dealer)->introLines);

        return str_contains($text, 'Gulberg') && str_contains($text, '6 Cr') && str_contains($text, '4+ bedrooms');
    });

    $other = User::factory()->dealer(DealerStatus::Verified)->create();
    app(LeadRouter::class)->reassign($lead, $other, User::factory()->admin()->create(), 'Original dealer unavailable');
    Notification::assertSentTo($other, LeadAssigned::class);
});

it('tells admins about listings submitted for review, including possible duplicates', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $inactiveAdmin = User::factory()->admin()->create(['status' => 'suspended']);
    $dealer = User::factory()->dealer()->create();
    $draft = Property::factory()->forDealer($dealer)->status(PropertyStatus::Draft)->create();

    $this->actingAs($dealer)->post(route('dealer.properties.submit', $draft))->assertRedirect();

    Notification::assertSentTo($admin, ListingSubmittedForReview::class, fn ($n) => $n->toMail($admin)->actionUrl === route('admin.properties.show', $draft));
    Notification::assertNotSentTo($inactiveAdmin, ListingSubmittedForReview::class);
});

it('tells the dealer when a listing is approved or rejected, but not when it is archived', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $pending = Property::factory()->status(PropertyStatus::Pending)->create();
    $other = Property::factory()->status(PropertyStatus::Pending)->create();
    $archived = Property::factory()->create();

    $this->actingAs($admin)->patch(route('admin.properties.moderate', $pending), ['decision' => 'approve']);
    $this->actingAs($admin)->patch(route('admin.properties.moderate', $other), ['decision' => 'reject', 'reason' => 'Photos do not match the property']);
    $this->actingAs($admin)->patch(route('admin.properties.moderate', $archived), ['decision' => 'archive']);

    Notification::assertSentTo($pending->dealer, ListingReviewed::class, fn ($n) => $n->decision === 'approve' && str_contains($n->toMail($pending->dealer)->subject, 'live'));
    Notification::assertSentTo($other->dealer, ListingReviewed::class, fn ($n) => in_array('Reviewer note: Photos do not match the property', $n->toMail($other->dealer)->introLines, true));
    Notification::assertNotSentTo($archived->dealer, ListingReviewed::class);
});

it('sends contact messages to the company address, or to admins when none is set', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $form = ['name' => 'Bilal', 'email' => 'bilal@example.com', 'phone' => '03001112222', 'message' => 'Do you list properties in Bahria Orchard?'];
    config(['meezan.contact.email' => null]);

    $this->post(route('pages.contact.send'), $form)->assertSessionHasNoErrors();
    Notification::assertSentTo($admin, ContactMessageReceived::class, fn ($n) => $n->toMail($admin)->replyTo[0][0] === 'bilal@example.com');

    config(['meezan.contact.email' => 'hello@meezanproperty.com']);
    $this->post(route('pages.contact.send'), $form + ['message' => 'A second, different question about plots.']);
    Notification::assertSentOnDemand(ContactMessageReceived::class, fn ($n, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'hello@meezanproperty.com');
});

it('queues every service email and sends it only after the transaction commits', function () {
    foreach ([NewInquiryReceived::class, LeadAssigned::class, ListingSubmittedForReview::class, ListingReviewed::class, ContactMessageReceived::class, SavedSearchMatches::class] as $class) {
        expect(is_subclass_of($class, ShouldQueue::class))->toBeTrue($class.' should be queued');
    }

    $notification = new NewInquiryReceived(new Inquiry);
    expect($notification)->toBeInstanceOf(QueuedMailNotification::class)
        ->and($notification->afterCommit)->toBeTrue()
        ->and($notification->tries)->toBe(3);
});

it('lets dealers switch account emails off and on from their profile', function () {
    $dealer = User::factory()->dealer()->create();
    $profile = $dealer->dealerProfile;
    $form = ['name' => $dealer->name, 'phone' => '03001234567', 'agency_name' => $profile->agency_name, 'areas' => [area('Gulberg')->id]];

    $this->actingAs($dealer)->put(route('dealer.profile.update'), $form + ['notify_account_email' => '0'])->assertSessionHasNoErrors();
    expect($dealer->fresh()->notify_account_email)->toBeFalse();

    $this->actingAs($dealer)->put(route('dealer.profile.update'), $form + ['notify_account_email' => '1']);
    expect($dealer->fresh()->notify_account_email)->toBeTrue();

    // A form without the field leaves the choice alone.
    $this->actingAs($dealer)->put(route('dealer.profile.update'), $form);
    expect($dealer->fresh()->notify_account_email)->toBeTrue();
});

it('only sends saved-search alert emails to confirmed addresses', function () {
    config(['mail.default' => 'smtp']);
    Notification::fake();
    $confirmed = User::factory()->create(['notify_email' => true]);
    $unconfirmed = User::factory()->unverified()->create(['notify_email' => true]);
    foreach ([$confirmed, $unconfirmed] as $buyer) {
        $search = $buyer->savedSearches()->make(['name' => 'Houses', 'filters' => ['purpose' => 'sale'], 'alert_frequency' => 'daily']);
        $search->forceFill(['created_at' => now()->subDays(2)])->save();
    }
    Property::factory()->create(['published_at' => now()->subHour()]);

    $this->artisan('meezan:send-search-alerts')->assertSuccessful();

    Notification::assertSentTo($confirmed, SavedSearchMatches::class);
    Notification::assertNotSentTo($unconfirmed, SavedSearchMatches::class);
});
