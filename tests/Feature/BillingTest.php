<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\BillingAdjustment;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\BillingAdminAlert;
use App\Notifications\BillingUpdated;
use App\Services\Billing\BillingReport;
use App\Services\Billing\Entitlements;
use App\Services\Billing\Gateways\GatewayManager;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['meezan.billing.gateways.mock.enabled' => true]);
    $this->admin = User::factory()->admin()->create();
});

function billingPackage(array $attributes = []): Package
{
    $package = new Package($attributes + ['name' => 'Package '.Str::random(6), 'price' => 0, 'currency' => 'PKR', 'billing_interval' => 'month', 'is_active' => true]);
    $package->save();

    return $package;
}

function enableBankTransfer(): void
{
    config(['meezan.billing.gateways.manual' => [
        'enabled' => true, 'bank_name' => 'Test Bank', 'account_title' => 'Meezan Property Test',
        'account_number' => '0000-TEST', 'iban' => 'PK00TEST0000000000000000', 'max_proof_kb' => 5120,
    ]]);
}

/** Dealer picks a paid package with the mock gateway and the test payment succeeds. */
function subscribeWithMock(User $dealer, Package $package, string $outcome = 'succeeded'): Payment
{
    test()->actingAs($dealer)->post(route('dealer.billing.choose', $package), ['gateway' => 'mock'])->assertRedirect();
    $payment = Payment::where('user_id', $dealer->id)->latest('id')->firstOrFail();
    test()->actingAs($dealer)->post(route('dealer.billing.mock.complete', $payment), ['outcome' => $outcome])->assertRedirect();

    return $payment->fresh();
}

function signedMockWebhook(array $payload, string $secret = 'test-webhook-secret')
{
    $body = json_encode($payload);

    return test()->call('POST', '/webhooks/payments/mock', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_MOCK_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, $secret),
    ], $body);
}

/*
| Packages (admin)
*/

it('lets admins create and edit packages with configurable limits and features, audited', function () {
    $this->actingAs($this->admin)->post(route('admin.packages.store'), [
        'name' => 'Agency Plus', 'price' => '4999', 'currency' => 'PKR', 'billing_interval' => 'month',
        'listing_limit' => '40', 'lead_limit' => '', 'featured_limit' => '3',
        'features' => ['featured_listings', 'priority_leads'], 'is_active' => '1', 'trial_days' => '7',
    ])->assertRedirect(route('admin.packages.index'));

    $package = Package::sole();
    expect($package)->slug->toBe('agency-plus')->listing_limit->toBe(40)->lead_limit->toBeNull()
        ->featured_limit->toBe(3)->trial_days->toBe(7)->is_active->toBeTrue()
        ->and($package->hasFeature('priority_leads'))->toBeTrue()
        ->and(AuditLog::where('action', 'package.created')->exists())->toBeTrue();

    $this->actingAs($this->admin)->put(route('admin.packages.update', $package), [
        'name' => 'Agency Plus', 'slug' => 'agency-plus', 'price' => '5500', 'currency' => 'PKR', 'billing_interval' => 'year',
        'listing_limit' => '60', 'features' => ['featured_listings'], 'is_active' => '1',
    ])->assertRedirect();

    $log = AuditLog::where('action', 'package.updated')->sole();
    expect($package->fresh())->price->toBe('5500.00')->billing_interval->toBe('year')->listing_limit->toBe(60)
        ->and($log->properties['from']['listing_limit'])->toBe(40)
        ->and($log->properties['to']['listing_limit'])->toBe(60);
});

it('rejects invalid package settings and allows only a free default plan', function () {
    $this->actingAs($this->admin)->post(route('admin.packages.store'), ['name' => 'Bad', 'price' => '-5', 'currency' => 'pkr', 'billing_interval' => 'week', 'features' => ['teleport']])
        ->assertSessionHasErrors(['price', 'currency', 'billing_interval', 'features.0']);

    $this->actingAs($this->admin)->post(route('admin.packages.store'), ['name' => 'Paid default', 'price' => '100', 'currency' => 'PKR', 'billing_interval' => 'month', 'is_default' => '1'])
        ->assertSessionHasErrors('is_default');

    $first = billingPackage(['is_default' => true, 'is_recommended' => true]);
    $this->actingAs($this->admin)->post(route('admin.packages.store'), ['name' => 'New free', 'price' => '0', 'currency' => 'PKR', 'billing_interval' => 'month', 'is_default' => '1', 'is_recommended' => '1', 'is_active' => '1']);

    expect($first->fresh())->is_default->toBeFalse()->is_recommended->toBeFalse()
        ->and(Package::defaultPlan()->name)->toBe('New free')
        ->and(Package::count())->toBe(2);
});

