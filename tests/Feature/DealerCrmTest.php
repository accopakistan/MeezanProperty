<?php

use App\Enums\DealerStatus;
use App\Enums\LeadStatus;
use App\Enums\PipelineStage;
use App\Enums\PropertyStatus;
use App\Models\AuditLog;
use App\Models\BuyerRequirement;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadAppointment;
use App\Models\LeadAssignment;
use App\Models\LeadFollowUp;
use App\Models\Package;
use App\Models\Property;
use App\Models\User;
use App\Notifications\CrmReminder;
use App\Notifications\LeadAssigned;
use App\Services\Billing\Entitlements;
use App\Services\Crm\CrmStats;
use App\Services\Leads\LeadRouter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

function crmDealer(?array $areas = null): User
{
    return User::factory()->dealer(DealerStatus::Verified, $areas ?? [area('Gulberg')->id])->create();
}

/** A consented Gulberg requirement routed to the given dealers (all of whom cover Gulberg). */
function crmLead(int $dealers = 1): Lead
{
    config(['meezan.leads.max_dealers_per_requirement' => max(3, $dealers)]);
    foreach (range(1, $dealers) as $i) {
        crmDealer();
    }
    $requirement = new BuyerRequirement(['purpose' => 'sale', 'city' => 'Lahore', 'area_ids' => [area('Gulberg')->id], 'type' => 'house', 'max_price' => 50_000_000, 'min_area_sqft' => 2250]);
    $requirement->user_id = User::factory()->create(['name' => 'Sana Buyer', 'phone' => '03001112233'])->id;
    $requirement->save();

    return app(LeadRouter::class)->routeRequirement($requirement, true);
}

function stage($test, LeadAssignment $assignment, string $to, array $extra = [])
{
    return $test->actingAs($assignment->dealer()->first())->patch(route('dealer.crm.leads.stage', $assignment), ['stage' => $to] + $extra);
}

/*
| Multi-dealer independence (mandatory)
*/

it('keeps each dealer’s pipeline, notes and follow-ups independent on a shared lead', function () {
    Notification::fake();
    $lead = crmLead(3);
    [$a, $b, $c] = $lead->activeAssignments()->orderBy('id')->get()->all();

    stage($this, $a, 'contacted')->assertSessionHasNoErrors();
    stage($this, $a->fresh(), 'qualified')->assertSessionHasNoErrors();
    stage($this, $c, 'lost', ['reason' => 'price_mismatch'])->assertSessionHasNoErrors();
    $this->actingAs(User::find($a->dealer_id))->post(route('dealer.crm.notes.store', $a), ['body' => 'Buyer prefers DHA Phase 6, call after 6 PM']);
    $this->actingAs(User::find($a->dealer_id))->post(route('dealer.crm.follow-ups.store', $a), ['type' => 'call', 'due_at' => now(config('meezan.timezone'))->addDay()->toDateTimeString()]);

    expect($a->fresh()->pipeline_stage)->toBe(PipelineStage::Qualified)
        ->and($b->fresh()->pipeline_stage)->toBe(PipelineStage::New)
        ->and($c->fresh()->pipeline_stage)->toBe(PipelineStage::Lost)
        ->and($lead->fresh()->status)->toBe(LeadStatus::Assigned) // the marketplace status is untouched
        ->and($b->notes()->count())->toBe(0)->and($b->followUps()->count())->toBe(0);

    // B cannot open, change or read A's records.
    $this->actingAs(User::find($b->dealer_id))->get(route('dealer.crm.leads.show', $a))->assertForbidden();
    $this->actingAs(User::find($b->dealer_id))->patch(route('dealer.crm.leads.stage', $a), ['stage' => 'negotiating'])->assertForbidden();
    $this->actingAs(User::find($b->dealer_id))->post(route('dealer.crm.notes.store', $a), ['body' => 'x'])->assertForbidden();
    $this->actingAs(User::find($b->dealer_id))->get(route('dealer.crm.leads.show', $b))->assertOk()->assertDontSee('DHA Phase 6, call after 6 PM')->assertSee('Sana Buyer');
    // "Call ·" is how the agenda labels a call follow-up; the site header's "Call us" button is not one.
    $this->actingAs(User::find($b->dealer_id))->get(route('dealer.crm.agenda'))->assertDontSee('Call ·');
    $this->actingAs(User::find($a->dealer_id))->get(route('dealer.crm.leads.show', $a))->assertSee('DHA Phase 6, call after 6 PM');
});

