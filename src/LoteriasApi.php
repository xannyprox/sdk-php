<?php

declare(strict_types=1);

namespace LoteriasApi;

use LoteriasApi\Resources\Results;
use LoteriasApi\Resources\Draws;
use InvalidArgumentException;

/**
 * Loterias API Client
 *
 * Official PHP SDK for Loterias API
 *
 * Example usage:
 * ```php
 * use LoteriasApi\LoteriasApi;
 *
 * $api = new LoteriasApi('lat_xxx...');
 *
 * // Get latest Euromillones result
 * $result = $api->results->getLatest('euromillones');
 * print_r($result['data']['combination']);
 *
 * // Check your numbers
 * $check = $api->results->checkNumbers('euromillones',
 *     numbers: [4, 15, 23, 38, 42],
 *     extraNumbers: [3, 11]
 * );
 * echo $check['data']['isWinner'] ? 'Winner!' : 'No luck';
 * ```
 */
class LoteriasApi
{
    /** Results API - Query lottery results */
    public readonly Results $results;

    /** Draws API - Query upcoming draws */
    public readonly Draws $draws;

    /**
     * Create a new Loterias API client
     *
     * @param string $apiKey Your API key (get one at https://loterias-api.com)
     * @param string $baseUrl Base URL for the API
     * @param int $timeout Request timeout in seconds
     * @throws InvalidArgumentException if apiKey is empty
     */
    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.loterias-api.com/api/v1',
        int $timeout = 30
    ) {
        if (empty($apiKey)) {
            throw new InvalidArgumentException('apiKey is required');
        }

        $client = new HttpClient($apiKey, $baseUrl, $timeout);

        $this->results = new Results($client);
        $this->draws = new Draws($client);
    }
}