it('shows only active packages from the database and hides deactivated ones from dealers', function () {
    $this->get(route('pricing'))->assertOk()->assertSee('Dealer packages are being finalised');

    $live = billingPackage(['name' => 'Visible Plan', 'price' => 3000, 'is_recommended' => true]);
    $hidden = billingPackage(['name' => 'Hidden Plan', 'price' => 1000]);
    $this->actingAs($this->admin)->patch(route('admin.packages.toggle', $hidden))->assertRedirect();

    auth()->logout();
    $this->flushSession(); // the admin's "hidden" flash message names the package
    $this->get(route('pricing'))->assertOk()->assertSee('Visible Plan')->assertSee('Recommended')->assertDontSee('Hidden Plan')->assertDontSee('% off');

    $dealer = User::factory()->dealer()->create();
    $this->actingAs($dealer)->get(route('dealer.billing.checkout', $hidden))->assertNotFound();
    $this->actingAs($dealer)->post(route('dealer.billing.choose', $hidden), ['gateway' => 'mock'])->assertSessionHasErrors('package');
    expect(Subscription::count())->toBe(0);
});

it('never deletes packages, invoices or payments and keeps invoice amounts fixed', function () {
    $dealer = User::factory()->dealer()->create();
    $payment = subscribeWithMock($dealer, billingPackage(['price' => 2500]));
    $invoice = $payment->invoice;

    expect(fn () => $invoice->delete())->toThrow(LogicException::class)
        ->and(fn () => $payment->delete())->toThrow(LogicException::class)
        ->and(fn () => $invoice->forceFill(['amount' => 1])->save())->toThrow(LogicException::class)
        ->and(Route::has('admin.packages.destroy'))->toBeFalse()
        ->and($invoice->number)->toMatch('/^MP-\d{4}-\d{6}$/')
        ->and($payment->reference)->toMatch('/^PAY-\d{7}$/');
});

/*
| Subscriptions
*/

it('applies the default plan to dealers without a subscription, and the configured fallback when there is none', function () {
    $dealer = User::factory()->dealer()->create();
    expect((new Entitlements)->plan($dealer))->source->toBe('fallback')
        ->and((new Entitlements)->plan($dealer)->limit('listings'))->toBeNull();

    billingPackage(['name' => 'Free', 'is_default' => true, 'listing_limit' => 3, 'lead_limit' => 2]);
    $plan = (new Entitlements)->plan($dealer);
    expect($plan->source)->toBe('default')->and($plan->limit('listings'))->toBe(3)->and($plan->limit('leads'))->toBe(2);
});

it('starts a free package at once and keeps the previous subscription in history', function () {
    Notification::fake();
    $dealer = User::factory()->dealer()->create();
    $first = billingPackage(['name' => 'Free A']);
    $second = billingPackage(['name' => 'Free B', 'listing_limit' => 9]);

    $this->actingAs($dealer)->post(route('dealer.billing.choose', $first))->assertRedirect(route('dealer.subscription'));
    $this->actingAs($dealer)->post(route('dealer.billing.choose', $second))->assertRedirect(route('dealer.subscription'));

    $old = Subscription::where('package_id', $first->id)->sole();
    $new = Subscription::where('package_id', $second->id)->sole();
    expect($old->status)->toBe(SubscriptionStatus::Cancelled)->and($old->end_reason)->toContain('Free B')
        ->and($new->status)->toBe(SubscriptionStatus::Active)->and($new->previous_subscription_id)->toBe($old->id)
        ->and((new Entitlements)->plan($dealer)->limit('listings'))->toBe(9)
        ->and(Invoice::count())->toBe(0);
    Notification::assertSentTo($dealer, BillingUpdated::class, fn ($n) => $n->event === 'subscription_activated');
});

