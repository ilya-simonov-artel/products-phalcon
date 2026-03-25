<?php

declare(strict_types=1);

use App\Library\ApiResponse;
use App\Library\HttpException;
use Phalcon\Autoload\Loader;
use Phalcon\Http\Response;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Router;

const BASE_PATH = __DIR__ . '/..';

require BASE_PATH . '/vendor/autoload.php';

$loader = new Loader();
$loader->setNamespaces([
    'App\\Controllers' => BASE_PATH . '/app/Controllers/',
    'App\\Library' => BASE_PATH . '/app/Library/',
    'App\\Services' => BASE_PATH . '/app/Services/',
]);
$loader->register();

$container = require BASE_PATH . '/config/services.php';

$router = new Router(false);
$router->addGet('/', ['controller' => 'index', 'action' => 'index']);
$router->addPost('/api/auth/login', ['controller' => 'auth', 'action' => 'login']);
$router->addGet('/api/auth/me', ['controller' => 'auth', 'action' => 'me']);
$router->addPost('/api/auth/logout', ['controller' => 'auth', 'action' => 'logout']);
$router->addGet('/api/products', ['controller' => 'products', 'action' => 'index']);
$router->addGet('/api/products/aggregate', ['controller' => 'products', 'action' => 'aggregate']);
$router->addPost('/api/products', ['controller' => 'products', 'action' => 'create']);
$router->addPut('/api/products/{id:[0-9]+}', ['controller' => 'products', 'action' => 'update']);
$router->addDelete('/api/products/{id:[0-9]+}', ['controller' => 'products', 'action' => 'delete']);
$router->addGet('/api/categories', ['controller' => 'categories', 'action' => 'index']);
$router->addPost('/api/categories', ['controller' => 'categories', 'action' => 'create']);
$router->addPut('/api/categories/{id:[0-9]+}', ['controller' => 'categories', 'action' => 'update']);
$router->addDelete('/api/categories/{id:[0-9]+}', ['controller' => 'categories', 'action' => 'delete']);
$router->removeExtraSlashes(true);
$router->notFound(['controller' => 'errors', 'action' => 'notFound']);
$container->setShared('router', $router);

try {
    $application = new Application($container);
    $response = $application->handle($_SERVER['REQUEST_URI']);
    $response->send();
} catch (Throwable $exception) {
    $status = $exception instanceof HttpException ? $exception->getStatusCode() : 500;
    $debug = filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN);

    $payload = $exception instanceof HttpException
        ? ApiResponse::error($exception->getMessage(), $status, $exception->getDetails())
        : ApiResponse::error(
            $debug ? $exception->getMessage() : 'Internal server error.',
            500,
            $debug ? ['exception' => get_class($exception)] : []
        );

    $response = new Response();
    $response->setStatusCode($status);

    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    if (str_starts_with($uri, '/api/')) {
        $response->setJsonContent($payload);
    } else {
        $response->setContent('<h1>Application error</h1><pre>' . htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT), ENT_QUOTES) . '</pre>');
    }

    $response->send();
}
