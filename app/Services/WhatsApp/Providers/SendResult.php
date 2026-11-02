<?php

namespace App\Services\WhatsApp\Providers;

/**
 * Outcome of one send attempt. retryable: a temporary problem (rate limit,
 * provider or network error) where trying again later may succeed.
 */
final class SendResult
{
    public function __construct(
        public readonly bool $sent,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $error = null,
        public readonly bool $retryable = false,
    ) {}

    public static function sent(string $id): self
    {
        return new self(true, $id);
    }

    public static function failed(string $error, bool $retryable = false): self
    {
        return new self(false, null, $error, $retryable);
    }
}