it('does not activate a paid package when the dealer clicks upgrade, only creates an invoice and payment', function () {
    $dealer = User::factory()->dealer()->create();
    $package = billingPackage(['price' => 2500, 'listing_limit' => 50]);

    $this->actingAs($dealer)->get(route('dealer.billing.checkout', $package))->assertOk()->assertSee('Continue to payment')->assertSee('Test payment (no real money)');
    $this->actingAs($dealer)->post(route('dealer.billing.choose', $package), ['gateway' => 'mock'])
        ->assertRedirect(route('dealer.billing.mock', Payment::sole()));

    expect(Subscription::sole()->status)->toBe(SubscriptionStatus::PendingPayment)
        ->and(Invoice::sole())->status->toBe(InvoiceStatus::Pending)->amount->toBe('2500.00')
        ->and(Payment::sole()->status)->toBe(PaymentStatus::Pending)
        ->and((new Entitlements)->plan($dealer)->source)->toBe('fallback');

    $this->actingAs($dealer)->post(route('dealer.billing.choose', $package))->assertSessionHasErrors('package');
});

it('activates a paid package after a successful test payment, flagged as a test and kept out of revenue', function () {
    Notification::fake();
    $dealer = User::factory()->dealer()->create();
    $package = billingPackage(['price' => 2500, 'listing_limit' => 50]);
    $payment = subscribeWithMock($dealer, $package);

    $subscription = Subscription::sole();
    expect($payment->status)->toBe(PaymentStatus::Succeeded)->and($payment->gateway_reference)->toStartWith('MOCK-')
        ->and($payment->invoice)->status->toBe(InvoiceStatus::Paid)->is_test->toBeTrue()->period_end->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)->and($subscription->is_test)->toBeTrue()
        ->and($subscription->current_period_end->isSameDay(now()->addMonthNoOverflow()))->toBeTrue()
        ->and((new Entitlements)->plan($dealer)->limit('listings'))->toBe(50)
        ->and(app(BillingReport::class)->paidRevenue(now()->startOfMonth(), now()->endOfMonth()))->toBeEmpty();
    Notification::assertSentTo($dealer, BillingUpdated::class, fn ($n) => $n->event === 'payment_succeeded');

    $this->actingAs($dealer)->get(route('dealer.subscription'))->assertOk()->assertSee($package->name)->assertSee('Test payment')->assertSee($payment->invoice->number);
    $this->actingAs($dealer)->get(route('dealer.billing.invoices.show', $payment->invoice))->assertOk()->assertSee('TEST RECORD')->assertSee('not a tax invoice');
});

it('records a failed payment without activating anything, and lets the dealer try again', function () {
    Notification::fake();
    $dealer = User::factory()->dealer()->create();
    $package = billingPackage(['price' => 2500]);
    $payment = subscribeWithMock($dealer, $package, 'failed');

    expect($payment->status)->toBe(PaymentStatus::Failed)->and($payment->failure_reason)->toBe('Test payment declined')
        ->and($payment->invoice->status)->toBe(InvoiceStatus::Failed)
        ->and(Subscription::sole()->status)->toBe(SubscriptionStatus::PendingPayment);
    Notification::assertSentTo($dealer, BillingUpdated::class, fn ($n) => $n->event === 'payment_failed');

    $this->actingAs($dealer)->post(route('dealer.billing.invoices.pay', $payment->invoice), ['gateway' => 'mock'])->assertRedirect();
    $retry = Payment::latest('id')->first();
    $this->actingAs($dealer)->post(route('dealer.billing.mock.complete', $retry), ['outcome' => 'succeeded']);

    expect(Payment::count())->toBe(2)->and(Subscription::sole()->status)->toBe(SubscriptionStatus::Active);
});

it('upgrades between paid packages only when paid, ending the old subscription with history', function () {
    $dealer = User::factory()->dealer()->create();
    $starter = billingPackage(['name' => 'Starter', 'price' => 2500]);
    $gold = billingPackage(['name' => 'Gold', 'price' => 7500]);
    subscribeWithMock($dealer, $starter);
    $old = Subscription::sole();

    $this->actingAs($dealer)->post(route('dealer.billing.choose', $gold), ['gateway' => 'mock']);
    expect($old->fresh()->status)->toBe(SubscriptionStatus::Active);

    $this->actingAs($dealer)->post(route('dealer.billing.mock.complete', Payment::latest('id')->first()), ['outcome' => 'succeeded']);
    $new = Subscription::where('package_id', $gold->id)->sole();

    expect($old->fresh())->status->toBe(SubscriptionStatus::Cancelled)->end_reason->toBe('Replaced by Gold')
        ->and($new)->status->toBe(SubscriptionStatus::Active)->change_type->toBe('upgrade')->previous_subscription_id->toBe($old->id)
        ->and(Invoice::count())->toBe(2);
});