/*
| Pipeline
*/

it('allows only valid moves and asks for reasons where needed', function () {
    $assignment = crmLead()->activeAssignments()->sole();

    stage($this, $assignment, 'qualified')->assertSessionHasErrors('stage'); // new → qualified skips contact
    stage($this, $assignment, 'lost')->assertSessionHasErrors('stage');      // needs a reason
    stage($this, $assignment, 'invalid')->assertSessionHasErrors('stage');   // needs a note
    stage($this, $assignment, 'contact_attempted')->assertSessionHasNoErrors();
    stage($this, $assignment->fresh(), 'no_response')->assertSessionHasNoErrors();
    expect($assignment->fresh())->pipeline_stage->toBe(PipelineStage::NoResponse)->closed_at->toBeNull(); // no response is not lost

    stage($this, $assignment->fresh(), 'lost', ['reason' => 'no_response', 'note' => 'Three calls, no answer'])->assertSessionHasNoErrors();
    $lost = $assignment->fresh();
    expect($lost)->pipeline_stage->toBe(PipelineStage::Lost)->outcome_reason->toBe('no_response')->closed_at->not->toBeNull()->first_contact_at->toBeNull();

    stage($this, $lost, 'contacted')->assertSessionHasNoErrors(); // reopened
    expect($assignment->fresh())->closed_at->toBeNull()->outcome_reason->toBeNull()->first_contact_at->not->toBeNull()->responded_at->not->toBeNull();

    expect(AuditLog::where('action', 'lead.pipeline_changed')->count())->toBe(4)
        ->and(LeadActivity::where('type', 'stage_changed')->count())->toBe(4);
});

it('records won details as dealer-reported, without requiring them', function () {
    $assignment = crmLead()->activeAssignments()->sole();
    $property = Property::factory()->create(['area_id' => area('Gulberg')->id]);
    foreach (['contacted', 'qualified', 'negotiating'] as $to) {
        stage($this, $assignment->fresh(), $to);
    }
    stage($this, $assignment->fresh(), 'won', ['transaction_type' => 'sale', 'property_id' => $property->id, 'value' => '48000000'])->assertSessionHasNoErrors();

    expect($assignment->fresh())->pipeline_stage->toBe(PipelineStage::Won)->won_transaction_type->toBe('sale')
        ->won_property_id->toBe($property->id)->won_value->toBe('48000000.00')->qualified_at->not->toBeNull();
    $this->actingAs(User::find($assignment->dealer_id))->get(route('dealer.crm.leads.show', $assignment))->assertSee('dealer-reported');
});

it('moves only the dealer’s own stage from the older quick-update form', function () {
    $lead = crmLead(2);
    [$a, $b] = $lead->activeAssignments()->orderBy('id')->get()->all();

    $this->actingAs(User::find($a->dealer_id))->patch(route('dealer.assignments.update', $a), ['status' => 'contacted'])->assertSessionHas('status');

    expect($a->fresh()->pipeline_stage)->toBe(PipelineStage::Contacted)
        ->and($b->fresh()->pipeline_stage)->toBe(PipelineStage::New)
        ->and($lead->fresh()->status)->toBe(LeadStatus::Assigned);
});

it('saves drag-and-drop moves as JSON and refuses disallowed ones', function () {
    $assignment = crmLead()->activeAssignments()->sole();
    $dealer = $assignment->dealer;

    $this->actingAs($dealer)->patchJson(route('dealer.crm.leads.stage', $assignment), ['stage' => 'won'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'cannot move'));
    $this->actingAs($dealer)->patchJson(route('dealer.crm.leads.stage', $assignment), ['stage' => 'contacted'])->assertOk();
    expect($assignment->fresh()->pipeline_stage)->toBe(PipelineStage::Contacted);

    $this->actingAs($dealer)->get(route('dealer.crm.pipeline'))->assertOk()->assertSee('data-crm-board', false)->assertSee('Move to…');
});

