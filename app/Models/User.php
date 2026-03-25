<?php

declare(strict_types=1);

namespace App\Models;

use Phalcon\Mvc\Model;

final class User extends Model
{
    public int $id;

    public string $username;

    public string $password_hash;

    public string $display_name;

    public string $created_at;

    public string $updated_at;

    public function initialize(): void
    {
        $this->setSource('users');

        $this->hasMany('id', UserToken::class, 'user_id', [
            'alias' => 'tokens',
        ]);
    }
}
