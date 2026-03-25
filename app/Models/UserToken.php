<?php

declare(strict_types=1);

namespace App\Models;

use Phalcon\Mvc\Model;

final class UserToken extends Model
{
    public int $id;

    public int $user_id;

    public string $token_hash;

    public string $expires_at;

    public ?string $revoked_at = null;

    public string $created_at;

    public function initialize(): void
    {
        $this->setSource('user_tokens');

        $this->belongsTo('user_id', User::class, 'id', [
            'alias' => 'user',
            'reusable' => true,
        ]);
    }
}
