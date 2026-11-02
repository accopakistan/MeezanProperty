<?php

namespace App\Services\Billing\Gateways;

use App\Models\Payment;

/**
 * Bank transfer, confirmed by staff. The dealer transfers the amount with
 * the invoice number as reference and submits their transaction reference
 * (and optionally proof); nothing is activated until an admin approves it.
 * Offered only when the bank account details are configured.
 */
class ManualGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'Bank transfer';
    }

    public function isAvailable(): bool
    {
        $config = config('meezan.billing.gateways.manual');

        return $config['enabled'] && filled($config['account_title']) && (filled($config['account_number']) || filled($config['iban']));
    }

    public function isTest(): bool
    {
        return false;
    }

    public function checkoutUrl(Payment $payment): string
    {
        return route('dealer.billing.payments.show', $payment);
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }

    public function verifySignature(string $rawBody, ?string $signature): bool
    {
        return false;
    }

    public function parseEvent(array $payload): ?GatewayEvent
    {
        return null;
    }

    /** @return array<string, string> label => value, configured details only */
    public function accountDetails(): array
    {
        $config = config('meezan.billing.gateways.manual');

        return array_filter([
            'Bank' => $config['bank_name'],
            'Account title' => $config['account_title'],
            'Account number' => $config['account_number'],
            'IBAN' => $config['iban'],
        ]);
    }
}
