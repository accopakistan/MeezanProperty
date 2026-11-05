<?php

use App\Enums\DealerStatus;
use App\Enums\LeadStatus;
use App\Models\AppSetting;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\ContactConsent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Lead;
use App\Models\OutboundMessage;
use App\Models\Package;
use App\Models\Property;
use App\Models\User;
use App\Notifications\AssistantNeedsAttention;
use App\Services\Assistant\ConversationAssistant;
use App\Services\Billing\Entitlements;
use App\Services\WhatsApp\AccountLinker;
use App\Services\WhatsApp\MessagePolicy;
use App\Services\WhatsApp\OutboundMessenger;
use App\Services\WhatsApp\Providers\MockProvider;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

const WA_BUYER = '923004445566';

beforeEach(function () {
    config([
        'services.whatsapp.phone_number_id' => '1234567890',
        'services.whatsapp.access_token' => 'meta-access-token-value',
        'services.whatsapp.app_secret' => 'meta-app-secret-value',
        'services.whatsapp.verify_token' => 'verify-me',
        'services.whatsapp.send_enabled' => false,
        'meezan.whatsapp.driver' => 'mock',
    ]);
    $this->admin = User::factory()->admin()->create();
});

function waPost($test, array $value, string $secret = 'meta-app-secret-value')
{
    $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => 'WABA', 'changes' => [['field' => 'messages', 'value' => $value]]]]]);

    return $test->call('POST', '/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $secret),
    ], $body);
}

function waText($test, string $id, string $text, string $from = WA_BUYER, ?string $phoneNumberId = '1234567890', string $secret = 'meta-app-secret-value')
{
    return waPost($test, secret: $secret, value: array_filter([
        'messaging_product' => 'whatsapp',
        'metadata' => $phoneNumberId ? ['display_phone_number' => '923000000000', 'phone_number_id' => $phoneNumberId] : null,
        'contacts' => [['wa_id' => $from, 'profile' => ['name' => 'Ayesha']]],
        'messages' => [['id' => $id, 'from' => $from, 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => $text]]],
    ]));
}

function waPlace(string $city, string $name, string $type = 'neighbourhood'): Area
{
    return Area::firstOrCreate(['city' => $city, 'name' => $name], ['slug' => Str::slug($name.' '.$city.' '.uniqid()), 'type' => $type]);
}

function waDealer(string $city, array $areaIds): User
{
    $dealer = User::factory()->dealer(DealerStatus::Verified, $areaIds)->create();
    $dealer->dealerProfile->update(['city' => $city]);
    $dealer->dealerProfile->cities()->sync(City::where('name', $city)->pluck('id'));

    return $dealer;
}

function lastReply(): ?OutboundMessage
{
    return OutboundMessage::where('purpose', '!=', 'dealer_lead')->latest('id')->first();
}

/*
| Webhook verification and security
*/

it('answers Meta’s verification challenge only with the right token and records it', function () {
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=1158201444')->assertOk()->assertSee('1158201444');
    expect(AppSetting::get('whatsapp.webhook_verified_at'))->not->toBeNull();

    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=1')->assertForbidden();
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=%3Cscript%3E')->assertForbidden();
    $this->get('/webhooks/whatsapp?hub.mode=unsubscribe&hub.verify_token=verify-me&hub.challenge=1')->assertForbidden();
});

it('rejects unsigned, wrongly signed and malformed webhooks without storing anything', function () {
    $this->postJson('/webhooks/whatsapp', ['entry' => []])->assertUnauthorized();
    waText($this, 'wamid.X', 'hello', secret: 'wrong')->assertUnauthorized();
    $body = json_encode(['object' => 'page', 'entry' => 'x']);
    $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'meta-app-secret-value')], $body)->assertStatus(422);

    expect(ConversationMessage::count())->toBe(0)->and(OutboundMessage::count())->toBe(0);
});

it('ignores events addressed to another business phone number', function () {
    waText($this, 'wamid.OTHER', 'house in Gulberg', phoneNumberId: '999')->assertOk()->assertJson(['received' => 0]);
    expect(Conversation::count())->toBe(0);
});

