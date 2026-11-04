<?php

namespace App\Services\InvestmentIndex\Collection;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Polite HTTP for official sources: identifies itself, obeys robots.txt
 * (a robots.txt that cannot be read because of a server error means "not
 * allowed"; a missing one means no rules), waits between requests, and
 * refuses responses over the size limit.
 */
class Fetcher
{
    /** @var array<string, array{agent: list<array{0: bool, 1: string}>}|null> host => rules */
    protected array $robots = [];

    protected ?float $lastRequest = null;

    public int $requests = 0;

    public function userAgent(): string
    {
        return (string) config('investment_index.collection.user_agent');
    }

    /** @throws CollectionError */
    public function get(string $url): string
    {
        if (! preg_match('#^https?://#i', $url)) {
            throw new CollectionError('network', "Not a web address: {$url}");
        }
        if (! $this->allowed($url)) {
            throw new CollectionError('robots', "robots.txt does not allow automated reading of {$url}.");
        }

        $response = $this->request($url);
        if ($response->failed()) {
            throw new CollectionError('http', "HTTP {$response->status()} from {$url}.");
        }
        $body = $response->body();
        if (strlen($body) > (int) config('investment_index.collection.max_bytes')) {
            throw new CollectionError('size', "The response from {$url} is larger than the limit.");
        }

        return $body;
    }

    public function allowed(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower(($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : ''));
        if (! array_key_exists($host, $this->robots)) {
            $this->robots[$host] = $this->loadRobots($host);
        }
        $rules = $this->robots[$host];
        if ($rules === null) {
            return false; // robots.txt unreadable (server error or network): do not crawl
        }
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        // The longest matching rule wins; Allow wins a tie. No rule: allowed.
        $best = null;
        foreach ($rules as [$allow, $pattern]) {
            if ($pattern === '') {
                continue;
            }
            $regex = '#^'.str_replace(['\*', '\$'], ['.*', '$'], preg_quote($pattern, '#')).'#';
            if (preg_match($regex, $path) && ($best === null || strlen($pattern) > strlen($best[1]) || (strlen($pattern) === strlen($best[1]) && $allow))) {
                $best = [$allow, $pattern];
            }
        }

        return $best === null || $best[0];
    }

    /** @return list<array{0: bool, 1: string}>|null the rules for this bot, [] when none, null when unreadable */
    protected function loadRobots(string $host): ?array
    {
        try {
            $response = $this->request($host.'/robots.txt');
        } catch (CollectionError) {
            return null;
        }
        if ($response->status() >= 400 && $response->status() < 500) {
            return [];
        }
        if (! $response->successful()) {
            return null;
        }

        $token = strtolower(strtok($this->userAgent(), '/'));
        $groups = [];
        $agents = [];
        $readingAgents = false;
        foreach (preg_split('/\R/', $response->body()) as $line) {
            $line = trim(preg_replace('/#.*/', '', $line));
            if (! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);
            if ($field === 'user-agent') {
                $agents = $readingAgents ? [...$agents, strtolower($value)] : [strtolower($value)];
                $readingAgents = true;
                foreach ($agents as $a) {
                    $groups[$a] ??= [];
                }

                continue;
            }
            $readingAgents = false;
            if (in_array($field, ['allow', 'disallow'], true)) {
                foreach ($agents as $a) {
                    $groups[$a][] = [$field === 'allow', $value];
                }
            }
        }

        return $groups[$token] ?? $groups['*'] ?? [];
    }

    /** @throws CollectionError */
    protected function request(string $url): Response
    {
        $delay = (int) config('investment_index.collection.delay_ms');
        if ($this->lastRequest !== null && $delay > 0) {
            $wait = $delay - (int) ((microtime(true) - $this->lastRequest) * 1000);
            $wait > 0 && usleep($wait * 1000);
        }
        $this->lastRequest = microtime(true);
        $this->requests++;

        try {
            return Http::withHeaders(['User-Agent' => $this->userAgent(), 'Accept' => 'text/html,application/xhtml+xml,application/json;q=0.9'])
                ->timeout((int) config('investment_index.collection.timeout'))
                ->retry(2, 2000, fn ($e) => $e instanceof ConnectionException, throw: false)
                ->get($url);
        } catch (Throwable $e) {
            throw new CollectionError('network', 'Could not reach '.$url.': '.mb_substr($e->getMessage(), 0, 200));
        }
    }
}
