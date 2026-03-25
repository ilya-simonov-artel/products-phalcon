<?php

declare(strict_types=1);

namespace App\Services;

use App\Library\HttpException;
use Phalcon\Db\Adapter\AdapterInterface;

final class AuthService
{
    public function __construct(
        private readonly AdapterInterface $db,
        private readonly JwtService $jwtService,
    )
    {
    }

    /**
     * @return array{token: string, user: array{id:int,username:string,display_name:string}}
     */
    public function login(string $username, string $password): array
    {
        $user = $this->db->fetchOne(
            'SELECT id, username, password_hash, display_name FROM users WHERE username = :username LIMIT 1',
            2,
            ['username' => $username],
        );

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            throw new HttpException('Неверно введён логин или пароль.', 401, [
                'username' => 'Проверьте правильность логина.',
                'password' => 'Проверьте правильность пароля.',
            ]);
        }

        $publicUser = [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'display_name' => (string) $user['display_name'],
        ];

        $token = $this->jwtService->issue($publicUser);
        $tokenHash = hash('sha256', $token);

        $claims = $this->jwtService->decodeAndValidate($token);
        $expiresAt = (new \DateTimeImmutable())->setTimestamp($claims['exp'])->format('Y-m-d H:i:s');

        $this->db->execute(
            'INSERT INTO user_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)',
            [
                'user_id' => (int) $claims['sub'],
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
            ],
        );

        return [
            'token' => $token,
            'user' => $publicUser,
        ];
    }

    /**
     * @return array{id:int,username:string,display_name:string}
     */
    public function authenticate(string $token): array
    {
        $claims = $this->jwtService->decodeAndValidate($token);
        $tokenHash = hash('sha256', $token);

        $user = $this->db->fetchOne(
            'SELECT u.id, u.username, u.display_name
             FROM user_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = :token_hash
               AND t.user_id = :user_id
               AND t.revoked_at IS NULL
               AND t.expires_at > NOW()
             LIMIT 1',
            2,
            [
                'token_hash' => $tokenHash,
                'user_id' => (int) $claims['sub'],
            ],
        );

        if (!$user) {
            throw new HttpException('Требуется авторизация.', 401);
        }

        return [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'display_name' => (string) $user['display_name'],
        ];
    }

    public function revoke(string $token): void
    {
        $tokenHash = hash('sha256', $token);

        $this->db->execute(
            'UPDATE user_tokens SET revoked_at = NOW() WHERE token_hash = :token_hash AND revoked_at IS NULL',
            ['token_hash' => $tokenHash],
        );
    }
}