/*
| Notes, follow-ups and reminders
*/

it('keeps notes private and lets only the author edit them', function () {
    $assignment = crmLead()->activeAssignments()->sole();
    $dealer = $assignment->dealer;
    $this->actingAs($dealer)->post(route('dealer.crm.notes.store', $assignment), ['body' => 'Budget flexible'])->assertSessionHasNoErrors();
    $note = $assignment->notes()->sole();

    $this->actingAs($dealer)->patch(route('dealer.crm.notes.update', [$assignment, $note->id]), ['body' => 'Budget flexible up to 5.5 crore'])->assertSessionHasNoErrors();
    expect($note->fresh())->body->toBe('Budget flexible up to 5.5 crore')->edited_at->not->toBeNull();

    $other = crmDealer();
    $this->actingAs($other)->patch(route('dealer.crm.notes.update', [$assignment, $note->id]), ['body' => 'hacked'])->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('dealer.crm.leads.show', $assignment))->assertForbidden();
    $this->actingAs($this->admin)->get(route('dealer.crm.leads.show', $assignment))->assertForbidden();
    // Staff see that notes exist, never their text.
    $this->actingAs($this->admin)->get(route('admin.leads.show', $assignment->lead))->assertOk()->assertSee('1 private note(s)')->assertDontSee('5.5 crore');
    expect(LeadActivity::where('type', 'note_added')->sole()->properties)->not->toHaveKey('body');
});

it('tracks follow-ups to completion, reports overdue ones and reminds once', function () {
    Notification::fake();
    $assignment = crmLead()->activeAssignments()->sole();
    $dealer = $assignment->dealer;
    $this->actingAs($dealer)->post(route('dealer.crm.follow-ups.store', $assignment), ['type' => 'whatsapp', 'priority' => 'high', 'due_at' => now(config('meezan.timezone'))->addMinutes(30)->toDateTimeString(), 'notes' => 'Send two options']);
    $this->actingAs($dealer)->post(route('dealer.crm.follow-ups.store', $assignment), ['type' => 'call', 'due_at' => now(config('meezan.timezone'))->addHours(3)->toDateTimeString()]);
    [$first, $second] = $assignment->followUps()->orderBy('id')->get()->all();

    $this->travel(31)->minutes();
    Artisan::call('meezan:crm-reminders');
    Artisan::call('meezan:crm-reminders');
    Notification::assertSentToTimes($dealer, CrmReminder::class, 1);
    expect($first->fresh()->isOverdue())->toBeTrue()->and($first->fresh()->status)->toBe('pending'); // overdue is never deleted

    $this->travel(2)->hours();
    Artisan::call('meezan:crm-reminders');
    Notification::assertSentTo($dealer, CrmReminder::class, fn ($n) => $n->event === 'follow_up_overdue' && $n->subject->is($first));
    $this->actingAs($dealer)->get(route('dealer.crm.agenda'))->assertOk()->assertSee('Overdue (1)');

    $this->actingAs($dealer)->patch(route('dealer.crm.follow-ups.update', [$assignment, $first->id]), ['outcome' => 'completed'])->assertSessionHasNoErrors();
    $this->actingAs($dealer)->patch(route('dealer.crm.follow-ups.update', [$assignment, $second->id]), ['outcome' => 'cancelled']);
    $this->actingAs($dealer)->patch(route('dealer.crm.follow-ups.update', [$assignment, $first->id]), ['outcome' => 'cancelled'])->assertSessionHasErrors('outcome');
    expect($first->fresh())->status->toBe('completed')->completed_at->not->toBeNull()
        ->and($second->fresh()->status)->toBe('cancelled')->and(LeadFollowUp::count())->toBe(2);

    $this->actingAs(crmDealer())->patch(route('dealer.crm.follow-ups.update', [$assignment, $second->id]), ['outcome' => 'completed'])->assertForbidden();
});

