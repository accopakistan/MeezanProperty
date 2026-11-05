<?php

namespace App\Services\WhatsApp;

use App\Models\AppSetting;
use App\Models\WebhookEvent;
use App\Services\WhatsApp\Providers\MetaCloudProvider;
use App\Services\WhatsApp\Providers\MockProvider;
use App\Services\WhatsApp\Providers\WhatsAppProvider;

/**
 * Entry point to the WhatsApp channel's configuration: which provider sends
 * (WHATSAPP_MOCK_MODE or MEEZAN_WHATSAPP_DRIVER, or the mock provider while
 * the admin Mock mode switch is on) and
 * whether incoming webhooks can be accepted. Sending itself goes through
 * OutboundMessenger, which applies MessagePolicy first.
 */
class WhatsAppClient
{
    /** Where outgoing messages go right now. */
    public const DELIVERY = [
        'mock' => 'Mock messages (not sent)',
        'meta_test' => 'Real Meta test messages',
        'production' => 'Production messages',
        'off' => 'Nothing is being sent',
    ];

    public function __construct(protected WhatsAppSettings $settings) {}

    public function provider(): WhatsAppProvider
    {
        if ($this->settings->testMode() || config('meezan.whatsapp.driver') === 'mock') {
            return app(MockProvider::class);
        }

        return app(MetaCloudProvider::class);
    }

    /** mock, meta_test, production or off. Nothing here ever switches modes by itself. */
    public function deliveryMode(): string
    {
        return match (true) {
            $this->provider()->isTest() => 'mock',
            ! $this->canSend() => 'off',
            $this->isMetaTestMode() => 'meta_test',
            default => 'production',
        };
    }

    /**
     * What is holding mock mode on. Both must be off for Meta to be used.
     *
     * @return array{on: bool, env: bool, switch: bool}
     */
    public function mockStatus(): array
    {
        $env = config('meezan.whatsapp.driver') === 'mock';
        $switch = $this->settings->testMode();

        return ['on' => $env || $switch, 'env' => $env, 'switch' => $switch];
    }

    /**
     * Can a real test message go out once mock mode is off? Needs the access
     * token, not the app secret or verify token, which are only for incoming webhooks.
     *
     * @return array{ready: bool, missing: list<string>}
     */
    public function metaTestStatus(): array
    {
        $c = config('services.whatsapp');
        $id = $this->isMetaTestMode() ? ($c['phone_number_id'] ?? null) : ($c['test_phone_number_id'] ?? null);
        $missing = array_values(array_filter([
            ($c['send_enabled'] ?? false) === true ? null : 'WHATSAPP_ENABLED is not true.',
            filled($id) ? null : 'WHATSAPP_TEST_PHONE_NUMBER_ID is missing. It is on Meta App → WhatsApp → API Setup.',
            filled($c['access_token'] ?? null) ? null : 'WHATSAPP_ACCESS_TOKEN is missing. Generate it on Meta App → WhatsApp → API Setup. Temporary tokens last about a day.',
            filled($c['test_recipient'] ?? null) ? null : 'WHATSAPP_TEST_RECIPIENT is missing. Use a number verified in Meta\'s recipient list.',
            ...$this->blockingProblems($id),
        ]));

        return ['ready' => $missing === [], 'missing' => $missing];
    }

    /**
     * Whether production has what it needs. Being ready does not activate it:
     * only WHATSAPP_MODE=production does that.
     *
     * @return array{ready: bool, active: bool, missing: list<string>}
     */
    public function productionStatus(): array
    {
        $c = config('services.whatsapp');
        $production = ! $this->isMetaTestMode();
        $id = $production ? ($c['phone_number_id'] ?? null) : ($c['prod_phone_number_id'] ?? null);
        $account = $production ? ($c['business_account_id'] ?? null) : ($c['prod_business_account_id'] ?? null);
        $missing = array_values(array_filter([
            filled($id) ? null : 'WHATSAPP_PROD_PHONE_NUMBER_ID is not set.',
            filled($account) ? null : 'WHATSAPP_PROD_WABA_ID is not set.',
            filled($c['access_token'] ?? null) ? null : 'WHATSAPP_ACCESS_TOKEN is missing.',
            filled($c['app_secret'] ?? null) ? null : 'META_APP_SECRET is missing.',
            filled($c['verify_token'] ?? null) ? null : 'WHATSAPP_VERIFY_TOKEN is missing.',
        ]));

        return ['ready' => $missing === [], 'active' => $production, 'missing' => $missing];
    }

