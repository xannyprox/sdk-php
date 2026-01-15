<?php

declare(strict_types=1);

namespace LoteriasApi\Config;

/**
 * Immutable configuration for the retry mechanism.
 *
 * Example usage:
 * ```php
 * use LoteriasApi\Config\RetryConfig;
 *
 * // Use defaults (enabled with 3 retries)
 * $config = new RetryConfig();
 *
 * // Disable retries
 * $config = new RetryConfig(enabled: false);
 *
 * // Custom configuration
 * $config = new RetryConfig(
 *     enabled: true,
 *     maxRetries: 5,
 *     baseDelayMs: 500,
 *     maxDelayMs: 60000
 * );
 * ```
 */
final readonly class RetryConfig
{
    /**
     * @param bool $enabled Whether automatic retries are enabled
     * @param int $maxRetries Maximum number of retry attempts (1-10)
     * @param int $baseDelayMs Base delay in milliseconds for exponential backoff
     * @param int $maxDelayMs Maximum delay cap in milliseconds
     */
    public function __construct(
        public bool $enabled = true,
        public int $maxRetries = 3,
        public int $baseDelayMs = 1000,
        public int $maxDelayMs = 32000,
    ) {
        if ($maxRetries < 0 || $maxRetries > 10) {
            throw new \InvalidArgumentException('maxRetries must be between 0 and 10');
        }
        if ($baseDelayMs < 0) {
            throw new \InvalidArgumentException('baseDelayMs must be non-negative');
        }
        if ($maxDelayMs < $baseDelayMs) {
            throw new \InvalidArgumentException('maxDelayMs must be >= baseDelayMs');
        }
    }

    /**
     * Create a disabled retry configuration
     */
    public static function disabled(): self
    {
        return new self(enabled: false);
    }
}
