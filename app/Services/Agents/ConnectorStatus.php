<?php

namespace App\Services\Agents;

use App\Models\AppSetting;
use App\Models\MarketSource;
use App\Services\WhatsApp\WhatsAppClient;

/**
 * Status of every external integration the agents might use.
 *
 *   not_configured  no credentials
 *   requires_setup  partly configured
 *   configured      credentials present, not checked
 *   verified        a real check succeeded (stored with its time)
 *   error           the last real check failed
 *
 * Nothing is reported as verified without a successful check.
 */
class ConnectorStatus
{
    /** @return array<string, array{label: string, status: string, detail: string, checked_at: ?string, checkable: bool}> */
    public function all(): array
    {
        $checks = AppSetting::get('connectors.checks') ?? [];
        $checks = is_array($checks) ? $checks : (json_decode((string) $checks, true) ?: []);
        $wa = app(WhatsAppClient::class);
        $filled = fn (...$values) => count(array_filter($values, fn ($v) => filled($v)));

        $list = [
            'gemini' => ['Google Gemini API (language model)', app(AiProvider::class)->config('gemini')->hasKey() ? 'configured' : 'not_configured',
                'Agents run in rules mode without a language model: reports still come from real data, but there is no model wording or classification.', true],
            'whatsapp' => ['WhatsApp Business Cloud API', $wa->isConfigured() ? 'configured' : ($filled(config('services.whatsapp.phone_number_id'), config('services.whatsapp.access_token'), config('services.whatsapp.app_secret')) ? 'requires_setup' : 'not_configured'),
                $wa->isLive() ? 'Live sending enabled.' : 'Sending is off or in mock mode; replies are recorded, not delivered.', true],
            'search_console' => ['Google Search Console', $filled(config('services.google.search_console_site'), config('services.google.search_console_credentials')) === 2 ? 'configured' : 'not_configured', 'Needed for live search rankings and indexing data.', false],
            'analytics' => ['Google Analytics', $filled(config('services.google.analytics_property_id'), config('services.google.analytics_credentials')) === 2 ? 'configured' : 'not_configured', 'Needed for traffic and campaign figures.', false],
            'meta_graph' => ['Meta Graph API (Facebook / Instagram)', $filled(config('services.meta_graph.page_id'), config('services.meta_graph.access_token')) === 2 ? 'configured' : 'not_configured', 'Needed to publish posts or read social engagement.', false],
            'email' => ['Email provider', in_array(config('mail.default'), ['log', 'array', null], true) ? 'not_configured' : 'configured', 'Mailer: '.config('mail.default').'.', false],
            'payments' => ['Payment gateway', config('meezan.billing.gateways.manual.enabled') ? 'configured' : 'not_configured', 'Manual bank transfer '.(config('meezan.billing.gateways.manual.enabled') ? 'enabled' : 'disabled').'; online gateways are not connected. Agents never move money.', false],
            'sms' => ['SMS provider', $filled(config('services.sms.provider'), config('services.sms.api_key')) === 2 ? 'configured' : 'not_configured', 'Not used by any workflow yet.', false],
            'web_search' => ['Web search (research)', $filled(config('services.web_search.provider'), config('services.web_search.api_key')) === 2 ? 'configured' : 'not_configured', 'Needed for live competitor and partner research; without it agents use approved research notes only.', false],
            'maps' => ['Map tiles / style', filled(config('meezan.map.style_url')) || filled(config('meezan.map.tile_url')) ? 'configured' : 'not_configured', 'Public map services (no key).', false],
            'market_data' => ['Approved property data sources', MarketSource::where('is_active', true)->exists() ? 'configured' : 'not_configured', 'Sources registered in the Market Data Centre.', false],
        ];

        return collect($list)->map(function ($c, $key) use ($checks) {
            [$label, $status, $detail, $checkable] = $c;
            $check = $checks[$key] ?? null;
            if ($check && $status !== 'not_configured') {
                $status = $check['status'];
                $detail = $check['detail'].' '.$detail;
            }

            return ['label' => $label, 'status' => $status, 'detail' => trim($detail), 'checked_at' => $check['at'] ?? null, 'checkable' => $checkable];
        })->all();
    }

    /** Run a real check where one exists and store the outcome. */
    public function check(string $key): array
    {
        $current = $this->all()[$key]['status'] ?? 'not_configured';
        $result = match ($key) {
            'gemini' => app(AiProvider::class)->check($key),
            'whatsapp' => $this->checkWhatsApp($current),
            // Without a real check the status stays what configuration says; it is never upgraded to "verified".
            default => ['status' => $current === 'verified' ? 'configured' : $current, 'detail' => 'No automatic check exists for this connector.', 'store' => false],
        };
        if (($result['store'] ?? true) === false) {
            unset($result['store']);

            return $result;
        }
        $this->remember($key, $result);

        return $result;
    }

    /** Store a real check's outcome, or forget it (null) when the credentials behind it have changed. */
    public function remember(string $key, ?array $result): void
    {
        $checks = AppSetting::get('connectors.checks') ?? [];
        $checks = is_array($checks) ? $checks : (json_decode((string) $checks, true) ?: []);
        if ($result === null) {
            unset($checks[$key]);
        } elseif ($result['status'] !== 'not_configured') {
            $checks[$key] = $result + ['at' => now()->toDateTimeString()];
        }
        AppSetting::put('connectors.checks', $checks, auth()->id());
    }

    /** Uses the WhatsApp provider's own connection test (Meta Cloud API); the mock provider cannot be verified. */
    protected function checkWhatsApp(string $current): array
    {
        $client = app(WhatsAppClient::class);
        if ($current === 'not_configured' || $client->provider()->key() === 'mock') {
            return ['status' => $current, 'detail' => 'The mock or unconfigured WhatsApp provider cannot be verified.', 'store' => false];
        }
        $test = $client->provider()->testConnection();

        return ['status' => $test['ok'] ? 'verified' : 'error', 'detail' => (string) ($test['detail'] ?? '')];
    }
}
