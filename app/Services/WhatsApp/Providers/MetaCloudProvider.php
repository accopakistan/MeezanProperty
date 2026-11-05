<?php

namespace App\Services\WhatsApp\Providers;

use App\Models\OutboundMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Business Platform, Cloud API. Sends only when WHATSAPP_ENABLED=true
 * and the phone number id and access token are set; production also needs its
 * own business account id. Credentials come from config/services.php and are
 * never logged or shown; errors are logged by HTTP status and Meta error code.
 */
class MetaCloudProvider implements WhatsAppProvider
{
    /** Meta error codes that mean "try again later", whatever the HTTP status. */
    protected const RATE_LIMIT_CODES = [4, 80007, 130429, 131056];

    /** Plain descriptions of common Meta errors. Meta's own text is not stored. */
    protected const ERRORS = [
        190 => 'Access token is invalid or has expired',
        10 => 'The access token does not have permission for this',
        200 => 'The access token does not have permission for this',
        33 => 'The phone number id is wrong or not available to this token',
        100 => 'Meta rejected a value in the request',
        4 => 'Rate limit reached',
        80007 => 'Rate limit reached',
        130429 => 'Rate limit reached',
        131056 => 'Too many messages to this number',
        131026 => 'This number cannot receive WhatsApp messages',
        131030 => 'This number is not on Meta\'s allowed test list',
        131047 => 'More than 24 hours since the contact last wrote; a template is needed',
        132000 => 'The template parameters do not match the approved template',
        132001 => 'The template does not exist or is not approved in this language',
    ];

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
            && config('services.whatsapp.send_enabled') === true
            && (config('services.whatsapp.mode') !== 'production' || filled(config('services.whatsapp.business_account_id')));
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
                ->timeout($this->timeout())
                ->post($this->url(config('services.whatsapp.phone_number_id').'/messages'), $this->body($message));
        } catch (ConnectionException) {
            Log::warning('WhatsApp send failed: connection error', ['outbound' => $message->id]);

            return SendResult::failed('Could not reach WhatsApp', retryable: true);
        }

        if ($response->failed()) {
            $code = $response->json('error.code');
            Log::warning('WhatsApp send failed', ['outbound' => $message->id, 'status' => $response->status(), 'code' => $code]);

            return SendResult::failed(
                'HTTP '.$response->status().($code ? ' (code '.$code.')' : '').(isset(self::ERRORS[$code]) ? ': '.self::ERRORS[$code] : ''),
                retryable: $response->status() === 429 || $response->serverError() || in_array($code, self::RATE_LIMIT_CODES, true),
            );
        }

        $id = $response->json('messages.0.id');

        return $id ? SendResult::sent($id) : SendResult::failed('No message id in the response');
    }

    public function configurationChecks(): array
    {
        $set = fn (string $key) => filled(config('services.whatsapp.'.$key));
        $checks = [
            ['label' => 'Phone number ID', 'ok' => $set('phone_number_id'), 'detail' => $set('phone_number_id') ? 'Set' : 'Missing'],
            ['label' => 'Access token (WHATSAPP_ACCESS_TOKEN)', 'ok' => $set('access_token'), 'detail' => $set('access_token') ? 'Set (hidden)' : 'Missing'],
            ['label' => 'App secret, for webhook signatures (META_APP_SECRET)', 'ok' => $set('app_secret'), 'detail' => $set('app_secret') ? 'Set (hidden)' : 'Missing'],
            ['label' => 'Webhook verify token (WHATSAPP_VERIFY_TOKEN)', 'ok' => $set('verify_token'), 'detail' => $set('verify_token') ? 'Set (hidden)' : 'Missing'],
            ['label' => 'Sending switched on (WHATSAPP_ENABLED)', 'ok' => config('services.whatsapp.send_enabled') === true, 'detail' => config('services.whatsapp.send_enabled') === true ? 'On' : 'Off'],
        ];
        if (config('services.whatsapp.mode') === 'production') {
            $checks[] = ['label' => 'Production business account (WHATSAPP_PROD_WABA_ID)', 'ok' => $set('business_account_id'), 'detail' => $set('business_account_id') ? 'Set' : 'Missing'];
        }

        return $checks;
    }

    /** Reads the phone number's display number and verified name (Graph API phone number node). */
    public function testConnection(): array
    {
        if (! filled(config('services.whatsapp.phone_number_id')) || ! filled(config('services.whatsapp.access_token'))) {
            return ['ok' => false, 'detail' => 'Phone number ID and access token are required.'];
        }

        try {
            $response = Http::withToken(config('services.whatsapp.access_token'))->timeout($this->timeout())
                ->get($this->url(config('services.whatsapp.phone_number_id')), ['fields' => 'display_phone_number,verified_name']);
        } catch (ConnectionException) {
            return ['ok' => false, 'detail' => 'Could not reach graph.facebook.com.'];
        }

        if ($response->failed()) {
            $code = $response->json('error.code');

            return ['ok' => false, 'detail' => 'Meta answered HTTP '.$response->status().($code ? ' (code '.$code.')' : '').(isset(self::ERRORS[$code]) ? ': '.self::ERRORS[$code] : '').'.'];
        }

        $number = (string) $response->json('display_phone_number');

        return ['ok' => true, 'detail' => trim('Connected to '.($response->json('verified_name') ?: 'the business number').($number !== '' ? ' (ending '.substr(preg_replace('/\D+/', '', $number), -4).')' : '')).'.'];
    }

    protected function url(string $path): string
    {
        return sprintf('%s/%s/%s', config('services.whatsapp.base_url') ?: 'https://graph.facebook.com', config('services.whatsapp.graph_version'), $path);
    }

    protected function timeout(): int
    {
        return (int) config('services.whatsapp.timeout') ?: 30;
    }

    /** @return array<string, mixed> */
    protected function body(OutboundMessage $message): array
    {
        $base = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $message->recipient];
        $payload = $message->payload ?? [];

        return $base + match ($message->type) {
            'template' => ['type' => 'template', 'template' => $this->template($payload)],
            'interactive' => ['type' => 'interactive', 'interactive' => [
                'type' => 'button',
                'body' => ['text' => mb_substr($message->body, 0, 1024)],
                'action' => ['buttons' => array_map(fn ($b) => ['type' => 'reply', 'reply' => ['id' => $b['id'], 'title' => mb_substr($b['title'], 0, 20)]], array_slice($payload['buttons'] ?? [], 0, 3))],
            ]],
            'list' => ['type' => 'interactive', 'interactive' => [
                'type' => 'list',
                'body' => ['text' => mb_substr($message->body, 0, 1024)],
                'action' => [
                    'button' => mb_substr((string) ($payload['button'] ?? 'Choose'), 0, 20),
                    'sections' => array_map(fn ($section) => array_filter([
                        'title' => isset($section['title']) ? mb_substr($section['title'], 0, 24) : null,
                        'rows' => array_map(fn ($row) => array_filter([
                            'id' => mb_substr((string) $row['id'], 0, 200),
                            'title' => mb_substr((string) $row['title'], 0, 24),
                            'description' => isset($row['description']) ? mb_substr($row['description'], 0, 72) : null,
                        ]), array_slice($section['rows'] ?? [], 0, 10)),
                    ]), array_slice($payload['sections'] ?? [], 0, 10)),
                ],
            ]],
            'image', 'video', 'audio', 'document' => ['type' => $message->type, $message->type => array_filter([
                'id' => $payload['id'] ?? null,
                'link' => $payload['link'] ?? null,
                // WhatsApp does not accept captions on audio.
                'caption' => $message->type === 'audio' ? null : (filled($message->body) ? mb_substr($message->body, 0, 1024) : null),
                'filename' => $message->type === 'document' ? ($payload['filename'] ?? null) : null,
            ])],
            default => ['type' => 'text', 'text' => ['preview_url' => true, 'body' => mb_substr($message->body, 0, 4096)]],
        };
    }

    /**
     * Meta's sample hello_world has no variables, so "components" is left out
     * when there are no parameters.
     *
     * @return array<string, mixed>
     */
    protected function template(array $payload): array
    {
        $parameters = array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], $payload['parameters'] ?? []);

        return ['name' => $payload['template'], 'language' => ['code' => $payload['language'] ?? 'en']]
            + ($parameters ? ['components' => [['type' => 'body', 'parameters' => $parameters]]] : []);
    }
}
