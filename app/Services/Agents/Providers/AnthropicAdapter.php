<?php

namespace App\Services\Agents\Providers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Anthropic Messages API over HTTP. Authenticates with x-api-key and sends
 * the anthropic-version header. System text goes in the top-level "system"
 * field. Sampling settings are not sent: API version 2026-10-21 rejects them
 * on every model. Depth is set with output_config.effort, which older models
 * refuse, so that refusal is retried once without it.
 */
class AnthropicAdapter extends HttpAdapter
{
    public function key(): string
    {
        return 'anthropic';
    }

    public function label(): string
    {
        return 'Anthropic Claude';
    }

    public function minOutputTokens(): int
    {
        return (int) config('services.anthropic.min_output_tokens', 1024);
    }

    public function send(ProviderConfig $config, string $model, array $messages, float $temperature, int $maxTokens, bool $json, ?int $timeout = null, ?int $retries = null): array
    {
        $system = collect($messages)->where('role', 'system')->pluck('content')->implode("\n\n");
        if ($json) {
            $system = trim($system."\n\nReply with a single JSON object and nothing else.");
        }
        $body = ['model' => $model, 'max_tokens' => $maxTokens, 'messages' => collect($messages)->where('role', '!=', 'system')->values()->all()]
            + ($system !== '' ? ['system' => $system] : [])
            + (filled($config->options['effort'] ?? null) ? ['output_config' => ['effort' => $config->options['effort']]] : []);

        try {
            $response = $this->post($config, $body, $timeout, $retries);
            $message = strtolower((string) $response->json('error.message'));
            if ($response->status() === 400 && isset($body['output_config']) && (str_contains($message, 'effort') || str_contains($message, 'output_config'))) {
                unset($body['output_config']);
                $response = $this->post($config, $body, $timeout, $retries);
            }
        } catch (Throwable $e) {
            return $this->connectionFailure($model, $e);
        }

        if (! $response->successful()) {
            return $this->failure($model, $response);
        }

        $in = (int) $response->json('usage.input_tokens', 0) + (int) $response->json('usage.cache_read_input_tokens', 0) + (int) $response->json('usage.cache_creation_input_tokens', 0);
        $out = (int) $response->json('usage.output_tokens', 0);
        $stop = $response->json('stop_reason');
        if ($stop === 'refusal') {
            return $this->failed($model, 'safety', 'The provider declined this request.', $in, $out);
        }
        $text = collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode('');
        if (trim($text) === '') {
            return $stop === 'max_tokens'
                ? $this->failed($model, 'truncated', 'The token limit was reached before an answer: raise the maximum tokens.', $in, $out)
                : $this->failed($model, 'malformed', 'Provider returned an empty or malformed reply.', $in, $out);
        }
        if ($json) {
            $text = preg_replace('/^\s*```(?:json)?\s*|\s*```\s*$/i', '', $text);
        }

        return $this->succeeded((string) $response->json('model', $model), $text, $in, $out);
    }

    public function listModels(ProviderConfig $config): array
    {
        try {
            $response = $this->request($config)->timeout(10)->get(rtrim($config->baseUrl, '/').'/v1/models', ['limit' => 100]);
        } catch (Throwable) {
            return ['ok' => false, 'models' => [], 'error' => 'Provider unreachable.', 'failure' => 'temporary'];
        }
        if (! $response->successful()) {
            return ['ok' => false, 'models' => [], 'error' => $this->listFailure($response->status()), 'failure' => in_array($response->status(), [401, 403], true) ? 'auth' : 'temporary'];
        }

        return ['ok' => true, 'models' => collect($response->json('data', []))->pluck('id')->filter()->values()->all(), 'error' => null, 'failure' => null];
    }

    protected function request(ProviderConfig $config)
    {
        return Http::withHeaders(['x-api-key' => $config->apiKey, 'anthropic-version' => $config->options['version'] ?? '2026-10-21'])->acceptJson();
    }

    protected function post(ProviderConfig $config, array $body, ?int $timeout, ?int $retries): Response
    {
        return $this->request($config)
            ->timeout(max(5, $timeout ?? $config->timeout))
            ->retry($this->attempts($config, $retries), 500, fn ($e) => $this->retryable($e), throw: false)
            ->post(rtrim($config->baseUrl, '/').'/v1/messages', $body);
    }

    protected function isCreditError(Response $response): bool
    {
        return $response->status() === 402 || $response->json('error.type') === 'billing_error'
            || str_contains(strtolower((string) $response->json('error.message')), 'credit balance');
    }

    protected function failure(string $model, Response $response): array
    {
        return match (true) {
            $this->isCreditError($response) => $this->failed($model, 'quota', self::NO_CREDIT),
            in_array($response->status(), [401, 403], true) => $this->failed($model, 'auth', 'Provider rejected the credentials.'),
            $response->status() === 404 => $this->failed($model, 'model', 'Model not found: check the model name, or that this account can use it.'),
            $response->status() === 429 => $this->failed($model, 'temporary', 'Provider rate limit reached.'),
            $response->status() >= 500 => $this->failed($model, 'temporary', 'Provider error (HTTP '.$response->status().').'),
            default => $this->failed($model, 'invalid', 'Provider refused the request (HTTP '.$response->status().').'.$this->detail($response)),
        };
    }
}
