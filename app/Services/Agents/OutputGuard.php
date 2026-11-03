<?php

namespace App\Services\Agents;

use App\Models\AiProviderSetting;

/**
 * Last line of defence on everything agents output or store: removes
 * secrets (API keys, tokens, passwords, the app key) and private contact
 * details (emails, phone numbers) from strings at any depth.
 */
class OutputGuard
{
    public static function clean(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([static::class, 'clean'], $value);
        }
        if (! is_string($value)) {
            return $value;
        }

        return static::text($value);
    }

    public static function text(string $text): string
    {
        foreach (array_filter([
            ...AiProviderSetting::storedKeys(), config('services.openai.key'), config('services.anthropic.key'), config('app.key'), config('services.whatsapp.access_token'), config('services.whatsapp.app_secret'), config('services.meta_graph.access_token'), config('services.sms.api_key'), config('services.web_search.api_key'),
            config('database.connections.mysql.password'), config('mail.mailers.smtp.password'),
        ], fn ($s) => is_string($s) && strlen($s) >= 6) as $secret) {
            $text = str_replace($secret, '[redacted]', $text);
        }

        $text = preg_replace([
            '/\bsk-[A-Za-z0-9_\-]{16,}\b/',                         // OpenAI-style keys
            '/\bbase64:[A-Za-z0-9+\/=]{20,}/',                       // Laravel app keys
            '/\b(?:EAA|ghp_|xox[bap]-)[A-Za-z0-9_\-]{16,}\b/',       // Meta / GitHub / Slack tokens
            '/\b(password|passwd|secret|api[_ -]?key|token)\s*[:=]\s*\S+/i',
            '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',     // email addresses
            '/(?<!\d)(?:\+?92|0)3\d{2}[\s\-]?\d{7}(?!\d)/',          // Pakistani mobile numbers
        ], ['[redacted key]', '[redacted key]', '[redacted token]', '$1: [redacted]', '[email hidden]', '[phone hidden]'], $text);

        return $text;
    }
}
