<?php

namespace App\Services\Agents\Providers;

/**
 * Everything needed to call one provider, resolved by AiProvider from saved
 * settings and the environment. Holds the API key, so it stays server-side
 * and hides the key from debug output.
 */
final class ProviderConfig
{
    public function __construct(
        public readonly string $provider,
        public readonly string $label,
        public readonly ?string $apiKey,
        public readonly ?string $keySource,     // saved | env | null
        public readonly bool $keyUnreadable,
        public readonly string $baseUrl,
        public readonly ?string $defaultModel,
        public readonly bool $enabled,
        public readonly int $timeout,
        public readonly int $retries,
        public readonly array $options = [],
    ) {}

    public function hasKey(): bool
    {
        return filled($this->apiKey);
    }

    public function ready(): bool
    {
        return $this->enabled && $this->hasKey();
    }

    public function __debugInfo(): array
    {
        return ['provider' => $this->provider, 'hasKey' => $this->hasKey(), 'keySource' => $this->keySource, 'baseUrl' => $this->baseUrl, 'enabled' => $this->enabled];
    }
}
