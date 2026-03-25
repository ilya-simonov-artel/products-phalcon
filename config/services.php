<?php

declare(strict_types=1);

use App\Services\AuthService;
use App\Services\CategoryService;
use App\Services\JwtService;
use App\Services\ProductService;
use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt;
use Phalcon\Url\Url;

$container = new FactoryDefault();
$config = require BASE_PATH . '/config/config.php';

$container->setShared('config', fn() => $config);

$container->setShared('db', function () use ($config) {
    return new Mysql([
        'host' => $config->path('database.host'),
        'port' => $config->path('database.port'),
        'dbname' => $config->path('database.dbname'),
        'username' => $config->path('database.username'),
        'password' => $config->path('database.password'),
        'charset' => $config->path('database.charset'),
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ]);
});


$container->setShared('dispatcher', function () {
    $dispatcher = new Dispatcher();
    $dispatcher->setDefaultNamespace('App\\Controllers');

    return $dispatcher;
});

$container->setShared('view', function () use ($config) {
    $view = new View();
    $view->setViewsDir($config->path('app.viewsDir'));
    $view->registerEngines([
        '.volt' => function (View $view) use ($config) {
            $cacheDir = (string) $config->path('app.cacheDir');
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0777, true);
            }

            $volt = new Volt($view);
            $volt->setOptions([
                'path' => $cacheDir,
                'separator' => '_',
                'always' => true,
            ]);

            return $volt;
        },
    ]);

    return $view;
});

$container->setShared('url', function () use ($config) {
    $url = new Url();
    $url->setBaseUri('/');

    return $url;
});

$container->setShared('jwtService', fn() => new JwtService(
    (string) $config->path('auth.jwtSecret'),
    (string) $config->path('auth.jwtIssuer'),
    (int) $config->path('auth.jwtTtlHours'),
));
$container->setShared('authService', fn() => new AuthService($container->get('db'), $container->get('jwtService')));
$container->setShared('categoryService', fn() => new CategoryService($container->get('db')));
$container->setShared('productService', fn() => new ProductService($container->get('db'), $container->get('categoryService')));

return $container;
