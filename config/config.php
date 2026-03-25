<?php

declare(strict_types=1);

use Phalcon\Config\Config;

return new Config([
    'app' => [
        'name' => getenv('APP_NAME') ?: 'Product Catalog',
        'env' => getenv('APP_ENV') ?: 'production',
        'debug' => (bool) (getenv('APP_DEBUG') ?: false),
        'url' => getenv('APP_URL') ?: 'http://localhost:8080',
        'viewsDir' => BASE_PATH . '/app/Views/',
        'cacheDir' => getenv('APP_CACHE_DIR') ?: '/tmp/phalcon-cache/',
    ],
    'database' => [
        'host' => getenv('DB_HOST') ?: 'db',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'dbname' => getenv('DB_NAME') ?: 'phalcon_catalog',
        'username' => getenv('DB_USER') ?: 'phalcon',
        'password' => getenv('DB_PASSWORD') ?: 'phalcon',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
    'auth' => [
        'jwtSecret' => getenv('JWT_SECRET') ?: 'dev-jwt-secret-change-me',
        'jwtIssuer' => getenv('JWT_ISSUER') ?: (getenv('APP_URL') ?: 'http://localhost:8080'),
        'jwtTtlHours' => (int) (getenv('JWT_TTL_HOURS') ?: 12),
    ],
]);
