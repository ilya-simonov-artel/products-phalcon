<?php

declare(strict_types=1);

namespace App\Library;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(string $message, private readonly int $statusCode = 400, private readonly array $details = [])
    {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