/*
| Appointments and recommendations
*/

it('schedules, reschedules, completes and cancels appointments for the dealer’s own lead only', function () {
    Notification::fake();
    $assignment = crmLead()->activeAssignments()->sole();
    $dealer = $assignment->dealer;
    $live = Property::factory()->create(['area_id' => area('Gulberg')->id]);
    $draft = Property::factory()->status(PropertyStatus::Draft)->create();
    stage($this, $assignment, 'contacted');

    $this->actingAs($dealer)->post(route('dealer.crm.appointments.store', $assignment), ['type' => 'viewing', 'starts_at' => now(config('meezan.timezone'))->addMinutes(45)->toDateTimeString(), 'property_id' => $draft->id])->assertSessionHasErrors('starts_at');
    $this->actingAs($dealer)->post(route('dealer.crm.appointments.store', $assignment), ['type' => 'viewing', 'starts_at' => now(config('meezan.timezone'))->addMinutes(45)->toDateTimeString(), 'duration_minutes' => 45, 'property_id' => $live->id, 'location' => 'Gulberg III'])->assertSessionHasNoErrors();
    $appointment = LeadAppointment::sole();
    expect($assignment->fresh()->pipeline_stage)->toBe(PipelineStage::ViewingScheduled);

    Artisan::call('meezan:crm-reminders');
    Notification::assertSentTo($dealer, CrmReminder::class, fn ($n) => $n->event === 'appointment_upcoming');

    $this->actingAs($dealer)->patch(route('dealer.crm.appointments.update', [$assignment, $appointment->id]), ['type' => 'viewing', 'starts_at' => now(config('meezan.timezone'))->addDays(2)->toDateTimeString()])->assertSessionHasNoErrors();
    expect($appointment->fresh())->reminder_sent_at->toBeNull()->starts_at->isSameDay(now()->addDays(2))->toBeTrue();

    $this->actingAs(crmDealer())->patch(route('dealer.crm.appointments.update', [$assignment, $appointment->id]), ['status' => 'cancelled'])->assertForbidden();
    $this->actingAs($dealer)->patch(route('dealer.crm.appointments.update', [$assignment, $appointment->id]), ['status' => 'completed'])->assertSessionHasNoErrors();
    $this->actingAs($dealer)->patch(route('dealer.crm.appointments.update', [$assignment, $appointment->id]), ['status' => 'cancelled'])->assertSessionHasErrors('status');
    expect($appointment->fresh()->status)->toBe('completed')
        ->and(AuditLog::whereIn('action', ['lead.appointment_scheduled', 'lead.appointment_updated', 'lead.appointment_completed'])->count())->toBe(3);
});

it('recommends only real live listings, once each, and records the buyer’s response', function () {
    $assignment = crmLead()->activeAssignments()->sole();
    $dealer = $assignment->dealer;
    $live = Property::factory()->create(['area_id' => area('Gulberg')->id, 'price' => 45_000_000]);
    $draft = Property::factory()->status(PropertyStatus::Draft)->create();

    $this->actingAs($dealer)->get(route('dealer.crm.leads.show', $assignment))->assertSee($live->title);
    $this->actingAs($dealer)->post(route('dealer.crm.recommendations.store', $assignment), ['property_id' => $live->id])->assertSessionHasNoErrors();
    $this->actingAs($dealer)->post(route('dealer.crm.recommendations.store', $assignment), ['reference' => $live->reference])->assertSessionHasErrors('reference');
    $this->actingAs($dealer)->post(route('dealer.crm.recommendations.store', $assignment), ['property_id' => $draft->id])->assertSessionHasErrors('reference');
    $this->actingAs($dealer)->post(route('dealer.crm.recommendations.store', $assignment), ['reference' => 'MP-NOPE'])->assertSessionHasErrors('reference');

    $rec = $assignment->recommendations()->sole();
    expect($rec->status)->toBe('recommended'); // nothing assumed about the buyer
    $this->actingAs($dealer)->patch(route('dealer.crm.recommendations.update', [$assignment, $rec->id]), ['status' => 'interested'])->assertSessionHasNoErrors();
    expect($rec->fresh()->status)->toBe('interested')
        ->and(LeadActivity::whereIn('type', ['property_recommended', 'recommendation_updated'])->count())->toBe(2);

    $this->actingAs(crmDealer())->post(route('dealer.crm.recommendations.store', $assignment), ['property_id' => $live->id])->assertForbidden();
});