it('schedules a downgrade for the end of the paid period and applies it there', function () {
    $dealer = User::factory()->dealer()->create();
    $gold = billingPackage(['name' => 'Gold', 'price' => 7500, 'listing_limit' => 100]);
    $free = billingPackage(['name' => 'Free', 'listing_limit' => 2]);
    subscribeWithMock($dealer, $gold);
    $paid = Subscription::sole();

    $this->actingAs($dealer)->post(route('dealer.billing.choose', $free))->assertRedirect(route('dealer.subscription'))->assertSessionHas('status', fn ($m) => str_contains($m, 'takes effect on'));
    expect($paid->fresh()->scheduled_package_id)->toBe($free->id)->and((new Entitlements)->plan($dealer)->limit('listings'))->toBe(100);

    $this->travelTo($paid->current_period_end->copy()->addHour());
    Artisan::call('meezan:billing-lifecycle');

    $after = Subscription::where('package_id', $free->id)->sole();
    expect($paid->fresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($after)->status->toBe(SubscriptionStatus::Active)->previous_subscription_id->toBe($paid->id)->change_type->toBe('downgrade')
        ->and((new Entitlements)->plan($dealer)->limit('listings'))->toBe(2);
});

it('cancels at the end of the period, can be undone, and then falls back to the default plan', function () {
    $dealer = User::factory()->dealer()->create();
    subscribeWithMock($dealer, billingPackage(['name' => 'Starter', 'price' => 2500]));
    $subscription = Subscription::sole();

    $this->actingAs($dealer)->post(route('dealer.billing.cancel'), ['reason' => 'Testing'])->assertRedirect();
    expect($subscription->fresh())->cancel_at_period_end->toBeTrue()->status->toBe(SubscriptionStatus::Active);

    $this->actingAs($dealer)->post(route('dealer.billing.resume'))->assertRedirect();
    expect($subscription->fresh()->cancel_at_period_end)->toBeFalse();

    $this->actingAs($dealer)->post(route('dealer.billing.cancel'));
    $this->travelTo($subscription->fresh()->current_period_end->copy()->addMinute());
    Artisan::call('meezan:billing-lifecycle');

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and((new Entitlements)->plan($dealer)->source)->toBe('fallback');
});

it('issues one renewal invoice, extends the period when paid, and expires an unpaid one after the grace period', function () {
    Notification::fake();
    $dealer = User::factory()->dealer()->create();
    subscribeWithMock($dealer, billingPackage(['price' => 2500]));
    $subscription = Subscription::sole();
    $end = $subscription->current_period_end->copy();

    $this->travelTo($end->copy()->subDays(5));
    Artisan::call('meezan:billing-lifecycle');
    Artisan::call('meezan:billing-lifecycle');
    $renewal = Invoice::where('kind', 'renewal')->sole();
    expect($renewal->status)->toBe(InvoiceStatus::Pending)->and($renewal->period_start->equalTo($end))->toBeTrue();
    Notification::assertSentTo($dealer, BillingUpdated::class, fn ($n) => $n->event === 'renewal_due');

    // Paid: the new period follows on from the old one.
    $this->actingAs($dealer)->post(route('dealer.billing.invoices.pay', $renewal), ['gateway' => 'mock']);
    $this->actingAs($dealer)->post(route('dealer.billing.mock.complete', Payment::latest('id')->first()), ['outcome' => 'succeeded']);
    expect($subscription->fresh()->current_period_end->equalTo($end->copy()->addMonthNoOverflow()))->toBeTrue();

    // Next period unpaid: overdue, then expired after the grace days.
    $nextEnd = $subscription->fresh()->current_period_end;
    $this->travelTo($nextEnd->copy()->addHour());
    Artisan::call('meezan:billing-lifecycle');
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue);

    $this->travelTo($nextEnd->copy()->addDays(config('meezan.billing.grace_days') + 1));
    Artisan::call('meezan:billing-lifecycle');
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and(Invoice::where('kind', 'renewal')->where('status', 'cancelled')->count())->toBe(1)
        ->and(Invoice::count())->toBe(3);
    Notification::assertSentTo($dealer, BillingUpdated::class, fn ($n) => $n->event === 'subscription_expired');
});