it('handles a repeated webhook delivery once: one message, one reply, one lead, one allowance used', function () {
    Notification::fake();
    waDealer('Lahore', [area('Gulberg')->id]);
    Package::create(['name' => 'Free', 'slug' => 'free', 'price' => 0, 'currency' => 'PKR', 'billing_interval' => 'month', 'is_active' => true, 'is_default' => true, 'lead_limit' => 5]);

    waText($this, 'wamid.D1', '10 marla house in Gulberg Lahore 5 crore');
    waText($this, 'wamid.D2', 'dealer');
    waText($this, 'wamid.D3', 'YES')->assertJson(['received' => 1]);
    waText($this, 'wamid.D3', 'YES')->assertJson(['duplicate' => true]);
    waText($this, 'wamid.D4', 'YES'); // the same words again within a minute: kept, not answered
    expect(ConversationMessage::where('external_id', 'wamid.D4')->sole()->processing_status)->toBe('duplicate');
    $this->travel(2)->minutes();
    waText($this, 'wamid.D5', 'YES'); // a later, separate YES: answered, but no second lead

    expect(Conversation::count())->toBe(1)
        ->and(ConversationMessage::where('direction', 'inbound')->count())->toBe(5)
        ->and(OutboundMessage::where('purpose', 'reply')->count())->toBe(4)
        ->and(Lead::count())->toBe(1)
        ->and(Lead::sole()->activeAssignments()->count())->toBe(1)
        ->and((new Entitlements)->usage(User::where('role', 'dealer')->first())['leads'])->toBe(1);
});

/*
| Providers
*/

it('sends replies through the mock provider as clearly marked tests', function () {
    waText($this, 'wamid.M1', 'house in Gulberg Lahore');

    $reply = lastReply();
    expect($reply)->provider->toBe('mock')->is_test->toBeTrue()->status->toBe('sent')->category->toBe('transactional')
        ->and($reply->provider_message_id)->toStartWith('mock.')
        ->and($reply->conversationMessage->delivery_status)->toBe('sent')
        ->and($reply->toArray())->not->toHaveKey('recipient');
});

it('sends nothing when the Meta provider has no credentials or sending is off', function () {
    Http::fake();
    config(['meezan.whatsapp.driver' => 'meta', 'services.whatsapp.send_enabled' => false]);
    waText($this, 'wamid.N1', 'house in Gulberg');
    expect(lastReply())->status->toBe('blocked')->blocked_reason->toBe('sending_disabled');

    config(['services.whatsapp.send_enabled' => true, 'services.whatsapp.access_token' => null]);
    expect(app(WhatsAppClient::class)->canSend())->toBeFalse();
    Http::assertNothingSent();
});

it('sends through the Meta Cloud API when enabled, and retries only temporary failures', function () {
    Notification::fake();
    config(['meezan.whatsapp.driver' => 'meta', 'services.whatsapp.send_enabled' => true]);
    Http::fake(['graph.facebook.com/*' => Http::sequence()
        ->push(['messages' => [['id' => 'wamid.OUT1']]])
        ->push(['error' => ['code' => 131000]], 500)
        ->push(['error' => ['code' => 131026]], 400)]);

    waText($this, 'wamid.S1', 'house in Gulberg');
    expect(lastReply())->status->toBe('sent')->provider_message_id->toBe('wamid.OUT1')->is_test->toBeFalse();
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer meta-access-token-value') && $request['to'] === WA_BUYER && $request['type'] === 'text');

    waText($this, 'wamid.S2', 'flat in Johar Town');
    $temporary = lastReply();
    expect($temporary)->status->toBe('queued')->last_error->toContain('HTTP 500')->attempts->toBe(1);

    expect(app(OutboundMessenger::class)->deliver($temporary->id))->toBe('failed')
        ->and($temporary->fresh())->status->toBe('failed')->last_error->toContain('131026');
    Notification::assertSentTo($this->admin, AssistantNeedsAttention::class, fn ($n) => $n->reason === 'send_failed');
});

