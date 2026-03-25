<?php

declare(strict_types=1);

namespace App\Library;

final class ApiResponse
{
    public static function success(array $data = [], int $status = 200): array
    {
        return [
            'success' => true,
            'status' => $status,
            'data' => $data,
        ];
    }

    public static function error(string $message, int $status, array $details = []): array
    {
        return [
            'success' => false,
            'status' => $status,
            'error' => [
                'message' => $message,
                'details' => $details,
            ],
        ];
    }
}
