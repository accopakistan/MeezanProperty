<?php

namespace App\Services\Agents\Providers;

use App\Services\Agents\OutputGuard;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google Gemini API (generateContent), as documented on 4 Nov 2026:
 *   POST {base}/models/{model}:generateContent        (x-goog-api-key header)
 *   POST {base}/models/{model}:streamGenerateContent?alt=sse
 *   GET  {base}/models                                  (model list, paged)
 *
 * The key is sent only in the header, never in a URL. "system" messages
 * become systemInstruction; "assistant" turns become role "model". JSON mode
 * sets responseMimeType (and responseJsonSchema when a schema is given).
 * Models from 3.6 on ignore temperature, so it is not sent to them; thinking
 * level is sent only to Gemini 3 and later, which accept it.
 *
 * Quota handling: a per-minute rate limit is retried with exponential
 * backoff (or Google's own retry delay); a daily or free-tier quota, missing
 * billing or a rejected key is never retried and is classed "quota" or "auth"
 * so the manager stops sending until it can succeed.
 */
class GeminiAdapter extends HttpAdapter
{
    public const MAX_RETRY_DELAY_MS = 20000;

    public function key(): string
    {
        return 'gemini';
    }

    public function label(): string
    {
        return 'Google Gemini';
    }

    public function minOutputTokens(): int
    {
        return (int) config('services.gemini.min_output_tokens', 512);
    }

    public function send(ProviderConfig $config, string $model, array $messages, float $temperature, int $maxTokens, bool $json, ?int $timeout = null, ?int $retries = null): array
    {
        [$system, $contents] = static::toContents($messages);
        $res = $this->generate($config, $model, $contents, $system, ['temperature' => $temperature, 'maxOutputTokens' => $maxTokens, 'json' => $json], [], $timeout, $retries);

        if ($res['ok'] && $res['function_calls'] !== []) {
            return $this->failed($model, 'malformed', 'The model asked to call a tool where none was offered.', $res['tokens_in'], $res['tokens_out']);
        }

        return array_diff_key($res, array_flip(['function_calls', 'content']));
    }

    /**
     * One generateContent call, with tools when given. The returned "content"
     * is the model turn exactly as received (with any thought signatures), to
     * be sent back unchanged in the next turn of a function-calling exchange.
     *
     * @param  list<array>  $contents  Gemini Content objects
     * @param  array{temperature?: float, maxOutputTokens?: int, json?: bool, schema?: array}  $options
     * @param  list<array>  $declarations  Gemini functionDeclarations
     * @return array{ok: bool, text: ?string, model: string, tokens_in: int, tokens_out: int, error: ?string, failure: ?string, function_calls: list<array{id: ?string, name: string, args: array}>, content: ?array, quota_reset: ?string}
     */
    public function generate(ProviderConfig $config, string $model, array $contents, ?string $system, array $options, array $declarations = [], ?int $timeout = null, ?int $retries = null): array
    {
        $body = $this->body($model, $contents, $system, $options, $declarations);

        try {
            $response = $this->post($config, $this->modelPath($model).':generateContent', $body, $timeout, $retries);
        } catch (Throwable $e) {
            return $this->withExtras($this->connectionFailure($model, $e));
        }
        if (! $response->successful()) {
            return $this->failure($model, $response);
        }

        return $this->parse($model, (array) $response->json());
    }

    /**
     * Streaming: text arrives through $onText as Google sends it (server-sent
     * events). Returns the same result as generate() once the stream ends.
     *
     * @param  callable(string): void  $onText
     */
    public function stream(ProviderConfig $config, string $model, array $messages, int $maxTokens, callable $onText, ?int $timeout = null): array
    {
        [$system, $contents] = static::toContents($messages);
        $body = $this->body($model, $contents, $system, ['maxOutputTokens' => $maxTokens], []);
        try {
            $response = Http::withHeaders(['x-goog-api-key' => $config->apiKey])->withOptions(['stream' => true])
                ->timeout(max(5, $timeout ?? $config->timeout))
                ->post($this->url($config, $this->modelPath($model).':streamGenerateContent?alt=sse'), $body);
        } catch (Throwable $e) {
            return $this->withExtras($this->connectionFailure($model, $e));
        }
        if (! $response->successful()) {
            return $this->failure($model, $response);
        }

        $text = '';
        $last = [];
        $buffer = '';
        $stream = $response->toPsrResponse()->getBody();
        while (! $stream->eof()) {
            $buffer .= $stream->read(8192);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if (! str_starts_with($line, 'data:')) {
                    continue;
                }
                $chunk = json_decode(trim(substr($line, 5)), true);
                if (! is_array($chunk)) {
                    continue;
                }
                $last = $chunk;
                foreach (data_get($chunk, 'candidates.0.content.parts', []) as $part) {
                    if (isset($part['text']) && empty($part['thought'])) {
                        $text .= $part['text'];
                        $onText($part['text']);
                    }
                }
            }
        }
        // Usage and finish reason come with the last chunk; the text is the whole stream.
        $last['candidates'][0]['content']['parts'] = [['text' => $text]];

        return $this->parse($model, $last);
    }

    /** Models this account can use for generateContent, without the "models/" prefix. */
    public function listModels(ProviderConfig $config): array
    {
        $models = [];
        $token = null;
        try {
            do {
                $response = Http::withHeaders(['x-goog-api-key' => $config->apiKey])->timeout(15)
                    ->get($this->url($config, 'models'), array_filter(['pageSize' => 1000, 'pageToken' => $token]));
                if (! $response->successful()) {
                    $failure = $this->classify($response);

                    return ['ok' => false, 'models' => [], 'error' => $failure[1], 'failure' => $failure[0]];
                }
                foreach ($response->json('models', []) as $m) {
                    if (in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true)) {
                        $models[] = preg_replace('#^models/#', '', (string) $m['name']);
                    }
                }
                $token = $response->json('nextPageToken');
            } while ($token && count($models) < 5000);
        } catch (Throwable) {
            return ['ok' => false, 'models' => [], 'error' => 'Google Gemini is unreachable.', 'failure' => 'temporary'];
        }
        sort($models);

        return ['ok' => true, 'models' => array_values(array_unique($models)), 'error' => null, 'failure' => null];
    }

    /* Request ---------------------------------------------------------------- */

    /**
     * Chat messages to Gemini: system text joined into systemInstruction,
     * consecutive turns of the same role merged (Gemini expects alternation).
     *
     * @return array{0: ?string, 1: list<array>}
     */
    public static function toContents(array $messages): array
    {
        $system = [];
        $contents = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $system[] = (string) $m['content'];

                continue;
            }
            $role = $m['role'] === 'assistant' ? 'model' : 'user';
            if ($contents && end($contents)['role'] === $role) {
                $contents[array_key_last($contents)]['parts'][] = ['text' => (string) $m['content']];
            } else {
                $contents[] = ['role' => $role, 'parts' => [['text' => (string) $m['content']]]];
            }
        }

        return [$system ? implode("\n\n", $system) : null, $contents ?: [['role' => 'user', 'parts' => [['text' => '']]]]];
    }

    protected function body(string $model, array $contents, ?string $system, array $options, array $declarations): array
    {
        $generation = array_filter(['maxOutputTokens' => $options['maxOutputTokens'] ?? null], fn ($v) => $v !== null);
        if (isset($options['temperature']) && static::acceptsTemperature($model)) {
            $generation['temperature'] = (float) $options['temperature'];
        }
        if (! empty($options['json'])) {
            $generation['responseMimeType'] = 'application/json';
            if (! empty($options['schema'])) {
                $generation['responseJsonSchema'] = $options['schema'];
            }
        }
        $level = (string) config('services.gemini.thinking_level');
        if ($level !== '' && static::acceptsThinkingLevel($model)) {
            $generation['thinkingConfig'] = ['thinkingLevel' => strtoupper($level)];
        }

        return array_filter([
            'systemInstruction' => $system !== null ? ['parts' => [['text' => $system]]] : null,
            'contents' => $contents,
            'tools' => $declarations ? [['functionDeclarations' => $declarations]] : null,
            'toolConfig' => $declarations ? ['functionCallingConfig' => ['mode' => 'AUTO']] : null,
            'generationConfig' => $generation ?: null,
        ], fn ($v) => $v !== null);
    }

    /** [major, minor] from a model ID such as "gemini-3.8-flash", or null when it does not follow that pattern. */
    public static function version(string $model): ?array
    {
        return preg_match('/^gemini-(\d+)(?:\.(\d+))?/', $model, $m) ? [(int) $m[1], (int) ($m[2] ?? 0)] : null;
    }

    /** Gemini 3.6 and later ignore temperature (Google's docs), so it is left out for them. */
    public static function acceptsTemperature(string $model): bool
    {
        $v = static::version($model);

        return $v !== null && ($v[0] < 3 || ($v[0] === 3 && $v[1] < 6));
    }

    /** thinkingLevel is for Gemini 3 and later; earlier models reject it. */
    public static function acceptsThinkingLevel(string $model): bool
    {
        $v = static::version($model);

        return $v !== null && $v[0] >= 3;
    }

    protected function post(ProviderConfig $config, string $path, array $body, ?int $timeout, ?int $retries): Response
    {
        return Http::withHeaders(['x-goog-api-key' => $config->apiKey])
            ->timeout(max(5, $timeout ?? $config->timeout))
            ->retry($this->attempts($config, $retries), fn (int $attempt, Throwable $e) => $this->backoff($attempt, $e), fn ($e) => $this->retryable($e), throw: false)
            ->post($this->url($config, $path), $body);
    }

    /** Exponential backoff with jitter, or Google's own retry delay when it gives one (capped). */
    protected function backoff(int $attempt, Throwable $e): int
    {
        if ($e instanceof RequestException) {
            $info = collect((array) $e->response->json('error.details', []))->firstWhere('@type', 'type.googleapis.com/google.rpc.RetryInfo');
            if (is_array($info) && preg_match('/^(\d+(?:\.\d+)?)s$/', (string) ($info['retryDelay'] ?? ''), $m)) {
                return (int) min(self::MAX_RETRY_DELAY_MS, (float) $m[1] * 1000);
            }
        }

        return (int) min(self::MAX_RETRY_DELAY_MS, 500 * (2 ** ($attempt - 1)) + random_int(0, 250));
    }

    protected function url(ProviderConfig $config, string $path): string
    {
        return rtrim($config->baseUrl, '/').'/'.ltrim($path, '/');
    }

    protected function modelPath(string $model): string
    {
        return 'models/'.rawurlencode(preg_replace('#^models/#', '', $model));
    }

    /* Response --------------------------------------------------------------- */

    protected function parse(string $model, array $json): array
    {
        $usage = $json['usageMetadata'] ?? [];
        $in = (int) ($usage['promptTokenCount'] ?? 0);
        // Thinking is billed as output.
        $out = (int) ($usage['candidatesTokenCount'] ?? 0) + (int) ($usage['thoughtsTokenCount'] ?? 0);
        $model = (string) ($json['modelVersion'] ?? $model);

        if (filled($json['promptFeedback']['blockReason'] ?? null)) {
            return $this->withExtras($this->failed($model, 'safety', 'Google Gemini declined this request.', $in, $out));
        }
        $candidate = $json['candidates'][0] ?? null;
        if (! is_array($candidate)) {
            return $this->withExtras($this->failed($model, 'malformed', 'Google Gemini returned no answer.', $in, $out));
        }
        $finish = (string) ($candidate['finishReason'] ?? '');
        $parts = $candidate['content']['parts'] ?? [];
        $text = collect($parts)->filter(fn ($p) => isset($p['text']) && empty($p['thought']))->pluck('text')->implode('');
        $calls = collect($parts)->filter(fn ($p) => isset($p['functionCall']['name']))
            ->map(fn ($p) => ['id' => $p['functionCall']['id'] ?? null, 'name' => (string) $p['functionCall']['name'], 'args' => (array) ($p['functionCall']['args'] ?? [])])->values()->all();

        if (in_array($finish, ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION', 'IMAGE_SAFETY'], true)) {
            return $this->withExtras($this->failed($model, 'safety', 'Google Gemini declined this request.', $in, $out));
        }
        if (in_array($finish, ['MALFORMED_FUNCTION_CALL', 'UNEXPECTED_TOOL_CALL', 'TOO_MANY_TOOL_CALLS'], true)) {
            return $this->withExtras($this->failed($model, 'malformed', 'Google Gemini produced an unusable tool call ('.strtolower($finish).').', $in, $out));
        }
        if (trim($text) === '' && $calls === []) {
            return $this->withExtras($finish === 'MAX_TOKENS'
                ? $this->failed($model, 'truncated', 'The token limit was reached before an answer: raise the maximum tokens.', $in, $out)
                : $this->failed($model, 'malformed', 'Google Gemini returned an empty reply.', $in, $out));
        }

        return ['ok' => true, 'text' => trim($text) === '' ? null : trim($text), 'model' => $model, 'tokens_in' => $in, 'tokens_out' => $out, 'error' => null, 'failure' => null,
            'function_calls' => $calls, 'content' => ['role' => 'model', 'parts' => $parts], 'quota_reset' => null];
    }

    protected function withExtras(array $result): array
    {
        return $result + ['function_calls' => [], 'content' => null, 'quota_reset' => null];
    }

    /** Retry rate limits, overload, timeouts and dropped connections; never quota, billing, keys or bad requests. */
    protected function retryable(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }
        if (! $e instanceof RequestException) {
            return false;
        }

        return in_array($this->classify($e->response)[0], ['temporary'], true);
    }

    protected function isCreditError(Response $response): bool
    {
        return $this->classify($response)[0] === 'quota';
    }

    /**
     * @return array{0: string, 1: string, 2: ?string} failure class, safe message, quota reset time (ISO)
     */
    protected function classify(Response $response): array
    {
        $status = $response->status();
        $code = strtoupper((string) ($response->json('error.status') ?? $response->json('error.code') ?? ''));
        $message = strtolower((string) $response->json('error.message'));
        $details = collect($response->json('error.details', []));
        $reasons = $details->pluck('reason')->filter()->map(fn ($r) => strtoupper($r));
        $quotaIds = $details->flatMap(fn ($d) => collect($d['violations'] ?? [])->pluck('quotaId'))->filter()->implode(' ');

        if ($reasons->contains('API_KEY_INVALID') || $status === 401 || in_array($code, ['UNAUTHENTICATED', 'AUTHENTICATION'], true) || str_contains($message, 'api key not valid')) {
            return ['auth', 'Google rejected the API key. Check the key in Google AI Studio and enter it again.', null];
        }
        if ($status === 403 || $code === 'PERMISSION_DENIED') {
            return ['auth', 'Google refused access for this key (it may be restricted to other APIs, blocked, or reported as leaked). Create a new key in Google AI Studio.', null];
        }
        if ($status === 402 || $code === 'PAYMENT_REQUIRED') {
            return ['quota', 'Google reports that the prepaid credit is used up. Add credit in Google AI Studio.', null];
        }
        if ($code === 'FAILED_PRECONDITION') {
            return ['quota', 'Google reports an unmet account requirement, usually billing: this project may not be eligible for the free tier. Link a billing account in Google AI Studio.', null];
        }
        if ($status === 404 || $code === 'NOT_FOUND' || $code === 'MODEL_NOT_FOUND') {
            return ['model', 'Model not found or not available to this key: choose one from your model list in Gemini Settings.', null];
        }
        if ($status === 429 || $code === 'RESOURCE_EXHAUSTED') {
            // A daily (often free-tier) quota does not come back by retrying: it resets at midnight Pacific time.
            if (str_contains($quotaIds, 'PerDay') || $code === 'QUOTA_EXCEEDED' || str_contains($message, 'per day')) {
                $reset = CarbonImmutable::now('America/Los_Angeles')->addDay()->startOfDay()->utc()->toIso8601String();

                return ['quota', 'Gemini daily quota exhausted'.(str_contains($quotaIds, 'FreeTier') ? ' (free tier)' : '').'. It resets at midnight Pacific time; no requests are sent until then.', $reset];
            }

            return ['temporary', 'Gemini rate limit reached (requests or tokens per minute). Retried with backoff.', null];
        }
        if ($status === 408 || $status >= 500 || in_array($code, ['UNAVAILABLE', 'DEADLINE_EXCEEDED', 'INTERNAL', 'SERVICE_UNAVAILABLE', 'API_ERROR'], true)) {
            // Google's own explanation (e.g. "This model is currently experiencing high demand") helps choose another model.
            return ['temporary', 'Google Gemini is temporarily unavailable (HTTP '.$status.').'.$this->detail($response), null];
        }

        return ['invalid', 'Google Gemini refused the request (HTTP '.$status.').'.$this->detail($response), null];
    }

    protected function failure(string $model, Response $response): array
    {
        [$failure, $error, $reset] = $this->classify($response);

        return ['quota_reset' => $reset] + $this->withExtras($this->failed($model, $failure, $error));
    }

    /** The provider's own message, cleaned; Gemini's messages for key errors are never shown. */
    protected function detail(Response $response): string
    {
        $message = $response->json('error.message');

        return is_string($message) ? ' '.OutputGuard::text(mb_substr($message, 0, 200)) : '';
    }
}
