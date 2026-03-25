<?php

declare(strict_types=1);

namespace App\Services;

use App\Library\HttpException;

final class JwtService
{
    private const ALGO = 'HS256';

    public function __construct(
        private readonly string $secret,
        private readonly string $issuer,
        private readonly int $ttlHours,
    ) {
        if (trim($this->secret) === '') {
            throw new \InvalidArgumentException('JWT secret must not be empty.');
        }
    }

    /**
     * @param array{id:int,username:string,display_name:string} $user
     */
    public function issue(array $user): string
    {
        $now = time();
        $expiresAt = $now + ($this->ttlHours * 3600);

        $header = [
            'alg' => self::ALGO,
            'typ' => 'JWT',
        ];

        $payload = [
            'iss' => $this->issuer,
            'sub' => (string) $user['id'],
            'iat' => $now,
            'exp' => $expiresAt,
            'jti' => bin2hex(random_bytes(16)),
            'username' => $user['username'],
            'display_name' => $user['display_name'],
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $signature = $this->createSignature($encodedHeader . '.' . $encodedPayload);

        return $encodedHeader . '.' . $encodedPayload . '.' . $signature;
    }

    /**
     * @return array{sub:string,exp:int,jti:string,username:string,display_name:string}
     */
    public function decodeAndValidate(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new HttpException('Некорректный JWT токен.', 401);
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $headerJson = $this->base64UrlDecode($encodedHeader);
        $payloadJson = $this->base64UrlDecode($encodedPayload);

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!is_array($header) || !is_array($payload)) {
            throw new HttpException('Некорректный JWT токен.', 401);
        }

        if (($header['alg'] ?? null) !== self::ALGO || ($header['typ'] ?? null) !== 'JWT') {
            throw new HttpException('Неподдерживаемый JWT токен.', 401);
        }

        $expectedSignature = $this->createSignature($encodedHeader . '.' . $encodedPayload);
        if (!hash_equals($expectedSignature, $encodedSignature)) {
            throw new HttpException('JWT токен имеет неверную подпись.', 401);
        }

        $issuer = $payload['iss'] ?? null;
        $sub = $payload['sub'] ?? null;
        $exp = $payload['exp'] ?? null;
        $jti = $payload['jti'] ?? null;
        $username = $payload['username'] ?? null;
        $displayName = $payload['display_name'] ?? null;

        if (
            !is_string($issuer) || $issuer !== $this->issuer
            || !is_string($sub) || $sub === ''
            || !is_int($exp)
            || !is_string($jti) || $jti === ''
            || !is_string($username) || $username === ''
            || !is_string($displayName) || $displayName === ''
        ) {
            throw new HttpException('JWT токен содержит некорректные данные.', 401);
        }

        if ($exp <= time()) {
            throw new HttpException('JWT токен истёк.', 401);
        }

        return [
            'sub' => $sub,
            'exp' => $exp,
            'jti' => $jti,
            'username' => $username,
            'display_name' => $displayName,
        ];
    }

    private function createSignature(string $data): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, true));
    }

    private function base64UrlEncode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $input): string
    {
        $padding = strlen($input) % 4;
        if ($padding > 0) {
            $input .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($input, '-_', '+/'), true);
        if ($decoded === false) {
            throw new HttpException('Некорректный JWT токен.', 401);
        }

        return $decoded;
    }
}