it('never uses the mock provider outside local and testing environments', function () {
    $this->app->detectEnvironment(fn () => 'production');
    expect(app(MockProvider::class)->canSend())->toBeFalse()
        ->and(app(MessagePolicy::class)->check(WA_BUYER, 'transactional', 'reply'))->toMatchArray(['allowed' => false, 'reason' => 'provider_unavailable']);
});

/*
| Conversation, languages, locations and matching (the same assistant as the website)
*/

it('remembers the requirement across messages in Roman Urdu and returns real listings with links', function () {
    $dha = waPlace('Lahore', 'DHA Lahore', 'defence_scheme');
    $house = Property::factory()->create(['title' => '10 Marla House in DHA Lahore', 'area_id' => $dha->id, 'price' => 39_000_000, 'bedrooms' => 5]);

    waText($this, 'wamid.R1', 'Assalamualaikum, mujhe DHA Lahore mein 10 marla ka ghar chahiye');
    $state = Conversation::sole()->state;
    expect($state)->toMatchArray(['city' => 'Lahore', 'type' => 'house', 'min_area_sqft' => 2250])
        ->and(lastReply()->body)->not->toContain('Which city');

    waText($this, 'wamid.R2', '4 crore ke around');
    $reply = lastReply()->body;
    expect(Conversation::sole()->state['city'])->toBe('Lahore')
        ->and($reply)->toContain('🏠 1 property found')->toContain('🏷 ')
        ->toContain('10 Marla House in DHA Lahore')
        ->toContain('💰 '.$house->priceLabel())
        ->toContain('🛏 5 Bedrooms')
        ->toContain(route('properties.show', $house))
        ->not->toContain('Which city')
        ->and(str_starts_with(route('properties.show', $house), config('app.url')))->toBeTrue()
        ->and(mb_strlen($reply))->toBeLessThanOrEqual(1500);
});

it('understands the same requirement in English, Roman Urdu and Urdu', function () {
    waPlace('Lahore', 'DHA Lahore', 'defence_scheme');
    $states = [];
    foreach (['923001000001' => 'I need a 10 marla house in DHA Lahore', '923001000002' => 'Mujhe DHA Lahore mein 10 marla ka ghar chahiye', '923001000003' => 'مجھے ڈی ایچ اے لاہور میں دس مرلہ گھر چاہیے'] as $from => $text) {
        waText($this, 'wamid.L'.$from, $text, $from);
        $states[] = collect(Conversation::where('external_contact', $from)->sole()->state)->only(['city', 'area_ids', 'type', 'min_area_sqft'])->all();
    }

    expect($states[1])->toBe($states[0])->and($states[2])->toBe($states[0])->and($states[0]['city'])->toBe('Lahore');
});

it('asks which Garden Town is meant instead of guessing, and refuses unsupported cities', function () {
    waPlace('Quetta', 'Garden Town', 'housing_scheme');

    waText($this, 'wamid.G1', 'house in Garden Town');
    expect(lastReply()->body)->toContain('1. ')->toContain('Lahore')->toContain('Quetta')
        ->and(Conversation::sole()->state['city'] ?? null)->toBeNull();
    waText($this, 'wamid.G2', '2');
    expect(Conversation::sole()->state['city'])->toBe('Quetta');

    waText($this, 'wamid.U1', 'I need a house in Rawalpindi', '923001000009');
    expect(lastReply()->body)->toContain('does not cover Rawalpindi');
});

it('matches real listings in all seven launch cities and never mixes cities', function () {
    foreach (config('meezan.cities') as $i => $city) {
        $place = waPlace($city, 'Testpur '.$city);
        Property::factory()->create(['title' => 'Listing in '.$city, 'city' => $city, 'area_id' => $place->id, 'price' => 30_000_000]);
    }

    foreach (config('meezan.cities') as $i => $city) {
        $from = '92300200000'.$i;
        waText($this, 'wamid.C'.$i, "house for sale in {$city} under 5 crore", $from);
        $body = OutboundMessage::where('recipient_hash', ContactConsent::hash($from))->latest('id')->first()->body;
        expect($body)->toContain('Listing in '.$city);
        foreach (array_diff(config('meezan.cities'), [$city]) as $other) {
            expect($body)->not->toContain('Listing in '.$other);
        }
    }
});

