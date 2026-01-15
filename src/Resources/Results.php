<?php

declare(strict_types=1);

namespace LoteriasApi\Resources;

use LoteriasApi\HttpClient;
use LoteriasApi\Exceptions\LoteriasApiException;

/**
 * Results API - Query lottery results
 */
class Results
{
    public function __construct(
        private readonly HttpClient $client
    ) {}

    /**
     * Get all results with optional filtering and pagination
     *
     * @param array<string, mixed> $params Filter parameters
     * @return array<string, mixed>
     * @throws LoteriasApiException
     */
    public function list(array $params = []): array
    {
        return $this->client->get('/results', $params);
    }

    /**
     * Get the latest result for each game type
     *
     * @return array<string, mixed>
     * @throws LoteriasApiException
     */
    public function getLatestAll(): array
    {
        return $this->client->get('/results/latest');
    }

    /**
     * Get results for a specific game type
     *
     * @param string $gameType The game type slug
     * @param array<string, mixed> $params Filter parameters
     * @return array<string, mixed>
     * @throws LoteriasApiException
     */
    public function listByGame(string $gameType, array $params = []): array
    {
        return $this->client->get("/results/{$gameType}", $params);
    }

    /**
     * Get the latest result for a specific game type
     *
     * @param string $gameType The game type slug
     * @return array<string, mixed>
     * @throws LoteriasApiException
     */
    public function getLatest(string $gameType): array
    {
        return $this->client->get("/results/{$gameType}/latest");
    }

    /**
     * Get results for a specific date
     *
     * @param string $gameType The game type slug
     * @param string $date Date in YYYY-MM-DD format
     * @return array<string, mixed>
     * @throws LoteriasApiException
     */
    public function getByDate(string $gameType, string $date): array
    {
        return $this->client->get("/results/{$gameType}/date/{$date}");
    }

    /**
     * Get results within a date range
     *
     * @param string $gameType The game type slug
     * @param string $from Start date in YYYY-MM-DD format
     * @param string $to End date in YYYY-MM-DD format
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array<string, mixed>
     * @throws LoteriasApiException
     */
    public function getByDateRange(
        string $gameType,
        string $from,
        string $to,
        int $page = 1,
        int $limit = 10
    ): array {
        return $this->client->get("/results/{$gameType}/range", [
            'from' => $from,
            'to' => $to,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * Get a specific draw result by ID
     *
     * @param string $gameType The game type slug
     * @param string $drawId The draw ID
     * @return array<string, mixed>
     * @throws LoteriasApiException
     */
    public function getByDrawId(string $gameType, string $drawId): array
    {
        return $this->client->get("/results/{$gameType}/{$drawId}");
    }

    /**
     * Check your numbers against a draw
     *
     * @param string $gameType The game type slug
     * @param int[] $numbers Main numbers to check
     * @param int[]|null $extraNumbers Extra numbers (stars for Euromillones, etc.)
     * @param string|null $drawId Specific draw ID (defaults to latest)
     * @return array<string, mixed>
     * @throws LoteriasApiException
     */
    public function checkNumbers(
        string $gameType,
        array $numbers,
        ?array $extraNumbers = null,
        ?string $drawId = null
    ): array {
        return $this->client->get("/results/{$gameType}/check", [
            'numbers' => $numbers,
            'extraNumbers' => $extraNumbers,
            'drawId' => $drawId,
        ]);
    }
}