    /**
     * Incoming messages count as working only with evidence: a public address,
     * Meta's verification, and a real message delivered through the webhook,
     * which is the only proof that the messages field is subscribed from here.
     *
     * @return array{working: bool, public: bool, verified: bool, received: bool}
     */
    public function inboundStatus(): array
    {
        $url = (string) (config('services.whatsapp.webhook_url') ?: config('app.url'));
        $host = (string) parse_url($url, PHP_URL_HOST);
        $public = str_starts_with($url, 'https://') && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            && ! str_ends_with($host, '.test') && ! str_ends_with($host, '.localhost');
        $verified = filled(AppSetting::get('whatsapp.webhook_verified_at'));
        $received = WebhookEvent::where('provider', 'whatsapp')->whereIn('event_type', ['messages', 'mixed'])->exists();

        return ['working' => $public && $verified && $received, 'public' => $public, 'verified' => $verified, 'received' => $received];
    }

    /**
     * Mistakes in the settings, such as a phone number where the Phone Number ID
     * belongs. Secrets are only checked for shape; their values are never reported.
     *
     * @return list<string>
     */
    public function formatProblems(): array
    {
        $c = config('services.whatsapp');
        $digits = fn (?string $number) => preg_replace('/\D+/', '', (string) $number);
        $customer = $digits(config('meezan.contact.whatsapp'));
        $sender = $digits($c['test_phone_number'] ?? null);
        $version = (string) ($c['graph_version'] ?? '');
        $recipient = (string) ($c['test_recipient'] ?? '');
        $mode = (string) ($c['mode_setting'] ?? 'test');

        return array_values(array_filter([
            ...$this->blockingProblems($c['phone_number_id'] ?? null),
            in_array($mode, ['test', 'production'], true) ? null : 'WHATSAPP_MODE should be test or production. Any other value is treated as test.',
            filled($c['business_account_id'] ?? null) && ! ctype_digit((string) $c['business_account_id']) ? 'The WhatsApp Business Account ID should contain digits only.' : null,
            $sender !== '' && $sender === $customer ? 'WHATSAPP_TEST_PHONE_NUMBER is set to the customer-facing number. It should be Meta\'s test sender.' : null,
            $recipient !== '' && ($recipient !== $digits($recipient) || strlen($recipient) < 10 || strlen($recipient) > 15) ? 'WHATSAPP_TEST_RECIPIENT should be 10 to 15 digits, with the country code.' : null,
            $version !== '' && ! preg_match('/^v\d+\.\d+$/', $version) ? 'The Graph API version should look like v25.0.' : null,
            preg_match('/[\s"\']/', (string) ($c['app_secret'] ?? '')) ? 'META_APP_SECRET contains spaces or quote marks, which usually means a copying mistake.' : null,
            preg_match('/[\s"\']/', (string) ($c['verify_token'] ?? '')) ? 'WHATSAPP_VERIFY_TOKEN contains spaces or quote marks, which usually means a copying mistake.' : null,
        ]));
    }