it('flags leads without activity for attention and never closes them', function () {
    Notification::fake();
    $assignment = crmLead()->activeAssignments()->sole();

    $this->travel(config('meezan.crm.stale_after_days') + 1)->days();
    Artisan::call('meezan:crm-reminders');
    Artisan::call('meezan:crm-reminders');

    expect($assignment->fresh())->stale_flagged_at->not->toBeNull()->pipeline_stage->toBe(PipelineStage::New);
    Notification::assertSentToTimes($assignment->dealer, CrmReminder::class, 1);

    stage($this, $assignment->fresh(), 'contact_attempted');
    expect($assignment->fresh()->stale_flagged_at)->toBeNull();
});

/*
| Analytics and admin oversight
*/

it('calculates dealer figures from recorded CRM data, per dealer on shared leads', function () {
    $lead = crmLead(2);
    [$a, $b] = $lead->activeAssignments()->orderBy('id')->get()->all();
    foreach (['contacted', 'qualified', 'negotiating', 'won'] as $to) {
        stage($this, $a->fresh(), $to);
    }
    stage($this, $b, 'contacted');
    stage($this, $b->fresh(), 'lost', ['reason' => 'bought_elsewhere']);

    $stats = app(CrmStats::class)->performance([$a->dealer_id, $b->dealer_id]);
    expect($stats[$a->dealer_id])->toMatchArray(['received' => 1, 'contacted' => 1, 'qualified' => 1, 'won' => 1, 'lost' => 0, 'contact_rate' => null])
        ->and($stats[$b->dealer_id])->toMatchArray(['received' => 1, 'contacted' => 1, 'qualified' => 0, 'won' => 0, 'lost' => 1]);

    // One lead, one dealer won: one lead "at least one dealer marked won", and still assigned for the marketplace.
    $this->actingAs($this->admin)->get(route('admin.crm'))->assertOk()
        ->assertSeeInOrder(['At least one dealer marked won', '1'])->assertSee('not a verified sale');
    expect($lead->fresh()->status)->toBe(LeadStatus::Assigned);

    $this->actingAs(User::find($a->dealer_id))->get(route('dealer.crm'))->assertOk()->assertSee('not enough leads yet');
});