it('gives one free trial without payment and requires payment after that', function () {
    $dealer = User::factory()->dealer()->create();
    $trialPackage = billingPackage(['name' => 'Pro', 'price' => 5000, 'trial_days' => 14]);

    $this->actingAs($dealer)->post(route('dealer.billing.choose', $trialPackage))->assertRedirect(route('dealer.subscription'));
    $trial = Subscription::sole();
    expect($trial->status)->toBe(SubscriptionStatus::Trial)->and($trial->trial_ends_at->isSameDay(now()->addDays(14)))->toBeTrue()
        ->and(Payment::count())->toBe(0);

    $other = billingPackage(['name' => 'Pro Max', 'price' => 9000, 'trial_days' => 14]);
    expect(app(SubscriptionManager::class)->outcome($dealer, $other))->toBe('payment_required');
});

it('closes checkouts that were never paid, keeping the invoice as cancelled', function () {
    $dealer = User::factory()->dealer()->create();
    $this->actingAs($dealer)->post(route('dealer.billing.choose', billingPackage(['price' => 2500])), ['gateway' => 'mock']);

    $this->travel(config('meezan.billing.checkout_expiry_days') + 1)->days();
    Artisan::call('meezan:billing-lifecycle');

    expect(Subscription::sole()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and(Invoice::sole()->status)->toBe(InvoiceStatus::Cancelled)
        ->and(Payment::sole()->status)->toBe(PaymentStatus::Cancelled);
});

/*
| Bank transfer (manual) workflow
*/

it('offers bank transfer only when account details are configured', function () {
    expect(app(GatewayManager::class)->available())->not->toHaveKey('manual');
    enableBankTransfer();
    expect(app(GatewayManager::class)->available())->toHaveKey('manual');
});

it('waits for staff to confirm a bank transfer before activating, with private proof', function () {
    Notification::fake();
    Storage::fake('local');
    Storage::fake('public');
    enableBankTransfer();
    $dealer = User::factory()->dealer()->create();
    $package = billingPackage(['price' => 2500, 'listing_limit' => 40]);

    $this->actingAs($dealer)->post(route('dealer.billing.choose', $package), ['gateway' => 'manual']);
    $payment = Payment::sole();
    $this->actingAs($dealer)->get(route('dealer.billing.payments.show', $payment))->assertOk()
        ->assertSee('PK00TEST0000000000000000')->assertSee($payment->invoice->number);

    $this->actingAs($dealer)->post(route('dealer.billing.payments.transfer', $payment), [
        'payer_reference' => 'FT26110200001',
        'proof' => UploadedFile::fake()->image('receipt.jpg', 600, 800),
    ])->assertRedirect();

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::AwaitingReview)
        ->and(Subscription::sole()->status)->toBe(SubscriptionStatus::PendingPayment)
        ->and((new Entitlements)->plan($dealer)->source)->toBe('fallback')
        ->and(Storage::disk('local')->exists($payment->proof_path))->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty()
        ->and($payment->toArray())->not->toHaveKey('proof_path');
    Notification::assertSentTo($this->admin, BillingAdminAlert::class, fn ($n) => $n->event === 'transfer_submitted');

    // Staff must confirm the money arrived.
    $this->actingAs($this->admin)->get(route('admin.payments.show', $payment))->assertOk()->assertSee('FT26110200001');
    $this->actingAs($this->admin)->get(route('admin.payments.proof', $payment))->assertOk();
    $this->actingAs($this->admin)->patch(route('admin.payments.review', $payment), ['decision' => 'approve'])->assertSessionHasErrors('confirm_received');
    expect($payment->fresh()->status)->toBe(PaymentStatus::AwaitingReview);

    $this->actingAs($this->admin)->patch(route('admin.payments.review', $payment), ['decision' => 'approve', 'confirm_received' => '1'])->assertRedirect();
    expect($payment->fresh())->status->toBe(PaymentStatus::Succeeded)->reviewed_by->toBe($this->admin->id)
        ->and(Subscription::sole()->status)->toBe(SubscriptionStatus::Active)
        ->and((new Entitlements)->plan($dealer)->limit('listings'))->toBe(40)
        ->and(app(BillingReport::class)->paidRevenue(now()->startOfMonth(), now()->endOfMonth())->all())->toBe(['PKR' => 2500.0])
        ->and(AuditLog::where('action', 'payment.transfer_approved')->where('user_id', $this->admin->id)->exists())->toBeTrue();
});

