<?php

namespace App\Services\Agents\Providers;

use App\Services\Agents\OutputGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

/** What both HTTP adapters share: result shapes, retry rules and safe error text. */
abstract class HttpAdapter implements ProviderAdapter
{
    protected const NO_CREDIT = 'No API credit: this account has no available API quota. Add billing credit in the provider console. A ChatGPT or Claude subscription does not include API credit.';

    protected function failed(string $model, string $failure, string $error, int $in = 0, int $out = 0): array
    {
        return ['ok' => false, 'text' => null, 'model' => $model, 'tokens_in' => $in, 'tokens_out' => $out, 'error' => $error, 'failure' => $failure];
    }

    protected function succeeded(string $model, string $text, int $in, int $out): array
    {
        return ['ok' => true, 'text' => trim($text), 'model' => $model, 'tokens_in' => $in, 'tokens_out' => $out, 'error' => null, 'failure' => null];
    }

    /** Retry rate limits, server errors and dropped connections; never bad requests, bad keys or empty credit. */
    protected function retryable(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }
        if (! $e instanceof RequestException) {
            return false;
        }
        $status = $e->response->status();

        return ($status === 429 && ! $this->isCreditError($e->response)) || $status >= 500;
    }

    abstract protected function isCreditError(Response $response): bool;

    protected function attempts(ProviderConfig $config, ?int $retries): int
    {
        return max(1, ($retries ?? $config->retries) + 1);
    }

    /** The provider's own message, only for errors that cannot echo a credential. */
    protected function detail(Response $response): string
    {
        $message = $response->json('error.message');

        return is_string($message) ? ' '.OutputGuard::text(mb_substr($message, 0, 200)) : '';
    }

    protected function listFailure(int $status): string
    {
        return in_array($status, [401, 403], true)
            ? "Provider answered HTTP {$status}: it rejected this API key. Check the key and its permissions."
            : "Provider answered HTTP {$status}.";
    }

    protected function connectionFailure(string $model, Throwable $e): array
    {
        return $this->failed($model, 'temporary', 'Provider unreachable: '.class_basename($e));
    }
}