it('lets staff add and remove dealers with eligibility checks, keeping history', function () {
    Notification::fake();
    $lead = crmLead(1);
    $first = $lead->activeAssignments()->sole();
    $second = crmDealer();
    $elsewhere = User::factory()->dealer(DealerStatus::Verified, [area('Johar Town')->id])->create();
    $unverified = User::factory()->dealer(DealerStatus::Pending, [area('Gulberg')->id])->create();

    $this->actingAs($this->admin)->post(route('admin.leads.assignments.store', $lead), ['dealer_id' => $elsewhere->id, 'reason' => 'Test'])->assertSessionHasErrors('dealer_id');
    $this->actingAs($this->admin)->post(route('admin.leads.assignments.store', $lead), ['dealer_id' => $unverified->id, 'reason' => 'Test'])->assertSessionHasErrors('dealer_id');
    $this->actingAs($this->admin)->post(route('admin.leads.assignments.store', $lead), ['dealer_id' => $second->id, 'reason' => 'Covers Gulberg too'])->assertSessionHasNoErrors();
    expect($lead->activeAssignments()->count())->toBe(2);
    Notification::assertSentTo($second, LeadAssigned::class);

    // Lead allowance: refused unless staff explicitly override.
    Package::create(['name' => 'Free', 'slug' => 'free', 'price' => 0, 'currency' => 'PKR', 'billing_interval' => 'month', 'is_active' => true, 'is_default' => true, 'lead_limit' => 0]);
    $third = crmDealer();
    $this->actingAs($this->admin)->post(route('admin.leads.assignments.store', $lead), ['dealer_id' => $third->id, 'reason' => 'x'])->assertSessionHasErrors('dealer_id');
    $this->actingAs($this->admin)->post(route('admin.leads.assignments.store', $lead), ['dealer_id' => $third->id, 'reason' => 'Agreed by phone', 'override_allowance' => '1'])->assertSessionHasNoErrors();
    expect(AuditLog::where('action', 'lead.dealer_added')->latest('id')->first()->properties['allowance_override'])->toBeTrue();

    $fourth = crmDealer();
    $this->actingAs($this->admin)->post(route('admin.leads.assignments.store', $lead), ['dealer_id' => $fourth->id, 'reason' => 'x', 'override_allowance' => '1'])->assertSessionHasErrors('dealer_id'); // max 3

    $this->actingAs($this->admin)->delete(route('admin.leads.assignments.destroy', $first))->assertSessionHasErrors('reason');
    $this->actingAs($this->admin)->delete(route('admin.leads.assignments.destroy', $first), ['reason' => 'Dealer on leave'])->assertSessionHas('status');
    expect($first->fresh())->status->toBe('removed')->end_reason->toBe('Dealer on leave')
        ->and(LeadAssignment::count())->toBe(3) // history kept
        ->and((new Entitlements)->usage($first->dealer)['leads'])->toBe(0);
    Notification::assertSentTo($first->dealer, CrmReminder::class, fn ($n) => $n->event === 'assignment_removed');
    $this->actingAs(User::find($first->dealer_id))->get(route('dealer.crm.leads.show', $first))->assertForbidden();
});

/*
| Security, lists and existing flows
*/