it('requires a reason to reject a transfer and lets the dealer pay again', function () {
    Notification::fake();
    enableBankTransfer();
    $dealer = User::factory()->dealer()->create();
    $this->actingAs($dealer)->post(route('dealer.billing.choose', billingPackage(['price' => 2500])), ['gateway' => 'manual']);
    $payment = Payment::sole();
    $this->actingAs($dealer)->post(route('dealer.billing.payments.transfer', $payment), ['payer_reference' => 'FT-1234']);

    $this->actingAs($this->admin)->patch(route('admin.payments.review', $payment), ['decision' => 'reject'])->assertSessionHasErrors('note');
    $this->actingAs($this->admin)->patch(route('admin.payments.review', $payment), ['decision' => 'reject', 'note' => 'No matching transfer found'])->assertRedirect();

    expect($payment->fresh())->status->toBe(PaymentStatus::Failed)->review_note->toBe('No matching transfer found')
        ->and(Subscription::sole()->status)->toBe(SubscriptionStatus::PendingPayment)
        ->and($payment->invoice->fresh()->status->isOpen())->toBeTrue();
    Notification::assertSentTo($dealer, BillingUpdated::class, fn ($n) => $n->event === 'payment_rejected' && str_contains($n->inApp($dealer)['body'], 'No matching transfer found'));
});

it('flags a transfer reference that was already used on another payment', function () {
    enableBankTransfer();
    foreach ([User::factory()->dealer()->create(), User::factory()->dealer()->create()] as $dealer) {
        $this->actingAs($dealer)->post(route('dealer.billing.choose', billingPackage(['price' => 2500])), ['gateway' => 'manual']);
        $this->actingAs($dealer)->post(route('dealer.billing.payments.transfer', Payment::where('user_id', $dealer->id)->sole()), ['payer_reference' => 'SAME-REF-99']);
    }

    $logs = AuditLog::where('action', 'payment.transfer_submitted')->oldest('id')->get();
    expect($logs[0]->properties)->not->toHaveKey('reference_used_before')
        ->and($logs[1]->properties['reference_used_before'])->toBeTrue();
    $this->actingAs($this->admin)->get(route('admin.payments.show', Payment::latest('id')->first()))->assertSee('This transaction reference also appears on');
});

/*
| Webhooks
*/

it('accepts only correctly signed payment webhooks and applies each event once', function () {
    config(['meezan.billing.gateways.mock.webhook_secret' => 'test-webhook-secret']);
    $dealer = User::factory()->dealer()->create();
    $this->actingAs($dealer)->post(route('dealer.billing.choose', billingPackage(['price' => 2500])), ['gateway' => 'mock']);
    $payment = Payment::sole();
    auth()->logout();
    $event = ['id' => 'evt_1', 'payment_reference' => $payment->reference, 'outcome' => 'succeeded', 'transaction_id' => 'TX-1', 'amount' => '2500.00', 'currency' => 'PKR'];

    signedMockWebhook($event, 'wrong-secret')->assertStatus(401);
    $this->postJson('/webhooks/payments/mock', $event)->assertStatus(401);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);

    signedMockWebhook($event)->assertOk()->assertJson(['result' => 'applied']);
    signedMockWebhook($event)->assertOk()->assertJson(['duplicate' => true]);
    signedMockWebhook(['id' => 'evt_2'] + $event)->assertOk()->assertJson(['result' => 'duplicate']);

    expect($payment->fresh())->status->toBe(PaymentStatus::Succeeded)->gateway_reference->toBe('TX-1')
        ->and(AuditLog::where('action', 'subscription.activated')->count())->toBe(1)
        ->and(AuditLog::where('action', 'invoice.paid')->count())->toBe(1);

    signedMockWebhook(['id' => 'evt_3', 'payment_reference' => 'PAY-9999999', 'outcome' => 'succeeded'])->assertStatus(422);
    signedMockWebhook(['id' => 'evt_4', 'outcome' => 'succeeded'])->assertStatus(422);
    $this->post('/webhooks/payments/unknown', [])->assertNotFound();
});

