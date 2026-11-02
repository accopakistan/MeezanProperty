<?php

namespace App\Services\WhatsApp\Providers;

use App\Models\OutboundMessage;

/**
 * A way to send WhatsApp messages. The rest of the application talks to
 * this interface (through OutboundMessenger), never to Meta directly.
 */
interface WhatsAppProvider
{
    public function key(): string;

    public function label(): string;

    /** Can send from this environment with the configuration present. */
    public function canSend(): bool;

    /** Records only; nothing reaches a real phone. */
    public function isTest(): bool;

    /** One attempt to deliver a text, interactive or template message. */
    public function send(OutboundMessage $message): SendResult;

    /**
     * Configuration and connection checks, without revealing any secret.
     *
     * @return list<array{label: string, ok: ?bool, detail: string}> ok null = not checked
     */
    public function configurationChecks(): array;

    /** Ask the provider whether the credentials work. @return array{ok: bool, detail: string} */
    public function testConnection(): array;
}
