<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin boundary around the WhatsApp Cloud API. Nothing is sent unless
 * WHATSAPP_SEND_ENABLED=true and the phone number id and token are set.
 */
class WhatsAppClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.whatsapp.phone_number_id'))
            && filled(config('services.whatsapp.access_token'))
            && filled(config('services.whatsapp.app_secret'))
            && filled(config('services.whatsapp.verify_token'));
    }

    public function canSend(): bool
    {
        return $this->isConfigured() && config('services.whatsapp.send_enabled') === true;
    }

    /**
     * @return array{sent: bool, id: ?string, error: ?string}
     */
    public function sendText(string $to, string $body): array
    {
        if (! $this->canSend()) {
            return ['sent' => false, 'id' => null, 'error' => 'WhatsApp sending is not enabled.'];
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('services.whatsapp.graph_version'),
            config('services.whatsapp.phone_number_id'),
        );

        $response = Http::withToken(config('services.whatsapp.access_token'))
            ->timeout(10)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['preview_url' => true, 'body' => mb_substr($body, 0, 4096)],
            ]);

        if ($response->failed()) {
            Log::warning('WhatsApp send failed', ['status' => $response->status()]);

            return ['sent' => false, 'id' => null, 'error' => 'HTTP '.$response->status()];
        }

        return ['sent' => true, 'id' => $response->json('messages.0.id'), 'error' => null];
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