it('does not activate when the gateway reports a different amount', function () {
    Notification::fake();
    config(['meezan.billing.gateways.mock.webhook_secret' => 'test-webhook-secret']);
    $dealer = User::factory()->dealer()->create();
    $this->actingAs($dealer)->post(route('dealer.billing.choose', billingPackage(['price' => 2500])), ['gateway' => 'mock']);
    $payment = Payment::sole();

    signedMockWebhook(['id' => 'evt_x', 'payment_reference' => $payment->reference, 'outcome' => 'succeeded', 'amount' => '25.00', 'currency' => 'PKR'])->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and(Subscription::sole()->status)->toBe(SubscriptionStatus::PendingPayment)
        ->and(AuditLog::where('action', 'payment.amount_mismatch')->exists())->toBeTrue();
    Notification::assertSentTo($this->admin, BillingAdminAlert::class, fn ($n) => $n->event === 'payment_problem');
});

it('never offers the test gateway in production', function () {
    expect(app(GatewayManager::class)->available())->toHaveKey('mock');

    foreach (['production', 'staging'] as $environment) {
        $this->app->detectEnvironment(fn () => $environment);
        expect(app(GatewayManager::class)->available())->not->toHaveKey('mock')
            ->and(app(GatewayManager::class)->get('mock')->supportsWebhooks())->toBeFalse();
    }

    // Off unless switched on explicitly, even locally.
    $this->app->detectEnvironment(fn () => 'local');
    config(['meezan.billing.gateways.mock.enabled' => false]);
    expect(app(GatewayManager::class)->available())->not->toHaveKey('mock');
});

/*
| Refunds and adjustments
*/

it('records refunds against paid invoices without exceeding the amount, and nets them from revenue', function () {
    enableBankTransfer();
    $dealer = User::factory()->dealer()->create();
    $this->actingAs($dealer)->post(route('dealer.billing.choose', billingPackage(['price' => 2500])), ['gateway' => 'manual']);
    $payment = Payment::sole();
    $this->actingAs($dealer)->post(route('dealer.billing.payments.transfer', $payment), ['payer_reference' => 'FT-REFUND']);
    $this->actingAs($this->admin)->patch(route('admin.payments.review', $payment), ['decision' => 'approve', 'confirm_received' => '1']);
    $invoice = $payment->invoice->fresh();

    $this->actingAs($this->admin)->post(route('admin.invoices.refund', $invoice), ['amount' => '1000', 'reason' => 'Goodwill'])->assertRedirect();
    $this->actingAs($this->admin)->post(route('admin.invoices.refund', $invoice), ['amount' => '1600', 'reason' => 'Too much'])->assertSessionHasErrors('amount');
    expect($invoice->fresh())->status->toBe(InvoiceStatus::Paid)->refunded_amount->toBe('1000.00')
        ->and(app(BillingReport::class)->paidRevenue(now()->startOfMonth(), now()->endOfMonth())->all())->toBe(['PKR' => 1500.0]);

    $this->actingAs($this->admin)->post(route('admin.invoices.refund', $invoice), ['amount' => '1500', 'reason' => 'Cancelled', 'end_subscription' => '1']);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Refunded)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and(Subscription::sole()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and(AuditLog::where('action', 'invoice.refund_recorded')->count())->toBe(2);

    $this->actingAs($this->admin)->post(route('admin.billing.adjustments', $dealer), ['type' => 'credit', 'amount' => '500', 'reason' => 'Goodwill credit'])->assertRedirect();
    expect(BillingAdjustment::where('type', 'credit')->sole()->created_by)->toBe($this->admin->id);
});

/*
| Admin pages and security
*/

it('renders the admin billing pages with real data', function () {
    $dealer = User::factory()->dealer()->create();
    $payment = subscribeWithMock($dealer, billingPackage(['name' => 'Starter', 'price' => 2500]));

    foreach ([route('admin.billing'), route('admin.packages.index'), route('admin.packages.create'), route('admin.packages.edit', Package::first()), route('admin.subscriptions'),
        route('admin.subscriptions.show', Subscription::first()), route('admin.payments'), route('admin.payments.show', $payment), route('admin.invoices.show', $payment->invoice_id)] as $url) {
        $this->actingAs($this->admin)->get($url)->assertOk();
    }
    // Test subscriptions are left out of the dashboard figures and the package breakdown, but listed under Subscriptions.
    $this->actingAs($this->admin)->get(route('admin.billing'))->assertSee('1 test payment')->assertSee('excluded from every figure');
    $this->actingAs($this->admin)->get(route('admin.subscriptions'))->assertSee('Starter')->assertSee('Test');
});

