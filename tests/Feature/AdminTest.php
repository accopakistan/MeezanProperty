<?php

use App\Enums\DealerStatus;
use App\Enums\LeadStatus;
use App\Enums\PropertyStatus;
use App\Models\AuditLog;
use App\Models\BuyerRequirement;
use App\Models\DealerDocument;
use App\Models\Property;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Leads\LeadRouter;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

it('blocks non-admins from every admin page', function (string $uri) {
    $this->get($uri)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get($uri)->assertForbidden();
    $this->actingAs(User::factory()->dealer()->create())->get($uri)->assertForbidden();
})->with(['/admin', '/admin/users', '/admin/dealers', '/admin/properties', '/admin/leads', '/admin/conversations', '/admin/subscriptions', '/admin/payments', '/admin/analytics', '/admin/settings', '/admin/audit-logs']);

it('renders every admin page for admins', function (string $uri) {
    Property::factory()->count(2)->create();

    $this->actingAs($this->admin)->get($uri)->assertOk();
})->with(['/admin', '/admin/users', '/admin/dealers', '/admin/properties', '/admin/leads', '/admin/conversations', '/admin/subscriptions', '/admin/payments', '/admin/analytics', '/admin/settings', '/admin/audit-logs']);

it('shows real totals on the dashboard', function () {
    Property::factory()->count(2)->status(PropertyStatus::Pending)->create();

    $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSeeInOrder(['Property Approvals', '2']);
});

it('approves a pending listing and records it', function () {
    $property = Property::factory()->status(PropertyStatus::Pending)->create(['published_at' => null, 'expires_at' => null]);

    $this->actingAs($this->admin)->patch(route('admin.properties.moderate', $property), ['decision' => 'approve'])->assertRedirect();

    $property->refresh();
    expect($property->status)->toBe(PropertyStatus::Published)
        ->and($property->published_at)->not->toBeNull()
        ->and($property->expires_at->isFuture())->toBeTrue()
        ->and(AuditLog::where('action', 'property.approved')->where('user_id', $this->admin->id)->exists())->toBeTrue();
});

it('requires a reason to reject a listing', function () {
    $property = Property::factory()->status(PropertyStatus::Pending)->create();

    $this->actingAs($this->admin)->patch(route('admin.properties.moderate', $property), ['decision' => 'reject'])->assertSessionHasErrors('reason');
    $this->actingAs($this->admin)->patch(route('admin.properties.moderate', $property), ['decision' => 'reject', 'reason' => 'Photos missing'])->assertRedirect();

    expect($property->fresh())->status->toBe(PropertyStatus::Rejected)->rejection_reason->toBe('Photos missing');
});

it('verifies and suspends dealers with an audit trail', function () {
    $dealer = User::factory()->dealer()->create();
    $profile = $dealer->dealerProfile;

    // Verification needs every required document approved first (strict rule, agreed in Phase 8).
    $this->actingAs($this->admin)->patch(route('admin.dealers.verification', $profile), ['decision' => 'verify'])->assertSessionHasErrors('decision');
    expect($profile->fresh()->verification_status)->toBe(DealerStatus::Pending);
    foreach (DealerDocument::requiredFor($profile->business_type) as $type) {
        $document = new DealerDocument(['type' => $type, 'original_name' => $type.'.jpg']);
        $document->forceFill(['dealer_profile_id' => $profile->id, 'path' => 'dealer-documents/'.$profile->id.'/'.$type.'.jpg', 'mime' => 'image/jpeg', 'size' => 1000, 'status' => 'approved'])->save();
    }

    $this->actingAs($this->admin)->patch(route('admin.dealers.verification', $profile), ['decision' => 'verify']);
    expect($profile->fresh())->verification_status->toBe(DealerStatus::Verified)->verified_by->toBe($this->admin->id);

    $this->actingAs($this->admin)->patch(route('admin.dealers.verification', $profile), ['decision' => 'suspend'])->assertSessionHasErrors('reason');
    $this->actingAs($this->admin)->patch(route('admin.dealers.verification', $profile), ['decision' => 'suspend', 'reason' => 'Misleading listings']);
    expect($profile->fresh()->verification_status)->toBe(DealerStatus::Suspended);

    expect(AuditLog::whereIn('action', ['dealer.verified', 'dealer.suspended'])->count())->toBe(2);
});

it('suspends users, who are then signed out', function () {
    $buyer = User::factory()->create();

    $this->actingAs($this->admin)->patch(route('admin.users.status', $buyer), ['status' => 'suspended']);
    expect($buyer->fresh()->status)->toBe('suspended');

    $this->actingAs($buyer->fresh())->get('/account')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('reassigns leads to verified dealers only', function () {
    $buyer = User::factory()->create();
    $requirement = new BuyerRequirement(['purpose' => 'sale', 'city' => 'Lahore', 'area_ids' => [area('Gulberg')->id]]);
    $requirement->user_id = $buyer->id;
    $requirement->save();
    $lead = app(LeadRouter::class)->routeRequirement($requirement, true);
    expect($lead->status)->toBe(LeadStatus::Unrouted);

    $unverified = User::factory()->dealer()->create();
    $this->actingAs($this->admin)->post(route('admin.leads.reassign', $lead), ['dealer_id' => $unverified->id, 'reason' => 'Test'])->assertStatus(422);

    $verified = User::factory()->dealer(DealerStatus::Verified)->create();
    $this->actingAs($this->admin)->post(route('admin.leads.reassign', $lead), ['dealer_id' => $verified->id, 'reason' => 'Covers nearby area'])->assertRedirect();

    expect($lead->fresh()->status)->toBe(LeadStatus::Assigned)
        ->and($lead->activeAssignments()->sole()->dealer_id)->toBe($verified->id)
        ->and(AuditLog::where('action', 'lead.reassigned')->exists())->toBeTrue();

    $this->actingAs($this->admin)->get(route('admin.leads.show', $lead))->assertOk()->assertSee('Covers nearby area');
});

it('keeps audit log entries immutable', function () {
    $log = AuditLogger::record('test.action');

    expect(fn () => $log->update(['action' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

it('does not invent billing data', function () {
    $this->actingAs($this->admin)->get('/admin/payments')->assertSee('No payment provider is connected');
    $this->actingAs($this->admin)->get('/admin/subscriptions')->assertSee('Subscription plans are not set up');
});
