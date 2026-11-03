<?php

namespace App\Services\Agents\Providers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * OpenAI chat completions (and OpenAI-compatible servers, through the base
 * URL). OpenAI's own API takes max_completion_tokens; other servers get the
 * older max_tokens. Some models accept only their default temperature, so a
 * refusal naming it is retried once without it.
 */
class OpenAiAdapter extends HttpAdapter
{
    public function key(): string
    {
        return 'openai';
    }

    public function label(): string
    {
        return 'OpenAI';
    }

    public function minOutputTokens(): int
    {
        return (int) config('services.openai.min_output_tokens', 256);
    }

    public function send(ProviderConfig $config, string $model, array $messages, float $temperature, int $maxTokens, bool $json, ?int $timeout = null, ?int $retries = null): array
    {
        $official = parse_url($config->baseUrl, PHP_URL_HOST) === 'api.openai.com';
        $body = ['model' => $model, 'temperature' => $temperature, ($official ? 'max_completion_tokens' : 'max_tokens') => $maxTokens, 'messages' => $messages]
            + ($json ? ['response_format' => ['type' => 'json_object']] : [])
            + ($official && filled($effort = config('services.openai.reasoning_effort')) ? ['reasoning_effort' => $effort] : []);

        try {
            $response = $this->post($config, $body, $timeout, $retries);
            // Models differ in what they accept: drop a setting the provider names as refused, and try again.
            foreach (['temperature' => 'temperature', 'reasoning_effort' => 'reasoning'] as $setting => $word) {
                if ($response->status() === 400 && isset($body[$setting]) && str_contains(strtolower((string) $response->json('error.message')), $word)) {
                    unset($body[$setting]);
                    $response = $this->post($config, $body, $timeout, $retries);
                }
            }
        } catch (Throwable $e) {
            return $this->connectionFailure($model, $e);
        }

        if (! $response->successful()) {
            return $this->failure($model, $response);
        }

        $in = (int) $response->json('usage.prompt_tokens', 0);
        $out = (int) $response->json('usage.completion_tokens', 0);
        $text = $response->json('choices.0.message.content');
        if (filled($response->json('choices.0.message.refusal')) || $response->json('choices.0.finish_reason') === 'content_filter') {
            return $this->failed($model, 'safety', 'The provider declined this request.', $in, $out);
        }
        if (! is_string($text) || trim($text) === '') {
            return $response->json('choices.0.finish_reason') === 'length'
                ? $this->failed($model, 'truncated', 'The token limit was reached before an answer: raise the maximum tokens.', $in, $out)
                : $this->failed($model, 'malformed', 'Provider returned an empty or malformed reply.', $in, $out);
        }

        return $this->succeeded((string) $response->json('model', $model), $text, $in, $out);
    }

    public function listModels(ProviderConfig $config): array
    {
        try {
            $response = Http::withToken($config->apiKey)->timeout(10)->get(rtrim($config->baseUrl, '/').'/models');
        } catch (Throwable) {
            return ['ok' => false, 'models' => [], 'error' => 'Provider unreachable.', 'failure' => 'temporary'];
        }
        if (! $response->successful()) {
            return ['ok' => false, 'models' => [], 'error' => $this->listFailure($response->status()), 'failure' => in_array($response->status(), [401, 403], true) ? 'auth' : 'temporary'];
        }

        return ['ok' => true, 'models' => collect($response->json('data', []))->pluck('id')->filter()->sort()->values()->all(), 'error' => null, 'failure' => null];
    }

    protected function post(ProviderConfig $config, array $body, ?int $timeout, ?int $retries): Response
    {
        return Http::withToken($config->apiKey)
            ->timeout(max(5, $timeout ?? $config->timeout))
            ->retry($this->attempts($config, $retries), 500, fn ($e) => $this->retryable($e), throw: false)
            ->post(rtrim($config->baseUrl, '/').'/chat/completions', $body);
    }

    protected function isCreditError(Response $response): bool
    {
        return in_array($response->json('error.code'), ['insufficient_quota', 'credit_balance_exhausted', 'billing_not_active'], true) || $response->json('error.type') === 'insufficient_quota';
    }

    protected function failure(string $model, Response $response): array
    {
        return match (true) {
            $this->isCreditError($response) => $this->failed($model, 'quota', self::NO_CREDIT),
            in_array($response->status(), [401, 403], true) => $this->failed($model, 'auth', 'Provider rejected the credentials.'),
            $response->status() === 404 || $response->json('error.code') === 'model_not_found' => $this->failed($model, 'model', 'Model not found: check the model name.'),
            $response->status() === 429 => $this->failed($model, 'temporary', 'Provider rate limit reached.'),
            $response->status() >= 500 => $this->failed($model, 'temporary', 'Provider error (HTTP '.$response->status().').'),
            default => $this->failed($model, 'invalid', 'Provider refused the request (HTTP '.$response->status().').'.$this->detail($response)),
        };
    }
}
