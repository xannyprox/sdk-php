<?php

declare(strict_types=1);

namespace LoteriasApi\Middleware;

use Closure;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use LoteriasApi\Config\RetryConfig;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Guzzle middleware that implements automatic retries with exponential backoff.
 *
 * Retries requests that fail with:
 * - 429 (Too Many Requests / Rate Limit)
 * - 5xx (Server Errors)
 * - Connection failures
 *
 * Uses exponential backoff: delay = min(baseDelay * 2^attempt, maxDelay)
 * Respects the Retry-After header when present.
 */
final readonly class RetryMiddleware
{
    private const RETRYABLE_STATUS_CODES = [429, 500, 502, 503, 504];

    public function __construct(
        private RetryConfig $config,
    ) {}

    /**
     * Create a Guzzle middleware handler.
     *
     * @return Closure(callable): callable
     */
    public static function create(RetryConfig $config): Closure
    {
        $middleware = new self($config);
        return $middleware->handler();
    }

    /**
     * Returns the middleware handler function.
     *
     * @return Closure(callable): callable
     */
    public function handler(): Closure
    {
        return function (callable $handler): callable {
            return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
                return $this->executeWithRetry($handler, $request, $options, 0);
            };
        };
    }

    /**
     * Execute request with retry logic.
     */
    private function executeWithRetry(
        callable $handler,
        RequestInterface $request,
        array $options,
        int $attempt
    ): PromiseInterface {
        /** @var PromiseInterface $promise */
        $promise = $handler($request, $options);

        return $promise->then(
            function (ResponseInterface $response) use ($handler, $request, $options, $attempt) {
                if ($this->shouldRetry($response, $attempt)) {
                    return $this->retryRequest($handler, $request, $options, $attempt, $response);
                }
                return $response;
            },
            function ($reason) use ($handler, $request, $options, $attempt) {
                if ($this->shouldRetryException($reason, $attempt)) {
                    $response = $reason instanceof RequestException ? $reason->getResponse() : null;
                    return $this->retryRequest($handler, $request, $options, $attempt, $response);
                }
                throw $reason;
            }
        );
    }

    /**
     * Determine if a response warrants a retry.
     */
    private function shouldRetry(ResponseInterface $response, int $attempt): bool
    {
        if ($attempt >= $this->config->maxRetries) {
            return false;
        }

        return in_array($response->getStatusCode(), self::RETRYABLE_STATUS_CODES, true);
    }

    /**
     * Determine if an exception warrants a retry.
     */
    private function shouldRetryException(mixed $reason, int $attempt): bool
    {
        if ($attempt >= $this->config->maxRetries) {
            return false;
        }

        // Retry on connection failures
        if ($reason instanceof ConnectException) {
            return true;
        }

        // Retry on retryable HTTP status codes
        if ($reason instanceof RequestException && $reason->hasResponse()) {
            /** @var ResponseInterface $response */
            $response = $reason->getResponse();
            return in_array($response->getStatusCode(), self::RETRYABLE_STATUS_CODES, true);
        }

        return false;
    }

    /**
     * Execute a retry after the calculated delay.
     */
    private function retryRequest(
        callable $handler,
        RequestInterface $request,
        array $options,
        int $attempt,
        ?ResponseInterface $response
    ): PromiseInterface {
        $delayMs = $this->calculateDelay($attempt, $response);

        // Sleep for the calculated delay (convert to microseconds)
        usleep($delayMs * 1000);

        return $this->executeWithRetry($handler, $request, $options, $attempt + 1);
    }

    /**
     * Calculate the delay before the next retry attempt.
     *
     * Uses exponential backoff with jitter, respecting Retry-After header if present.
     */
    private function calculateDelay(int $attempt, ?ResponseInterface $response): int
    {
        // Check for Retry-After header (common in 429 responses)
        if ($response !== null && $response->hasHeader('Retry-After')) {
            $retryAfter = $response->getHeader('Retry-After')[0];

            // Retry-After can be seconds or an HTTP-date
            if (is_numeric($retryAfter)) {
                $delayMs = (int) $retryAfter * 1000;
                return min($delayMs, $this->config->maxDelayMs);
            }

            // Parse HTTP-date format
            $timestamp = strtotime($retryAfter);
            if ($timestamp !== false) {
                $delaySeconds = max(0, $timestamp - time());
                return min($delaySeconds * 1000, $this->config->maxDelayMs);
            }
        }

        // Exponential backoff: baseDelay * 2^attempt
        $exponentialDelay = $this->config->baseDelayMs * (2 ** $attempt);

        // Add jitter (±10%) to prevent thundering herd
        $jitter = (int) ($exponentialDelay * 0.1 * (mt_rand() / mt_getrandmax() * 2 - 1));
        $delayWithJitter = $exponentialDelay + $jitter;

        return min(max($delayWithJitter, 0), $this->config->maxDelayMs);
    }
}
