<?php

declare(strict_types=1);

namespace LoteriasApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use LoteriasApi\Config\RetryConfig;
use LoteriasApi\Exceptions\LoteriasApiException;
use LoteriasApi\Middleware\RetryMiddleware;

/**
 * Internal HTTP client for making API requests.
 *
 * Supports automatic retries with exponential backoff for transient failures.
 */
class HttpClient
{
    private Client $client;
    private string $baseUrl;
    private string $apiKey;

    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.loterias-api.com/api/v1',
        int $timeout = 30,
        ?RetryConfig $retryConfig = null
    ) {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');

        // Use default retry config if not provided
        $retryConfig ??= new RetryConfig();

        // Build handler stack with retry middleware
        $stack = HandlerStack::create();
        if ($retryConfig->enabled) {
            $stack->push(RetryMiddleware::create($retryConfig), 'retry');
        }

        $this->client = new Client([
            'handler' => $stack,
            'base_uri' => $this->baseUrl,
            'timeout' => $timeout,
            'headers' => [
                'X-API-Key' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Make a GET request to the API
     *
     * @param string $path The API endpoint path
     * @param array<string, mixed> $params Query parameters
     * @return array<string, mixed> The response data
     * @throws LoteriasApiException
     */
    public function get(string $path, array $params = []): array
    {
        return $this->request('GET', $path, $params);
    }

    /**
     * Make an HTTP request to the API
     *
     * @param string $method HTTP method
     * @param string $path API endpoint path
     * @param array<string, mixed> $params Request parameters
     * @return array<string, mixed> The response data
     * @throws LoteriasApiException
     */
    private function request(string $method, string $path, array $params = []): array
    {
        // Clean up params - remove null values and convert arrays
        $cleanParams = [];
        foreach ($params as $key => $value) {
            if ($value !== null) {
                if (is_array($value)) {
                    $cleanParams[$key] = implode(',', $value);
                } else {
                    $cleanParams[$key] = $value;
                }
            }
        }

        try {
            $options = [];
            if ($method === 'GET' && !empty($cleanParams)) {
                $options['query'] = $cleanParams;
            } elseif (!empty($cleanParams)) {
                $options['json'] = $cleanParams;
            }

            $response = $this->client->request($method, $path, $options);
            $data = json_decode($response->getBody()->getContents(), true);

            return $data;
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $data = json_decode($response->getBody()->getContents(), true);
                $error = $data['error'] ?? [];

                throw new LoteriasApiException(
                    message: $error['message'] ?? 'Unknown error',
                    errorCode: $error['code'] ?? 'UNKNOWN_ERROR',
                    statusCode: $error['statusCode'] ?? $response->getStatusCode(),
                    details: $error['details'] ?? null,
                );
            }

            throw new LoteriasApiException(
                message: 'Network error',
                errorCode: 'NETWORK_ERROR',
                statusCode: 0,
                details: ['original_error' => $e->getMessage()],
            );
        } catch (GuzzleException $e) {
            throw new LoteriasApiException(
                message: 'Network error',
                errorCode: 'NETWORK_ERROR',
                statusCode: 0,
                details: ['original_error' => $e->getMessage()],
            );
        }
    }
}
