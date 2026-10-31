<?php

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Property;
use Illuminate\Support\Facades\Http;

function configureWhatsApp(bool $send = false): void
{
    config([
        'services.whatsapp.phone_number_id' => '1234567890',
        'services.whatsapp.access_token' => 'test-token',
        'services.whatsapp.app_secret' => 'test-secret',
        'services.whatsapp.verify_token' => 'verify-me',
        'services.whatsapp.send_enabled' => $send,
    ]);
}

function whatsappPayload(string $id = 'wamid.ABC', string $text = '10 marla house in Gulberg under 5 crore'): array
{
    return ['entry' => [['changes' => [['value' => [
        'contacts' => [['wa_id' => '923001234567', 'profile' => ['name' => 'Bilal']]],
        'messages' => [['id' => $id, 'from' => '923001234567', 'type' => 'text', 'text' => ['body' => $text]]],
    ]]]]]];
}

function postSigned($test, array $payload, string $secret = 'test-secret')
{
    $body = json_encode($payload);

    return $test->call('POST', '/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $secret),
    ], $body);
}

it('completes the webhook verification handshake', function () {
    configureWhatsApp();

    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=42')->assertOk()->assertSee('42');
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=42')->assertForbidden();
});

it('refuses webhooks while the integration is not configured', function () {
    $this->postJson('/webhooks/whatsapp', whatsappPayload())->assertStatus(503);
    expect(ConversationMessage::count())->toBe(0);
});

it('rejects webhooks with an invalid signature', function () {
    configureWhatsApp();

    postSigned($this, whatsappPayload(), 'wrong-secret')->assertUnauthorized();
    expect(ConversationMessage::count())->toBe(0);
});

it('stores inbound messages idempotently and does not send while sending is disabled', function () {
    configureWhatsApp(send: false);
    Http::fake();
    Property::factory()->create(['area_id' => area('Gulberg')->id, 'price' => 40_000_000]);

    postSigned($this, whatsappPayload())->assertOk()->assertJson(['received' => 1]);
    postSigned($this, whatsappPayload())->assertOk()->assertJson(['received' => 0]);

    $conversation = Conversation::sole();
    expect($conversation->contact_name)->toBe('Bilal')
        ->and($conversation->messages()->where('direction', 'inbound')->count())->toBe(1)
        ->and($conversation->messages()->where('direction', 'outbound')->sole()->delivery_status)->toBe('not_sent');

    Http::assertNothingSent();
});

it('hands the conversation to a human on request', function () {
    configureWhatsApp();

    postSigned($this, whatsappPayload('wamid.H1', 'I want to talk to a human please'));

    expect(Conversation::sole()->mode)->toBe('human');

    // No bot reply once a human has taken over.
    postSigned($this, whatsappPayload('wamid.H2', '5 marla plot in Bahria Town'));
    expect(ConversationMessage::where('direction', 'outbound')->count())->toBe(1);
});

it('sends replies through the Cloud API only when explicitly enabled', function () {
    configureWhatsApp(send: true);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]])]);

    postSigned($this, whatsappPayload());

    Http::assertSentCount(1);
    expect(ConversationMessage::where('direction', 'outbound')->sole())->delivery_status->toBe('sent')->external_id->toBe('wamid.OUT');
});
