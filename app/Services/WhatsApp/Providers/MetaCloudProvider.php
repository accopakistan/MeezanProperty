<?php

namespace App\Services\WhatsApp\Providers;

use App\Models\OutboundMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Business Platform, Cloud API (graph.facebook.com). Sends only
 * when WHATSAPP_SEND_ENABLED=true and the phone number id and access
 * token are set. Credentials come from config/services.php and are never
 * logged or shown; errors are logged by HTTP status and Meta error code only.
 */
class MetaCloudProvider implements WhatsAppProvider
{
    public function key(): string
    {
        return 'meta';
    }

    public function label(): string
    {
        return 'WhatsApp Cloud API (Meta)';
    }

    public function canSend(): bool
    {
        return filled(config('services.whatsapp.phone_number_id'))
            && filled(config('services.whatsapp.access_token'))
            && config('services.whatsapp.send_enabled') === true;
    }

    public function isTest(): bool
    {
        return false;
    }

    public function send(OutboundMessage $message): SendResult
    {
        if (! $this->canSend()) {
            return SendResult::failed('Sending is not enabled.');
        }

        try {
            $response = Http::withToken(config('services.whatsapp.access_token'))
                ->timeout(10)
                ->post($this->url(config('services.whatsapp.phone_number_id').'/messages'), $this->body($message));
        } catch (ConnectionException) {
            Log::warning('WhatsApp send failed: connection error', ['outbound' => $message->id]);

            return SendResult::failed('Could not reach WhatsApp', retryable: true);
        }

        if ($response->failed()) {
            $code = $response->json('error.code');
            Log::warning('WhatsApp send failed', ['outbound' => $message->id, 'status' => $response->status(), 'code' => $code]);

            return SendResult::failed('HTTP '.$response->status().($code ? ' (code '.$code.')' : ''), retryable: $response->status() === 429 || $response->serverError());
        }

        $id = $response->json('messages.0.id');

        return $id ? SendResult::sent($id) : SendResult::failed('No message id in the response');
    }

    public function configurationChecks(): array
    {
        $set = fn (string $key) => filled(config('services.whatsapp.'.$key));

        return [
            ['label' => 'Phone number ID (WHATSAPP_PHONE_NUMBER_ID)', 'ok' => $set('phone_number_id'), 'detail' => $set('phone_number_id') ? 'Set' : 'Missing'],
            ['label' => 'Access token (WHATSAPP_ACCESS_TOKEN)', 'ok' => $set('access_token'), 'detail' => $set('access_token') ? 'Set (hidden)' : 'Missing'],
            ['label' => 'App secret, for webhook signatures (WHATSAPP_APP_SECRET)', 'ok' => $set('app_secret'), 'detail' => $set('app_secret') ? 'Set (hidden)' : 'Missing'],
            ['label' => 'Webhook verify token (WHATSAPP_VERIFY_TOKEN)', 'ok' => $set('verify_token'), 'detail' => $set('verify_token') ? 'Set (hidden)' : 'Missing'],
            ['label' => 'Sending switched on (WHATSAPP_SEND_ENABLED)', 'ok' => config('services.whatsapp.send_enabled') === true, 'detail' => config('services.whatsapp.send_enabled') === true ? 'On' : 'Off'],
        ];
    }

    /** Reads the phone number's display number and verified name (Graph API phone number node). */
    public function testConnection(): array
    {
        if (! filled(config('services.whatsapp.phone_number_id')) || ! filled(config('services.whatsapp.access_token'))) {
            return ['ok' => false, 'detail' => 'Phone number ID and access token are required.'];
        }

        try {
            $response = Http::withToken(config('services.whatsapp.access_token'))->timeout(10)
                ->get($this->url(config('services.whatsapp.phone_number_id')), ['fields' => 'display_phone_number,verified_name']);
        } catch (ConnectionException) {
            return ['ok' => false, 'detail' => 'Could not reach graph.facebook.com.'];
        }

        if ($response->failed()) {
            return ['ok' => false, 'detail' => 'Meta answered HTTP '.$response->status().($response->json('error.code') ? ' (code '.$response->json('error.code').')' : '').'.'];
        }

        $number = (string) $response->json('display_phone_number');

        return ['ok' => true, 'detail' => trim('Connected to '.($response->json('verified_name') ?: 'the business number').($number !== '' ? ' (ending '.substr(preg_replace('/\D+/', '', $number), -4).')' : '')).'.'];
    }

    protected function url(string $path): string
    {
        return sprintf('https://graph.facebook.com/%s/%s', config('services.whatsapp.graph_version'), $path);
    }

    /** @return array<string, mixed> */
    protected function body(OutboundMessage $message): array
    {
        $base = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $message->recipient];
        $payload = $message->payload ?? [];

        return $base + match ($message->type) {
            'template' => ['type' => 'template', 'template' => [
                'name' => $payload['template'],
                'language' => ['code' => $payload['language'] ?? 'en'],
                'components' => [['type' => 'body', 'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], $payload['parameters'] ?? [])]],
            ]],
            'interactive' => ['type' => 'interactive', 'interactive' => [
                'type' => 'button',
                'body' => ['text' => mb_substr($message->body, 0, 1024)],
                'action' => ['buttons' => array_map(fn ($b) => ['type' => 'reply', 'reply' => ['id' => $b['id'], 'title' => mb_substr($b['title'], 0, 20)]], array_slice($payload['buttons'] ?? [], 0, 3))],
            ]],
            default => ['type' => 'text', 'text' => ['preview_url' => true, 'body' => mb_substr($message->body, 0, 4096)]],
        };
    }
}