    /** Problems that would make every Meta request fail. @return list<string> */
    protected function blockingProblems(?string $phoneNumberId): array
    {
        $digits = fn (?string $number) => preg_replace('/\D+/', '', (string) $number);
        $c = config('services.whatsapp');
        $numbers = array_filter([$digits(config('meezan.contact.whatsapp')), $digits($c['test_phone_number'] ?? null), $digits($c['test_recipient'] ?? null)]);
        $id = (string) $phoneNumberId;

        return array_values(array_filter([
            $id !== '' && ! ctype_digit($id) ? 'The Phone Number ID should contain digits only.' : null,
            $id !== '' && in_array($id, $numbers, true) ? 'The Phone Number ID is set to a phone number. Use the ID from Meta App → WhatsApp → API Setup.' : null,
            preg_match('/[\s"\']/', (string) ($c['access_token'] ?? '')) ? 'WHATSAPP_ACCESS_TOKEN contains spaces or quote marks, which usually means a copying mistake.' : null,
        ]));
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

    /** Meta's developer test setup: only the verified test recipient can receive messages. */
    public function isMetaTestMode(): bool
    {
        return config('services.whatsapp.mode') !== 'production';
    }

    /** May this contact receive a real message in the current mode? */
    public function allowedInCurrentMode(string $contact): bool
    {
        if (! $this->isMetaTestMode() || $this->provider()->isTest()) {
            return true;
        }
        $recipient = (string) config('services.whatsapp.test_recipient');

        return $recipient !== '' && hash_equals($recipient, $contact);
    }

    /**
     * Non-secret settings for the admin page. Secrets appear only as yes or no.
     *
     * @return array<string, mixed>
     */
    public function details(): array
    {
        $c = config('services.whatsapp');

        return [
            'mode' => $this->isMetaTestMode() ? 'test' : 'production',
            'app_id' => $c['app_id'] ?? null,
            'business_portfolio_id' => $c['business_portfolio_id'] ?? null,
            'business_account_id' => $c['business_account_id'] ?? null,
            'phone_number_id' => $c['phone_number_id'] ?? null,
            'sender' => $this->isMetaTestMode() ? ($c['test_phone_number'] ?? null) : null,
            'test_sender' => $c['test_phone_number'] ?? null,
            'test_phone_number_id' => $this->isMetaTestMode() ? ($c['phone_number_id'] ?? null) : ($c['test_phone_number_id'] ?? null),
            'test_recipient' => $c['test_recipient'] ?? null,
            'customer_number' => config('meezan.contact.whatsapp'),
            'test_recipient_last4' => filled($c['test_recipient'] ?? null) ? substr($c['test_recipient'], -4) : null,
            'graph_version' => $c['graph_version'] ?? null,
            'token_set' => filled($c['access_token'] ?? null),
            'secret_set' => filled($c['app_secret'] ?? null),
            'verify_set' => filled($c['verify_token'] ?? null),
            'enabled' => ($c['send_enabled'] ?? false) === true,
        ];
    }

    /**
     * What stops the integration working, as plain warnings. Nothing is ever
     * generated to fill a gap.
     *
     * @return list<string>
     */
    public function configurationWarnings(): array
    {
        $d = $this->details();

        return array_values(array_filter([
            $d['enabled'] ? null : 'Sending is off: WHATSAPP_ENABLED is not true.',
            $d['phone_number_id'] ? null : ($d['mode'] === 'test' ? 'WHATSAPP_TEST_PHONE_NUMBER_ID is missing.' : 'WHATSAPP_PROD_PHONE_NUMBER_ID is missing.'),
            $d['token_set'] ? null : 'WHATSAPP_ACCESS_TOKEN is missing, so nothing can be sent.',
            $d['secret_set'] ? null : 'META_APP_SECRET is missing, so incoming webhooks are refused.',
            $d['verify_set'] ? null : 'WHATSAPP_VERIFY_TOKEN is missing, so Meta cannot verify the webhook.',
            $d['mode'] === 'test' && ! config('services.whatsapp.test_recipient') ? 'WHATSAPP_TEST_RECIPIENT is missing, so test mode has nobody to send to.' : null,
            $d['mode'] === 'production' && ! $d['business_account_id'] ? 'WHATSAPP_PROD_WABA_ID is missing, so production sending stays off.' : null,
        ]));
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
