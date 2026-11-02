<?php

namespace App\Services\WhatsApp\Providers;

use App\Models\OutboundMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * TEST / MOCK provider for local development. It is not WhatsApp: nothing
 * leaves the server, message ids start with "mock.", and every message it
 * handles is flagged as a test. Only available when APP_ENV is local or
 * testing. The next send can be made to fail from Admin → WhatsApp.
 */
class MockProvider implements WhatsAppProvider
{
    public const FAIL_NEXT = 'whatsapp:mock:fail-next';

    public function key(): string
    {
        return 'mock';
    }

    public function label(): string
    {
        return 'Mock provider (TEST, nothing is sent)';
    }

    public function canSend(): bool
    {
        return app()->environment(['local', 'testing']);
    }

    public function isTest(): bool
    {
        return true;
    }

    public function send(OutboundMessage $message): SendResult
    {
        if (! $this->canSend()) {
            return SendResult::failed('The mock provider is only available locally.');
        }
        if ($mode = Cache::pull(self::FAIL_NEXT)) {
            return SendResult::failed('Simulated '.($mode === 'temporary' ? 'temporary' : 'permanent').' failure (test)', retryable: $mode === 'temporary');
        }

        return SendResult::sent('mock.'.Str::lower(Str::random(24)));
    }

    public static function failNext(string $mode = 'permanent'): void
    {
        Cache::put(self::FAIL_NEXT, $mode, now()->addHour());
    }

    public function configurationChecks(): array
    {
        return [['label' => 'Mock provider (local testing only)', 'ok' => $this->canSend(), 'detail' => $this->canSend() ? 'Active: messages are recorded, not sent' : 'Not allowed in this environment']];
    }

    public function testConnection(): array
    {
        return ['ok' => false, 'detail' => 'The mock provider has no real connection to test.'];
    }
}
