<?php

declare(strict_types=1);

namespace LoteriasApi\Exceptions;

use Exception;

/**
 * Exception thrown by the Loteria API SDK
 */
class LoteriasApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $statusCode,
        public readonly ?array $details = null,
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * Get the API error code
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get the HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get additional error details
     */
    public function getDetails(): ?array
    {
        return $this->details;
    }
}