it('explains a zero-result search without inventing listings and offers dealers', function () {
    waText($this, 'wamid.Z1', '1 kanal house for sale in Gulberg Lahore under 1 crore');

    expect(lastReply()->body)->toContain('No exact match')->not->toContain('🏠')->not->toContain('/properties/');
});

/*
| Leads, dealers and their alerts
*/

it('creates a qualified lead only after consent and assigns at most the configured number of dealers', function () {
    Notification::fake();
    config(['meezan.leads.max_dealers_per_requirement' => 3]);
    foreach (range(1, 5) as $n) {
        waDealer('Lahore', [area('Gulberg')->id]);
    }

    waText($this, 'wamid.Q1', '10 marla house for sale in Gulberg Lahore 5 crore');
    expect(Lead::count())->toBe(0);
    waText($this, 'wamid.Q2', 'Please connect me with a dealer');
    expect(Lead::count())->toBe(0)->and(lastReply()->body)->toContain('Reply YES');
    waText($this, 'wamid.Q3', 'haan');

    $lead = Lead::sole();
    expect($lead->activeAssignments()->count())->toBe(3)
        ->and($lead->requirement->source)->toBe('whatsapp')
        ->and(ContactConsent::granted('share_with_dealers', 'whatsapp', WA_BUYER))->toBeTrue()
        ->and(Conversation::sole()->status)->toBe('lead');
});

it('holds the lead for staff when the dealers have used their lead allowance', function () {
    Notification::fake();
    Package::create(['name' => 'Free', 'slug' => 'free', 'price' => 0, 'currency' => 'PKR', 'billing_interval' => 'month', 'is_active' => true, 'is_default' => true, 'lead_limit' => 0]);
    waDealer('Lahore', [area('Gulberg')->id]);

    waText($this, 'wamid.H1', '10 marla house for sale in Gulberg Lahore 5 crore');
    waText($this, 'wamid.H2', 'dealer');
    waText($this, 'wamid.H3', 'YES');

    expect(Lead::sole())->status->toBe(LeadStatus::Unrouted)->hold_reason->toBe('capacity')
        ->and(OutboundMessage::where('purpose', 'dealer_lead')->count())->toBe(0);
});

it('alerts opted-in dealers on WhatsApp with the requirement only, using the approved template outside the 24-hour window', function () {
    config(['meezan.whatsapp.templates.dealer_lead' => ['name' => 'mp_dealer_new_lead', 'language' => 'en']]);
    $dealer = waDealer('Lahore', [area('Gulberg')->id]);
    $this->actingAs($dealer->fresh())->put(route('dealer.profile.update'), [
        'name' => $dealer->name, 'phone' => '03001234567', 'agency_name' => $dealer->dealerProfile->agency_name, 'whatsapp_number' => '0321 7654321',
        'whatsapp_lead_alerts' => '1', 'accepts_leads' => '1', 'areas' => [area('Gulberg')->id], 'cities' => ['Lahore'],
    ])->assertSessionHasNoErrors();
    expect($dealer->fresh()->notify_whatsapp)->toBeTrue()
        ->and(ContactConsent::granted('whatsapp_lead_alerts', 'whatsapp', '923217654321'))->toBeTrue();
    auth()->logout();

    waText($this, 'wamid.A1', '10 marla house for sale in Gulberg Lahore 5 crore');
    waText($this, 'wamid.A2', 'dealer');
    waText($this, 'wamid.A3', 'YES');

    $alert = OutboundMessage::where('purpose', 'dealer_lead')->sole();
    expect($alert)->category->toBe('notification')->type->toBe('template')->status->toBe('sent')->recipient_last4->toBe('4321')
        ->and($alert->payload['template'])->toBe('mp_dealer_new_lead')
        ->and($alert->payload['parameters'][1])->toContain('Gulberg')
        ->and($alert->payload['parameters'][2])->toContain('PKR')
        ->and($alert->body)->toContain('New qualified lead available')->toContain('Gulberg')
        ->not->toContain(WA_BUYER)->not->toContain('Ayesha');

    // Without an approved template, an alert outside the window is blocked, never sent as free text.
    config(['meezan.whatsapp.templates.dealer_lead' => ['name' => null, 'language' => 'en']]);
    waText($this, 'wamid.A4', 'new search', '923001000077');
    waText($this, 'wamid.A5', '5 marla house for sale in Gulberg Lahore 2 crore', '923001000077');
    waText($this, 'wamid.A6', 'dealer', '923001000077');
    waText($this, 'wamid.A7', 'YES', '923001000077');
    expect(OutboundMessage::where('purpose', 'dealer_lead')->latest('id')->first())->status->toBe('blocked')->blocked_reason->toBe('outside_service_window');
});

