<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Library\ApiResponse;
use App\Library\HttpException;
use Phalcon\Http\ResponseInterface;

final class CategoriesController extends ControllerBase
{
    public function indexAction(): ResponseInterface
    {
        try {
            $this->requireBearerToken();
            $service = $this->container->get('categoryService');

            return $this->json(ApiResponse::success([
                'tree' => $service->getTree(),
                'items' => $service->getFlat(),
            ]));
        } catch (HttpException $exception) {
            return $this->json(ApiResponse::error('Необходима авторизация для просмотра категорий.', 401), 401);
        } catch (\Throwable $exception) {
            return $this->handleApiException($exception);
        }
    }

    public function createAction(): ResponseInterface
    {
        try {
            $this->requireBearerToken();
            $category = $this->container->get('categoryService')->create($this->readJsonBody());

            return $this->json(ApiResponse::success(['category' => $category], 201), 201);
        } catch (HttpException $exception) {
            $msg = $exception->getStatusCode() === 401 ? 'Необходима авторизация для создания категории.' : 'Ошибка валидации данных.';
            return $this->json(ApiResponse::error($msg, $exception->getStatusCode(), $exception->getDetails()), $exception->getStatusCode());
        } catch (\Throwable $exception) {
            return $this->handleApiException($exception);
        }
    }

    public function updateAction(int $id): ResponseInterface
    {
        try {
            $this->requireBearerToken();
            $category = $this->container->get('categoryService')->update($id, $this->readJsonBody());

            return $this->json(ApiResponse::success(['category' => $category]));
        } catch (HttpException $exception) {
            $msg = $exception->getStatusCode() === 401 ? 'Необходима авторизация для изменения категории.' : 'Ошибка валидации данных.';
            return $this->json(ApiResponse::error($msg, $exception->getStatusCode(), $exception->getDetails()), $exception->getStatusCode());
        } catch (\Throwable $exception) {
            return $this->handleApiException($exception);
        }
    }

    public function deleteAction(int $id): ResponseInterface
    {
        try {
            $this->requireBearerToken();
            $this->container->get('categoryService')->delete($id);

            return $this->json(ApiResponse::success(['deleted' => true]));
        } catch (HttpException $exception) {
            $msg = $exception->getStatusCode() === 401 ? 'Необходима авторизация для удаления категории.' : 'Ошибка удаления категории.';
            return $this->json(ApiResponse::error($msg, $exception->getStatusCode(), $exception->getDetails()), $exception->getStatusCode());
        } catch (\Throwable $exception) {
            return $this->handleApiException($exception);
        }
    }
}
