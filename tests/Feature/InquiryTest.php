<?php

use App\Enums\LeadStatus;
use App\Enums\PropertyStatus;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;

function inquiryPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Sara Khan',
        'email' => 'sara@example.com',
        'phone' => '0300 1234567',
        'message' => 'I would like to visit this property this weekend.',
        'preferred_contact' => 'whatsapp',
        'consent' => '1',
    ], $overrides);
}

it('stores an inquiry and routes it to the listing dealer', function () {
    $property = Property::factory()->create();

    $this->post(route('inquiries.store', $property), inquiryPayload())
        ->assertRedirect()
        ->assertSessionHas('status');

    $inquiry = Inquiry::sole();
    expect($inquiry->recipient_id)->toBe($property->user_id)
        ->and($inquiry->property_id)->toBe($property->id)
        ->and($inquiry->ip_hash)->not->toBeNull();

    $lead = Lead::sole();
    expect($lead->status)->toBe(LeadStatus::Assigned)
        ->and($lead->assignments()->sole()->dealer_id)->toBe($property->user_id);
});

it('validates inquiry input', function () {
    $property = Property::factory()->create();

    $this->post(route('inquiries.store', $property), inquiryPayload(['email' => 'not-an-email', 'phone' => '12', 'message' => 'hi', 'consent' => null]))
        ->assertSessionHasErrors(['email', 'phone', 'message', 'consent']);

    expect(Inquiry::count())->toBe(0);
});

it('rejects bot submissions that fill the honeypot', function () {
    $property = Property::factory()->create();

    $this->post(route('inquiries.store', $property), inquiryPayload(['website' => 'http://spam.example']))
        ->assertSessionHasErrors('website');

    expect(Inquiry::count())->toBe(0);
});

it('prevents duplicate inquiries within the window', function () {
    $property = Property::factory()->create();

    $this->post(route('inquiries.store', $property), inquiryPayload());
    $this->post(route('inquiries.store', $property), inquiryPayload())->assertSessionHas('status', fn ($m) => str_contains($m, 'already received'));

    expect(Inquiry::count())->toBe(1)->and(Lead::count())->toBe(1);
});

it('does not accept inquiries for unpublished listings', function () {
    $property = Property::factory()->status(PropertyStatus::Pending)->create();

    $this->post(route('inquiries.store', $property), inquiryPayload())->assertNotFound();
});

it('links the inquiry to the signed-in buyer', function () {
    $buyer = User::factory()->create();
    $property = Property::factory()->create();

    $this->actingAs($buyer)->post(route('inquiries.store', $property), inquiryPayload());

    expect(Inquiry::sole()->user_id)->toBe($buyer->id);
    $this->actingAs($buyer)->get(route('account.inquiries'))->assertOk()->assertSee($property->title);
});