it('sends no WhatsApp alert to dealers who have not opted in, and withdraws consent when they opt out', function () {
    $dealer = waDealer('Lahore', [area('Gulberg')->id]);
    $dealer->dealerProfile->update(['whatsapp_number' => '923217654321']);

    waText($this, 'wamid.O1', '10 marla house for sale in Gulberg Lahore 5 crore');
    waText($this, 'wamid.O2', 'dealer');
    waText($this, 'wamid.O3', 'YES');
    expect(OutboundMessage::where('purpose', 'dealer_lead')->count())->toBe(0);

    ContactConsent::record('whatsapp_lead_alerts', 'whatsapp', 'dealer_profile', $dealer->id, '923217654321');
    $dealer->forceFill(['notify_whatsapp' => true])->save();
    $this->actingAs($dealer->fresh())->put(route('dealer.profile.update'), [
        'name' => $dealer->name, 'phone' => '03001234567', 'agency_name' => $dealer->dealerProfile->agency_name, 'whatsapp_number' => '923217654321',
        'accepts_leads' => '1', 'areas' => [area('Gulberg')->id], 'cities' => ['Lahore'],
    ])->assertSessionHasNoErrors();
    expect($dealer->fresh()->notify_whatsapp)->toBeFalse()
        ->and(ContactConsent::granted('whatsapp_lead_alerts', 'whatsapp', '923217654321'))->toBeFalse();
});

/*
| Outbound messages: dedupe, statuses, retry, rate limits
*/

it('never queues the same message twice and never sends a message twice', function () {
    waText($this, 'wamid.Q', 'house in Gulberg');
    $messenger = app(OutboundMessenger::class);
    $first = $messenger->queue(['to' => WA_BUYER, 'body' => 'Hello', 'category' => 'transactional', 'purpose' => 'reply', 'dedupe_key' => 'test:1']);
    $again = $messenger->queue(['to' => WA_BUYER, 'body' => 'Hello again', 'category' => 'transactional', 'purpose' => 'reply', 'dedupe_key' => 'test:1']);

    expect($again->id)->toBe($first->id)->and(OutboundMessage::where('dedupe_key', 'test:1')->count())->toBe(1)
        ->and($messenger->deliver($first->id))->toBe('skipped');
});

it('applies delivery statuses from the provider only forwards, and records failures', function () {
    waText($this, 'wamid.ST', 'house in Gulberg');
    $reply = lastReply();
    $status = fn (string $s, array $extra = []) => waPost($this, ['statuses' => [['id' => $reply->provider_message_id, 'status' => $s, 'recipient_id' => WA_BUYER] + $extra]]);

    $status('delivered');
    $status('read');
    $status('sent');
    expect($reply->fresh())->status->toBe('read')->delivered_at->not->toBeNull()->read_at->not->toBeNull()
        ->and($reply->conversationMessage->fresh()->delivery_status)->toBe('read');

    $status('failed', ['errors' => [['code' => 131047, 'title' => 'Re-engagement message']]]);
    expect($reply->fresh())->status->toBe('failed')->last_error->toBe('131047 Re-engagement message');
});

