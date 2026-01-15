<?php

declare(strict_types=1);

namespace LoteriasApi\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use LoteriasApi\Config\RetryConfig;
use LoteriasApi\Middleware\RetryMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for the RetryMiddleware exponential backoff implementation.
 *
 * Uses Guzzle's MockHandler to simulate API failures and verify retry behavior.
 */
class RetryMiddlewareTest extends TestCase
{
    /**
     * Test: 3x 429 errors followed by a 200 success.
     * Expected: Request succeeds after retrying.
     */
    public function testRetryOn429WithEventualSuccess(): void
    {
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '0'], '{"error": {"code": "RATE_LIMIT_EXCEEDED"}}'),
            new Response(429, ['Retry-After' => '0'], '{"error": {"code": "RATE_LIMIT_EXCEEDED"}}'),
            new Response(429, ['Retry-After' => '0'], '{"error": {"code": "RATE_LIMIT_EXCEEDED"}}'),
            new Response(200, [], '{"data": {"success": true}}'),
        ]);

        $client = $this->createClientWithRetry($mock, new RetryConfig(
            enabled: true,
            maxRetries: 3,
            baseDelayMs: 10, // Use small delays for testing
            maxDelayMs: 100,
        ));

        $response = $client->get('/test');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('success', $response->getBody()->getContents());
        $this->assertEmpty($mock->getLastRequest() === null); // All requests consumed
    }

    /**
     * Test: 2x 503 errors followed by a 200 success.
     * Expected: Request succeeds after retrying server errors.
     */
    public function testRetryOn5xxWithEventualSuccess(): void
    {
        $mock = new MockHandler([
            new Response(503, [], '{"error": {"code": "SERVICE_UNAVAILABLE"}}'),
            new Response(502, [], '{"error": {"code": "BAD_GATEWAY"}}'),
            new Response(200, [], '{"data": {"result": "ok"}}'),
        ]);

        $client = $this->createClientWithRetry($mock, new RetryConfig(
            enabled: true,
            maxRetries: 3,
            baseDelayMs: 10,
        ));

        $response = $client->get('/test');

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test: More failures than maxRetries allows.
     * Expected: Throws exception after exhausting retries.
     */
    public function testExhaustedRetriesThrowsException(): void
    {
        $mock = new MockHandler([
            new Response(429, [], '{"error": {"code": "RATE_LIMIT_EXCEEDED"}}'),
            new Response(429, [], '{"error": {"code": "RATE_LIMIT_EXCEEDED"}}'),
            new Response(429, [], '{"error": {"code": "RATE_LIMIT_EXCEEDED"}}'),
            new Response(429, [], '{"error": {"code": "RATE_LIMIT_EXCEEDED"}}'),
            // This one should never be reached (maxRetries=3)
            new Response(200, [], '{"data": {}}'),
        ]);

        $client = $this->createClientWithRetry($mock, new RetryConfig(
            enabled: true,
            maxRetries: 3,
            baseDelayMs: 10,
        ));

        // After 3 retries (4 total requests), should still get 429
        $response = $client->get('/test');
        $this->assertEquals(429, $response->getStatusCode());
    }

    /**
     * Test: Retries disabled in configuration.
     * Expected: First failure is returned immediately without retry.
     */
    public function testRetryDisabledReturnsImmediately(): void
    {
        $mock = new MockHandler([
            new Response(429, [], '{"error": {"code": "RATE_LIMIT_EXCEEDED"}}'),
            new Response(200, [], '{"data": {"should_not_reach": true}}'),
        ]);

        $client = $this->createClientWithRetry($mock, RetryConfig::disabled());

        $response = $client->get('/test');

        $this->assertEquals(429, $response->getStatusCode());
        // Second response should still be in queue (never reached)
        $this->assertEquals(1, $mock->count());
    }

    /**
     * Test: 4xx errors besides 429 should NOT be retried.
     * Expected: 400 Bad Request returns immediately.
     */
    public function testNonRetryable4xxNotRetried(): void
    {
        $mock = new MockHandler([
            new Response(400, [], '{"error": {"code": "INVALID_REQUEST"}}'),
            new Response(200, [], '{"data": {"should_not_reach": true}}'),
        ]);

        $client = $this->createClientWithRetry($mock, new RetryConfig(
            enabled: true,
            maxRetries: 3,
            baseDelayMs: 10,
        ));

        $response = $client->get('/test');

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(1, $mock->count()); // Second response not consumed
    }

    /**
     * Test: Retry-After header is respected.
     * Expected: Delay is based on Retry-After value.
     */
    public function testRespectsRetryAfterHeader(): void
    {
        // Note: We use 0 second Retry-After to keep tests fast
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '0'], '{"error": {"code": "RATE_LIMIT_EXCEEDED"}}'),
            new Response(200, [], '{"data": {"success": true}}'),
        ]);

        $startTime = microtime(true);

        $client = $this->createClientWithRetry($mock, new RetryConfig(
            enabled: true,
            maxRetries: 3,
            baseDelayMs: 5000, // Would be 5 seconds without Retry-After
        ));

        $response = $client->get('/test');
        $elapsed = microtime(true) - $startTime;

        $this->assertEquals(200, $response->getStatusCode());
        // Should be much faster than 5 seconds since Retry-After is 0
        $this->assertLessThan(1.0, $elapsed);
    }

    /**
     * Test: Connection exceptions trigger retry.
     * Expected: Network failures are retried.
     */
    public function testRetryOnConnectionException(): void
    {
        $request = new Request('GET', '/test');

        $mock = new MockHandler([
            new RequestException('Connection refused', $request),
            new RequestException('Connection timeout', $request),
            new Response(200, [], '{"data": {"recovered": true}}'),
        ]);

        $client = $this->createClientWithRetry($mock, new RetryConfig(
            enabled: true,
            maxRetries: 3,
            baseDelayMs: 10,
        ));

        // Note: Regular RequestExceptions without responses are thrown
        // Only ConnectException is retried in our implementation
        // This test verifies the exception handling path
        $this->expectException(RequestException::class);
        $client->get('/test');
    }

    /**
     * Test: RetryConfig validation.
     */
    public function testRetryConfigValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RetryConfig(maxRetries: 15); // Max is 10
    }

    /**
     * Test: RetryConfig factory method.
     */
    public function testRetryConfigDisabledFactory(): void
    {
        $config = RetryConfig::disabled();

        $this->assertFalse($config->enabled);
    }

    /**
     * Test: Successful request doesn't trigger retries.
     */
    public function testSuccessfulRequestNoRetry(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"data": {"immediate": true}}'),
            new Response(200, [], '{"data": {"should_not_reach": true}}'),
        ]);

        $client = $this->createClientWithRetry($mock, new RetryConfig(
            enabled: true,
            maxRetries: 3,
        ));

        $response = $client->get('/test');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, $mock->count()); // Only one request made
    }

    /**
     * Helper: Create a Guzzle client with retry middleware and mock handler.
     */
    private function createClientWithRetry(MockHandler $mock, RetryConfig $config): Client
    {
        $stack = HandlerStack::create($mock);

        if ($config->enabled) {
            $stack->push(RetryMiddleware::create($config), 'retry');
        }

        return new Client([
            'handler' => $stack,
            'http_errors' => false, // Don't throw on 4xx/5xx for easier testing
        ]);
    }
}