it('keeps the CRM away from guests, buyers and non-admins', function () {
    $assignment = crmLead()->activeAssignments()->sole();
    $buyer = User::factory()->create();

    $urls = [route('dealer.crm'), route('dealer.crm.pipeline'), route('dealer.crm.leads'), route('dealer.crm.agenda'), route('dealer.crm.leads.show', $assignment)];
    foreach ($urls as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
    foreach ($urls as $url) {
        $this->actingAs($buyer)->get($url)->assertForbidden();
    }
    $this->actingAs(User::find($assignment->dealer_id))->get(route('admin.crm'))->assertForbidden();
    $this->actingAs($buyer)->get(route('admin.crm'))->assertForbidden();
});

it('renders every CRM page with real data and filters in the database', function () {
    $lead = crmLead(1);
    $assignment = $lead->activeAssignments()->sole();
    $dealer = $assignment->dealer;
    $this->actingAs($dealer)->post(route('dealer.crm.follow-ups.store', $assignment), ['type' => 'call', 'due_at' => now(config('meezan.timezone'))->addMinutes(5)->toDateTimeString()]);

    foreach ([route('dealer.crm'), route('dealer.crm.pipeline'), route('dealer.crm.leads'), route('dealer.crm.agenda'), route('dealer.crm.leads.show', $assignment)] as $url) {
        $this->actingAs($dealer)->get($url)->assertOk();
    }
    $this->actingAs($dealer)->get(route('dealer.crm.leads', ['city' => 'Lahore', 'stage' => 'new']))->assertSee('Sana Buyer');
    $this->actingAs($dealer)->get(route('dealer.crm.leads', ['city' => 'Karachi']))->assertDontSee('Sana Buyer');
    $this->actingAs($dealer)->get(route('dealer.crm.leads', ['follow_up' => 'overdue']))->assertDontSee('Sana Buyer');
    $this->travel(10)->minutes();
    $this->actingAs($dealer)->get(route('dealer.crm.leads', ['follow_up' => 'overdue']))->assertSee('Sana Buyer');

    $this->actingAs($this->admin)->get(route('admin.leads.show', $lead))->assertOk()->assertSee('Dealer stage: New');
});

it('keeps a listing enquiry’s status in step with the dealer’s stage', function () {
    $property = Property::factory()->create();
    $inquiry = new Inquiry(['name' => 'Hamza', 'email' => 'h@example.com', 'phone' => '03001112222', 'message' => 'Is it available?']);
    $inquiry->forceFill(['property_id' => $property->id, 'recipient_id' => $property->user_id])->save();
    $assignment = app(LeadRouter::class)->routeInquiry($inquiry)->activeAssignments()->sole();
    $property->dealer->dealerProfile->forceFill(['verification_status' => DealerStatus::Verified])->save();

    stage($this, $assignment, 'contacted')->assertSessionHasNoErrors();
    expect($inquiry->fresh())->status->toBe('contacted')->responded_at->not->toBeNull();
    $this->actingAs(User::find($property->user_id))->get(route('dealer.crm.leads.show', $assignment))->assertOk()->assertSee('Is it available?');
});

/*
| Times
*/

it('reads typed times as Pakistan time, stores UTC and reminds at the right moment', function () {
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-11-05 05:00:00', 'UTC')); // 10:00 in Lahore
    $assignment = crmLead()->activeAssignments()->sole();
    $dealer = User::find($assignment->dealer_id);

    $this->actingAs($dealer)->post(route('dealer.crm.follow-ups.store', $assignment), ['type' => 'call', 'due_at' => '2026-11-05T11:00']);
    $followUp = LeadFollowUp::sole();
    expect($followUp->due_at->toDateTimeString())->toBe('2026-11-05 06:00:00'); // 11:00 PKT = 06:00 UTC

    $this->actingAs($dealer)->get(route('dealer.crm.leads.show', $assignment))->assertSee('5 Nov, 11:00 am');
    $this->actingAs($dealer)->get(route('dealer.crm'))->assertSeeInOrder(['1', 'Follow-ups due today']);

    $this->travelTo(Carbon::parse('2026-11-05 05:59:00', 'UTC'));
    Artisan::call('meezan:crm-reminders');
    Notification::assertNotSentTo($dealer, CrmReminder::class);
    $this->travelTo(Carbon::parse('2026-11-05 06:01:00', 'UTC'));
    Artisan::call('meezan:crm-reminders');
    Notification::assertSentTo($dealer, CrmReminder::class, fn ($n) => $n->event === 'follow_up_due' && str_contains($n->inApp($dealer)['body'], '11:00 am'));
});

it('never changes when a lead was assigned, or when a follow-up or viewing is due, as a lead is updated', function () {
    $assignment = crmLead()->activeAssignments()->sole();
    $dealer = User::find($assignment->dealer_id);
    $assigned = $assignment->assigned_at->toDateTimeString();
    $this->actingAs($dealer)->post(route('dealer.crm.follow-ups.store', $assignment), ['type' => 'call', 'due_at' => now(config('meezan.timezone'))->addDay()->format('Y-m-d\TH:i')]);
    $this->actingAs($dealer)->post(route('dealer.crm.appointments.store', $assignment), ['type' => 'office', 'starts_at' => now(config('meezan.timezone'))->addDays(2)->format('Y-m-d\TH:i')]);
    $due = LeadFollowUp::sole()->due_at->toDateTimeString();
    $starts = LeadAppointment::sole()->starts_at->toDateTimeString();

    $this->travel(3)->hours();
    stage($this, $assignment, 'contacted');
    $this->actingAs($dealer)->patch(route('dealer.crm.leads.priority', $assignment), ['priority' => 'high']);
    $this->actingAs($dealer)->patch(route('dealer.crm.follow-ups.update', [$assignment, LeadFollowUp::sole()->id]), ['outcome' => 'completed']);
    $this->actingAs($dealer)->patch(route('dealer.crm.appointments.update', [$assignment, LeadAppointment::sole()->id]), ['status' => 'completed']);

    expect($assignment->fresh()->assigned_at->toDateTimeString())->toBe($assigned)
        ->and(LeadFollowUp::sole()->due_at->toDateTimeString())->toBe($due)
        ->and(LeadAppointment::sole()->starts_at->toDateTimeString())->toBe($starts);
});
