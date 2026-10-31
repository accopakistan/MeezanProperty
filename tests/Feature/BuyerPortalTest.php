<?php

use App\Enums\DealerStatus;
use App\Enums\LeadStatus;
use App\Models\BuyerRequirement;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;

function requirementPayload(array $overrides = []): array
{
    return array_merge([
        'purpose' => 'sale',
        'type' => 'house',
        'city' => 'Lahore',
        'area_ids' => [area('Gulberg')->id],
        'max_price' => 50000000,
        'min_size' => 8,
        'size_unit' => 'marla',
    ], $overrides);
}

it('restricts the buyer account to buyers', function () {
    $this->get('/account')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->dealer()->create())->get('/account')->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get('/account')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/account')->assertOk();
});

it('saves and unsaves a property', function () {
    $buyer = User::factory()->create();
    $property = Property::factory()->create();

    $this->actingAs($buyer)->postJson(route('properties.save', $property))->assertOk()->assertJson(['saved' => true]);
    $this->actingAs($buyer)->get(route('account.saved'))->assertSee($property->title);

    $this->actingAs($buyer)->postJson(route('properties.save', $property))->assertJson(['saved' => false]);
    expect($buyer->savedProperties()->count())->toBe(0);
});

it('asks guests to sign in before saving', function () {
    $property = Property::factory()->create();

    $this->postJson(route('properties.save', $property))->assertUnauthorized();
    $this->post(route('properties.save', $property))->assertRedirect(route('login'));
});

it('keeps a requirement private unless the buyer consents', function () {
    $buyer = User::factory()->create();
    User::factory()->dealer(DealerStatus::Verified, [area('Gulberg')->id])->create();

    $this->actingAs($buyer)->post(route('account.requirements.store'), requirementPayload())->assertRedirect();

    $requirement = BuyerRequirement::sole();
    expect($requirement->min_area_sqft)->toBe(1800)
        ->and(Lead::count())->toBe(0);
});

it('routes a consented requirement only to verified dealers in the requested areas', function () {
    $buyer = User::factory()->create();
    $match = User::factory()->dealer(DealerStatus::Verified, [area('Gulberg')->id])->create();
    $pending = User::factory()->dealer(DealerStatus::Pending, [area('Gulberg')->id])->create();
    $elsewhere = User::factory()->dealer(DealerStatus::Verified, [area('Bahria Town')->id])->create();

    $this->actingAs($buyer)->post(route('account.requirements.store'), requirementPayload(['share_with_dealers' => '1']));

    $lead = Lead::sole();
    expect($lead->status)->toBe(LeadStatus::Assigned)
        ->and($lead->assignments->pluck('dealer_id')->all())->toBe([$match->id]);

    // Re-saving is idempotent: no duplicate assignments.
    $this->actingAs($buyer)->put(route('account.requirements.update', BuyerRequirement::sole()), requirementPayload(['share_with_dealers' => '1']));
    expect(Lead::count())->toBe(1)->and($lead->assignments()->count())->toBe(1);

    $this->actingAs($match)->get('/dealer/leads?tab=requirements')->assertSee($buyer->email);
    $this->actingAs($pending)->get('/dealer/leads?tab=requirements')->assertDontSee($buyer->email);
    $this->actingAs($elsewhere)->get('/dealer/leads?tab=requirements')->assertDontSee($buyer->email);
});

it('flags a requirement for human review when no dealer is eligible', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->post(route('account.requirements.store'), requirementPayload(['share_with_dealers' => '1']));

    expect(Lead::sole()->status)->toBe(LeadStatus::Unrouted);
});

it('does not let a buyer open someone else’s requirement', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->post(route('account.requirements.store'), requirementPayload());

    $this->actingAs(User::factory()->create())
        ->get(route('account.requirements.matches', BuyerRequirement::sole()))
        ->assertNotFound();
});

it('recommends matching listings on the dashboard', function () {
    $buyer = User::factory()->create();
    Property::factory()->create(['title' => 'Good Gulberg Match', 'area_id' => area('Gulberg')->id, 'price' => 45000000]);
    Property::factory()->create(['title' => 'Too Expensive Mansion', 'area_id' => area('Gulberg')->id, 'price' => 90000000]);

    $this->actingAs($buyer)->post(route('account.requirements.store'), requirementPayload());

    $this->actingAs($buyer)->get('/account')->assertSee('Good Gulberg Match')->assertDontSee('Too Expensive Mansion');
});
