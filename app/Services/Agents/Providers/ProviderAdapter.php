<?php

namespace App\Services\Agents\Providers;

/**
 * One language-model provider's API. Adapters only translate requests and
 * responses: limits, routing, fallback and usage records live in AiProvider,
 * so no agent or feature talks to a provider directly.
 *
 * Failure classes decide what may happen next:
 *   temporary   service trouble or rate limit (may fall back)
 *   auth        key rejected (needs an administrator)
 *   quota       no API credit (needs an administrator)
 *   model       model not found or not available to this account
 *   invalid     the request was refused as invalid
 *   safety      the provider declined the content
 *   truncated   the token limit left no answer
 *   malformed   the reply could not be read
 */
interface ProviderAdapter
{
    public function key(): string;

    public function label(): string;

    /** The lowest useful output limit: thinking models spend part of it before answering. */
    public function minOutputTokens(): int;

    /**
     * @param  list<array{role: string, content: string}>  $messages  may include "system" messages
     * @return array{ok: bool, text: ?string, model: string, tokens_in: int, tokens_out: int, error: ?string, failure: ?string}
     */
    public function send(ProviderConfig $config, string $model, array $messages, float $temperature, int $maxTokens, bool $json, ?int $timeout = null, ?int $retries = null): array;

    /** @return array{ok: bool, models: list<string>, error: ?string, failure: ?string} */
    public function listModels(ProviderConfig $config): array;
}