it('lets staff suspend and reinstate a subscription with a reason, audited', function () {
    Notification::fake();
    $dealer = User::factory()->dealer()->create();
    subscribeWithMock($dealer, billingPackage(['price' => 2500, 'listing_limit' => 30]));
    $subscription = Subscription::sole();

    $this->actingAs($this->admin)->patch(route('admin.subscriptions.update', $subscription), ['decision' => 'suspend'])->assertSessionHasErrors('reason');
    $this->actingAs($this->admin)->patch(route('admin.subscriptions.update', $subscription), ['decision' => 'suspend', 'reason' => 'Chargeback'])->assertRedirect();
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Suspended)->and((new Entitlements)->plan($dealer)->source)->toBe('fallback');

    $this->actingAs($this->admin)->patch(route('admin.subscriptions.update', $subscription), ['decision' => 'reinstate'])->assertRedirect();
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and(AuditLog::whereIn('action', ['subscription.suspended', 'subscription.reinstated'])->whereNotNull('user_id')->count())->toBe(2);
});

it('keeps every dealer’s billing private', function () {
    Storage::fake('local');
    enableBankTransfer();
    $owner = User::factory()->dealer()->create();
    $other = User::factory()->dealer()->create();
    $this->actingAs($owner)->post(route('dealer.billing.choose', billingPackage(['price' => 2500])), ['gateway' => 'manual']);
    $payment = Payment::sole();
    $this->actingAs($owner)->post(route('dealer.billing.payments.transfer', $payment), ['payer_reference' => 'FT-PRIVATE', 'proof' => UploadedFile::fake()->create('r.pdf', 20, 'application/pdf')]);

    $this->actingAs($other)->get(route('dealer.billing.invoices.show', $payment->invoice_id))->assertNotFound();
    $this->actingAs($other)->get(route('dealer.billing.payments.show', $payment))->assertNotFound();
    $this->actingAs($other)->get(route('dealer.billing.payments.proof', $payment))->assertNotFound();
    $this->actingAs($other)->post(route('dealer.billing.invoices.pay', $payment->invoice_id), ['gateway' => 'mock'])->assertNotFound();
    $this->actingAs($other)->get(route('dealer.billing.mock', $payment))->assertNotFound();
    $this->actingAs($owner)->get(route('dealer.billing.payments.proof', $payment))->assertOk();

    $buyer = User::factory()->create();
    $this->actingAs($buyer)->get(route('dealer.subscription'))->assertForbidden();
    foreach (['admin.billing', 'admin.packages.index', 'admin.subscriptions', 'admin.payments'] as $name) {
        $this->actingAs($owner)->get(route($name))->assertForbidden();
    }
    $this->actingAs($owner)->get(route('admin.payments.proof', $payment))->assertForbidden();
    auth()->logout();
    $this->get(route('dealer.subscription'))->assertRedirect(route('login'));
});

it('never shows or logs payment secrets', function () {
    config(['meezan.billing.gateways.mock.webhook_secret' => 'super-secret-webhook-key']);
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
        $logged[] = $e->message.json_encode($e->context);
    });
    $dealer = User::factory()->dealer()->create();
    $package = billingPackage(['price' => 2500]);

    $this->actingAs($dealer)->get(route('dealer.billing.checkout', $package))->assertDontSee('super-secret-webhook-key');
    $this->actingAs($dealer)->post(route('dealer.billing.choose', $package), ['gateway' => 'mock']);
    $payment = Payment::sole();
    $this->actingAs($dealer)->get(route('dealer.billing.mock', $payment))->assertDontSee('super-secret-webhook-key');
    signedMockWebhook(['id' => 'e1', 'payment_reference' => $payment->reference, 'outcome' => 'succeeded'], 'bad');
    signedMockWebhook(['id' => 'e1', 'payment_reference' => $payment->reference, 'outcome' => 'succeeded'], 'super-secret-webhook-key')->assertOk();

    expect(collect($logged)->filter(fn ($line) => str_contains($line, 'super-secret-webhook-key') || str_contains($line, 'sha256=')))->toBeEmpty()
        ->and(WebhookEvent::where('provider', 'pay-mock')->pluck('payload_hash')->first())->toBe(hash('sha256', 'e1'));
});
