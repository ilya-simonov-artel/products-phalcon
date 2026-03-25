<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Library\ApiResponse;
use Phalcon\Http\ResponseInterface;

final class ErrorsController extends ControllerBase
{
    public function notFoundAction(): ResponseInterface
    {
        $uri = $this->request->getURI();
        if (str_starts_with($uri, '/api/')) {
            return $this->json(ApiResponse::error('Resource not found.', 404), 404);
        }

        $this->response->setStatusCode(404);
        $this->view->disable();
        $this->response->setContent('<h1>404 Not Found</h1>');

        return $this->response;
    }
}