it('lets staff retry a failed message, checking the policy again', function () {
    waText($this, 'wamid.F0', 'hello');
    MockProvider::failNext();
    waText($this, 'wamid.F1', 'house in Gulberg');
    $failed = lastReply();
    expect($failed->status)->toBe('failed')->and($failed->last_error)->toContain('Simulated permanent failure');

    $this->actingAs($this->admin)->post(route('admin.whatsapp.retry', $failed))->assertSessionHas('status');
    expect($failed->fresh())->status->toBe('sent')
        ->and(AuditLog::where('action', 'whatsapp.message_retried')->exists())->toBeTrue();

    $this->actingAs($this->admin)->post(route('admin.whatsapp.retry', $failed))->assertSessionHas('error');
});

it('limits messages per contact and holds message floods and exact repeats', function () {
    config(['meezan.whatsapp.max_inbound_per_minute' => 3, 'meezan.whatsapp.max_outbound_per_recipient_per_hour' => 2]);

    waText($this, 'wamid.R1', 'house in Gulberg');
    waText($this, 'wamid.R2', 'house in Gulberg');
    expect(ConversationMessage::where('external_id', 'wamid.R2')->sole()->processing_status)->toBe('duplicate');

    waText($this, 'wamid.R3', 'flat in Johar Town');
    waText($this, 'wamid.R4', 'plot in DHA Phase 6');
    expect(ConversationMessage::where('external_id', 'wamid.R4')->sole()->processing_status)->toBe('rate_limited')
        ->and(OutboundMessage::where('status', 'blocked')->where('blocked_reason', 'rate_limited')->count())->toBe(0);

    $this->travel(2)->minutes();
    waText($this, 'wamid.R5', '10 marla house in Gulberg');
    expect(lastReply())->status->toBe('blocked')->blocked_reason->toBe('rate_limited')
        ->and(ConversationMessage::where('direction', 'inbound')->count())->toBe(5);
});

/*
| Consent, opt-out and automation switches
*/

it('confirms STOP once, then sends nothing automated until the buyer writes again', function () {
    waText($this, 'wamid.STOP1', 'house in Gulberg');
    waText($this, 'wamid.STOP2', 'STOP');
    expect(lastReply())->purpose->toBe('opt_out_confirmation')->status->toBe('sent')
        ->and(Conversation::sole()->mode)->toBe('closed');

    $blocked = app(OutboundMessenger::class)->queue(['to' => WA_BUYER, 'body' => 'Follow-up', 'category' => 'transactional', 'purpose' => 'reply']);
    expect($blocked)->status->toBe('blocked')->blocked_reason->toBe('opted_out')
        ->and(app(OutboundMessenger::class)->queue(['to' => WA_BUYER, 'body' => 'Promo', 'category' => 'promotional', 'purpose' => 'campaign'])->blocked_reason)->toBe('promotional_disabled');

    waText($this, 'wamid.STOP3', 'house in Johar Town');
    expect(Conversation::sole()->mode)->toBe('bot')->and(lastReply()->status)->toBe('sent');
});

it('keeps messages but sends no automatic replies when automation or auto-reply is off', function () {
    $settings = app(WhatsAppSettings::class);
    $settings->set('auto_reply', false, $this->admin);
    waText($this, 'wamid.OFF1', 'house in Gulberg');
    expect(ConversationMessage::where('external_id', 'wamid.OFF1')->sole()->processing_status)->toBe('held')
        ->and(OutboundMessage::count())->toBe(0);

    $settings->set('auto_reply', true, $this->admin);
    $settings->set('automation', false, $this->admin);
    waText($this, 'wamid.OFF2', 'flat in Johar Town');
    expect(lastReply())->status->toBe('blocked')->blocked_reason->toBe('automation_disabled');

    // Staff can still answer while automation is off.
    $this->actingAs($this->admin)->post(route('admin.conversations.reply', Conversation::sole()), ['body' => 'Our team will call you today.'])->assertSessionHas('status');
    expect(lastReply())->purpose->toBe('staff_reply')->status->toBe('sent')
        ->and(AuditLog::where('action', 'whatsapp.setting_changed')->count())->toBe(3);
});

