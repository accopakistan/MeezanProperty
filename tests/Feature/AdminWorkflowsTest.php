<?php

use App\Enums\DealerStatus;
use App\Enums\LeadStatus;
use App\Enums\PropertyStatus;
use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use App\Notifications\ListingReviewed;
use App\Services\Leads\LeadRouter;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Site Admin']);
});

it('searches and filters users', function () {
    User::factory()->create(['name' => 'Zainab Searchable', 'email' => 'zainab@example.com']);
    User::factory()->dealer()->create(['name' => 'Dealer Person']);
    User::factory()->create(['name' => 'Suspended Buyer', 'status' => 'suspended']);

    $this->actingAs($this->admin)->get(route('admin.users.index', ['q' => 'zainab']))
        ->assertOk()->assertSee('Zainab Searchable')->assertDontSee('Dealer Person');
    $this->actingAs($this->admin)->get(route('admin.users.index', ['role' => 'dealer']))
        ->assertSee('Dealer Person')->assertDontSee('Zainab Searchable');
    $this->actingAs($this->admin)->get(route('admin.users.index', ['status' => 'suspended']))
        ->assertSee('Suspended Buyer')->assertDontSee('Zainab Searchable');
});

it('reactivates a suspended user and records both decisions', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->patch(route('admin.users.status', $user), ['status' => 'suspended']);
    $this->actingAs($this->admin)->patch(route('admin.users.status', $user), ['status' => 'active']);

    expect($user->fresh()->status)->toBe('active')
        ->and(AuditLog::where('auditable_id', $user->id)->pluck('action')->all())->toContain('user.suspended', 'user.reactivated');
});

it('stops an admin from suspending their own account', function () {
    $this->actingAs($this->admin)->patch(route('admin.users.status', $this->admin), ['status' => 'suspended'])->assertStatus(422);

    expect($this->admin->fresh()->status)->toBe('active');
});

it('rejects a pending dealer with a recorded reason shown to the dealer', function () {
    $dealer = User::factory()->dealer()->create();
    $profile = $dealer->dealerProfile;

    $this->actingAs($this->admin)->patch(route('admin.dealers.verification', $profile), ['decision' => 'reject'])->assertSessionHasErrors('reason');
    $this->actingAs($this->admin)->patch(route('admin.dealers.verification', $profile), ['decision' => 'reject', 'reason' => 'Registration number could not be confirmed']);

    expect($profile->fresh())->verification_status->toBe(DealerStatus::Rejected)
        ->rejection_reason->toBe('Registration number could not be confirmed');
    $this->actingAs($this->admin)->get(route('admin.dealers.show', $profile))->assertSee('Dealer rejected')->assertSee('Registration number could not be confirmed');
    $this->actingAs($dealer)->get(route('dealer.dashboard'))->assertSee('Registration number could not be confirmed');
});

it('unpublishes a live listing, tells the dealer and hides it from the public', function () {
    Notification::fake();
    $property = Property::factory()->create();

    $this->actingAs($this->admin)->patch(route('admin.properties.moderate', $property), ['decision' => 'unpublish', 'reason' => 'Price looks wrong']);

    expect($property->fresh()->status)->toBe(PropertyStatus::Draft);
    // Admins may still preview it; the public may not.
    auth()->logout();
    $this->get(route('properties.show', $property))->assertNotFound();
    Notification::assertSentTo($property->dealer, ListingReviewed::class, fn ($n) => $n->decision === 'unpublish' && $n->reason === 'Price looks wrong');
});

it('updates a lead’s status with history and keeps leads away from dealers and buyers', function () {
    $property = Property::factory()->create();
    $inquiry = new Inquiry(['name' => 'Buyer', 'email' => 'b@example.com', 'phone' => '03001112222', 'message' => 'Interested in this house.']);
    $inquiry->forceFill(['property_id' => $property->id, 'recipient_id' => $property->user_id])->save();
    $lead = app(LeadRouter::class)->routeInquiry($inquiry);

    $this->actingAs($this->admin)->patch(route('admin.leads.status', $lead), ['status' => 'qualified'])->assertRedirect();
    $this->actingAs($this->admin)->patch(route('admin.leads.status', $lead), ['status' => 'not-a-status'])->assertSessionHasErrors('status');

    expect($lead->fresh()->status)->toBe(LeadStatus::Qualified)
        ->and(AuditLog::where('action', 'lead.status_changed')->where('auditable_id', $lead->id)->exists())->toBeTrue();
    $this->actingAs($this->admin)->get(route('admin.leads.show', $lead))->assertOk()->assertSee($property->title);

    $this->actingAs($property->dealer)->get(route('admin.leads.show', $lead))->assertForbidden();
    $this->actingAs(User::factory()->create())->patch(route('admin.leads.status', $lead), ['status' => 'closed'])->assertForbidden();
    expect(Lead::find($lead->id)->status)->toBe(LeadStatus::Qualified);
});

it('filters the audit log by action', function () {
    $user = User::factory()->create();
    $this->actingAs($this->admin)->patch(route('admin.users.status', $user), ['status' => 'suspended']);
    $this->actingAs($this->admin)->patch(route('admin.properties.moderate', Property::factory()->status(PropertyStatus::Pending)->create()), ['decision' => 'approve']);

    $this->actingAs($this->admin)->get(route('admin.audit-logs', ['action' => 'user']))
        ->assertOk()->assertSee('Site Admin');
    expect(AuditLog::where('action', 'like', 'user.%')->count())->toBe(1)
        ->and(AuditLog::where('action', 'property.approved')->count())->toBe(1);
});
