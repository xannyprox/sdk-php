<?php

declare(strict_types=1);

namespace LoteriasApi\Resources;

use LoteriasApi\HttpClient;
use LoteriasApi\Exceptions\LoteriasApiException;

/**
 * Draws API - Query upcoming draws
 */
class Draws
{
    public function __construct(
        private readonly HttpClient $client
    ) {}

    /**
     * Get all upcoming draws
     *
     * @param array<string, mixed> $params Filter parameters
     * @return array<string, mixed>
     * @throws LoteriasApiException
     */
    public function listUpcoming(array $params = []): array
    {
        return $this->client->get('/draws/upcoming', $params);
    }

    /**
     * Get the next draw for each game type
     *
     * @return array<string, mixed>
     * @throws LoteriasApiException
     */
    public function getNextAll(): array
    {
        return $this->client->get('/draws/upcoming/next');
    }

    /**
     * Get upcoming draws for a specific game type
     *
     * @param string $gameType The game type slug
     * @param array<string, mixed> $params Filter parameters
     * @return array<string, mixed>
     * @throws LoteriasApiException
     */
    public function listUpcomingByGame(string $gameType, array $params = []): array
    {
        return $this->client->get("/draws/upcoming/{$gameType}", $params);
    }

    /**
     * Get a specific draw by ID
     *
     * @param string $drawId The draw ID
     * @return array<string, mixed>
     * @throws LoteriasApiException
     */
    public function getById(string $drawId): array
    {
        return $this->client->get("/draws/{$drawId}");
    }
}