it('routes everything to the mock provider in test mode, whatever the driver', function () {
    config(['meezan.whatsapp.driver' => 'meta', 'services.whatsapp.send_enabled' => true]);
    Http::fake();
    app(WhatsAppSettings::class)->set('test_mode', true, $this->admin);

    waText($this, 'wamid.TM', 'house in Gulberg');
    expect(lastReply())->provider->toBe('mock')->is_test->toBeTrue()->status->toBe('sent');
    Http::assertNothingSent();
});

it('blocks staff replies outside the 24-hour window', function () {
    waText($this, 'wamid.W1', 'house in Gulberg');
    $this->travel(25)->hours();

    $this->actingAs($this->admin)->post(route('admin.conversations.reply', Conversation::sole()), ['body' => 'Are you still looking?'])
        ->assertSessionHas('error', fn ($m) => str_contains($m, '24 hours'));
    expect(lastReply())->status->toBe('blocked')->blocked_reason->toBe('outside_service_window');
});

/*
| Buttons, account linking, failures
*/

it('sends reply buttons when enabled and understands a tapped button', function () {
    config(['meezan.whatsapp.interactive_buttons' => true]);
    waText($this, 'wamid.B1', '10 marla house for sale in Gulberg Lahore 5 crore');
    expect(lastReply())->type->toBe('interactive')
        ->and(collect(lastReply()->payload['buttons'])->pluck('id')->all())->toContain('dealer');

    waPost($this, ['messages' => [['id' => 'wamid.B2', 'from' => WA_BUYER, 'type' => 'interactive', 'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'dealer', 'title' => 'Talk to dealer']]]]]);
    expect(ConversationMessage::where('external_id', 'wamid.B2')->sole()->body)->toBe('Please connect me with a dealer')
        ->and(lastReply()->body)->toContain('Reply YES');
});

it('links a WhatsApp chat to an account only with a one-time code', function () {
    $buyer = User::factory()->create();
    waText($this, 'wamid.K0', '10 marla house for sale in Gulberg Lahore 5 crore');
    waText($this, 'wamid.K1', 'save my requirement');

    $this->actingAs($buyer)->post(route('account.whatsapp.link'))->assertRedirect();
    $code = app(AccountLinker::class)->pendingCode($buyer);
    $this->actingAs($buyer)->get(route('account.settings'))->assertSee('LINK '.$code);

    waText($this, 'wamid.K2', 'LINK 000000');
    expect(Conversation::sole()->user_id)->toBeNull()->and(lastReply()->body)->toContain('not valid');

    waText($this, 'wamid.K3', 'link '.$code);
    expect(Conversation::sole()->user_id)->toBe($buyer->id)
        ->and(Conversation::sole()->requirement->user_id)->toBe($buyer->id)
        ->and(lastReply()->body)->toContain('linked to your Meezan Property account');

    waText($this, 'wamid.K4', 'LINK '.$code, '923009999999');
    expect(Conversation::where('external_contact', '923009999999')->sole()->user_id)->toBeNull();
});

it('apologises without technical details and alerts staff when processing fails', function () {
    Notification::fake();
    $this->mock(ConversationAssistant::class)->shouldReceive('respond')->andThrow(new RuntimeException('SQLSTATE secret internals'));

    waText($this, 'wamid.E1', 'house in Gulberg')->assertOk();

    expect(ConversationMessage::where('external_id', 'wamid.E1')->sole()->processing_status)->toBe('failed')
        ->and(lastReply()->body)->toContain('Sorry, something went wrong')->not->toContain('SQLSTATE');
    Notification::assertSentTo($this->admin, AssistantNeedsAttention::class, fn ($n) => $n->reason === 'processing_failed');
});

/*
| Admin, privacy and logging
*/

it('shows the control center to admins only, without secret values', function () {
    waText($this, 'wamid.AD', 'house in Gulberg');

    $this->actingAs($this->admin)->get(route('admin.whatsapp'))->assertOk()
        ->assertSee('TEST / MOCK MODE')->assertSee('Set (hidden)')->assertSee(route('webhooks.whatsapp'))
        ->assertSee('No: this is a local address')
        ->assertDontSee('meta-access-token-value')->assertDontSee('meta-app-secret-value')->assertDontSee('verify-me');
    $this->actingAs($this->admin)->get(route('admin.whatsapp.messages'))->assertOk()->assertSee('•••• 5566')->assertDontSee(WA_BUYER);

    $dealer = User::factory()->dealer()->create();
    $buyer = User::factory()->create();
    foreach ([$dealer, $buyer] as $user) {
        $this->actingAs($user)->get(route('admin.whatsapp'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.whatsapp.messages'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.conversations.show', Conversation::sole()))->assertForbidden();
        $this->actingAs($user)->post(route('admin.conversations.reply', Conversation::sole()), ['body' => 'x'])->assertForbidden();
        $this->actingAs($user)->post(route('admin.whatsapp.retry', lastReply()))->assertForbidden();
        $this->actingAs($user)->post(route('admin.whatsapp.simulate'), ['action' => 'message', 'text' => 'x'])->assertForbidden();
    }
    auth()->logout();
    $this->get(route('admin.whatsapp'))->assertRedirect(route('login'));
});

it('tests the Meta connection without claiming success it did not get', function () {
    config(['meezan.whatsapp.driver' => 'meta']);
    Http::fake(['graph.facebook.com/*' => Http::sequence()
        ->push(['error' => ['code' => 190]], 401)
        ->push(['display_phone_number' => '+92 300 0000001', 'verified_name' => 'Meezan Property', 'id' => '1234567890'])]);

    $this->actingAs($this->admin)->post(route('admin.whatsapp.test'))->assertSessionHas('error', fn ($m) => str_contains($m, '401'));
    $this->actingAs($this->admin)->get(route('admin.whatsapp'))->assertDontSee('Connected to');

    $this->actingAs($this->admin)->post(route('admin.whatsapp.test'))->assertSessionHas('status', 'Connected to Meezan Property (ending 0001).');
});

it('runs the mock console through the real pipeline and refuses it without the mock provider', function () {
    $this->actingAs($this->admin)->post(route('admin.whatsapp.simulate'), ['action' => 'message', 'text' => '10 marla house in Gulberg Lahore', 'process_now' => '1'])
        ->assertSessionHas('status', 'Test message received and processed.');
    $this->actingAs($this->admin)->post(route('admin.whatsapp.simulate'), ['action' => 'duplicate', 'process_now' => '1'])
        ->assertSessionHas('status', fn ($m) => str_contains($m, 'Duplicate delivery'));
    expect(ConversationMessage::where('direction', 'inbound')->count())->toBe(1)->and(lastReply()->is_test)->toBeTrue();

    $this->actingAs($this->admin)->post(route('admin.whatsapp.simulate'), ['action' => 'status', 'outbound_id' => lastReply()->id, 'delivery' => 'read']);
    expect(lastReply()->status)->toBe('read');

    config(['meezan.whatsapp.driver' => 'meta']);
    $this->actingAs($this->admin)->post(route('admin.whatsapp.simulate'), ['action' => 'message', 'text' => 'x'])->assertNotFound();
});

it('never logs credentials or signatures', function () {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
        $logged[] = $e->message.json_encode($e->context);
    });
    config(['meezan.whatsapp.driver' => 'meta', 'services.whatsapp.send_enabled' => true]);
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 1]], 500)]);

    waText($this, 'wamid.LOG', 'house in Gulberg');
    waText($this, 'wamid.LOG2', 'hi', secret: 'wrong');

    expect($logged)->not->toBeEmpty()
        ->and(collect($logged)->filter(fn ($l) => str_contains($l, 'meta-access-token-value') || str_contains($l, 'meta-app-secret-value') || str_contains($l, 'sha256=') || str_contains($l, WA_BUYER)))->toBeEmpty();
});

it('re-dispatches messages a stopped worker left queued and flags interrupted sends', function () {
    waText($this, 'wamid.SW', 'house in Gulberg');
    $reply = lastReply();
    $reply->forceFill(['status' => 'sending'])->save();
    $this->travel(20)->minutes();

    Artisan::call('meezan:whatsapp-sweep');
    expect($reply->fresh())->status->toBe('failed')->last_error->toContain('Outcome unknown');
});
