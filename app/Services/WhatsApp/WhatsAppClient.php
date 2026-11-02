<?php

namespace App\Services\WhatsApp;

use App\Services\WhatsApp\Providers\MetaCloudProvider;
use App\Services\WhatsApp\Providers\MockProvider;
use App\Services\WhatsApp\Providers\WhatsAppProvider;

/**
 * Entry point to the WhatsApp channel's configuration: which provider sends
 * (MEEZAN_WHATSAPP_DRIVER, or the mock provider while test mode is on) and
 * whether incoming webhooks can be accepted. Sending itself goes through
 * OutboundMessenger, which applies MessagePolicy first.
 */
class WhatsAppClient
{
    public function __construct(protected WhatsAppSettings $settings) {}

    public function provider(): WhatsAppProvider
    {
        if ($this->settings->testMode() || config('meezan.whatsapp.driver') === 'mock') {
            return app(MockProvider::class);
        }

        return app(MetaCloudProvider::class);
    }

    /** Incoming webhooks need all four Meta values (number id, token, app secret, verify token). */
    public function isConfigured(): bool
    {
        return filled(config('services.whatsapp.phone_number_id'))
            && filled(config('services.whatsapp.access_token'))
            && filled(config('services.whatsapp.app_secret'))
            && filled(config('services.whatsapp.verify_token'));
    }

    public function canSend(): bool
    {
        return $this->provider()->canSend();
    }

    /** Messages reach real phones (a real provider that can send; never the mock). */
    public function isLive(): bool
    {
        return ! $this->provider()->isTest() && $this->provider()->canSend();
    }

    public function verifySignature(string $payload, ?string $header): bool
    {
        $secret = config('services.whatsapp.app_secret');

        if (! $secret || ! $header || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $payload, $secret), $header);
    }
}
