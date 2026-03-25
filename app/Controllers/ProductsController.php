<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Library\ApiResponse;
use App\Library\HttpException;
use Phalcon\Http\ResponseInterface;

final class ProductsController extends ControllerBase
{
    public function indexAction(): ResponseInterface
    {
        try {
            $this->requireBearerToken();
            $result = $this->container->get('productService')->list(
                [
                    'category_id' => $this->request->getQuery('category_id'),
                    'in_stock' => $this->request->getQuery('in_stock'),
                ],
                (int) $this->request->getQuery('page', null, 1),
                (int) $this->request->getQuery('limit', null, 10),
            );

            return $this->json(ApiResponse::success($result));
        } catch (HttpException $exception) {
            return $this->json(ApiResponse::error('Необходима авторизация для просмотра товаров.', 401), 401);
        } catch (\Throwable $exception) {
            return $this->handleApiException($exception);
        }
    }

    public function aggregateAction(): ResponseInterface
    {
        try {
            $this->requireBearerToken();
            $result = $this->container->get('productService')->aggregate([
                'category_id' => $this->request->getQuery('category_id'),
                'in_stock' => $this->request->getQuery('in_stock'),
            ]);

            return $this->json(ApiResponse::success($result));
        } catch (HttpException $exception) {
            return $this->json(ApiResponse::error('Необходима авторизация для просмотра агрегированных данных.', 401), 401);
        } catch (\Throwable $exception) {
            return $this->handleApiException($exception);
        }
    }

    public function createAction(): ResponseInterface
    {
        try {
            $this->requireBearerToken();
            $product = $this->container->get('productService')->create($this->readJsonBody());

            return $this->json(ApiResponse::success(['product' => $product], 201), 201);
        } catch (HttpException $exception) {
            $msg = $exception->getStatusCode() === 401 ? 'Необходима авторизация для создания товара.' : 'Ошибка валидации данных.';
            return $this->json(ApiResponse::error($msg, $exception->getStatusCode(), $exception->getDetails()), $exception->getStatusCode());
        } catch (\Throwable $exception) {
            return $this->handleApiException($exception);
        }
    }

    public function updateAction(int $id): ResponseInterface
    {
        try {
            $this->requireBearerToken();
            $product = $this->container->get('productService')->update($id, $this->readJsonBody());

            return $this->json(ApiResponse::success(['product' => $product]));
        } catch (HttpException $exception) {
            $msg = $exception->getStatusCode() === 401 ? 'Необходима авторизация для изменения товара.' : 'Ошибка валидации данных.';
            return $this->json(ApiResponse::error($msg, $exception->getStatusCode(), $exception->getDetails()), $exception->getStatusCode());
        } catch (\Throwable $exception) {
            return $this->handleApiException($exception);
        }
    }

    public function deleteAction(int $id): ResponseInterface
    {
        try {
            $this->requireBearerToken();
            $this->container->get('productService')->delete($id);

            return $this->json(ApiResponse::success(['deleted' => true]));
        } catch (HttpException $exception) {
            $msg = $exception->getStatusCode() === 401 ? 'Необходима авторизация для удаления товара.' : 'Ошибка удаления товара.';
            return $this->json(ApiResponse::error($msg, $exception->getStatusCode(), $exception->getDetails()), $exception->getStatusCode());
        } catch (\Throwable $exception) {
            return $this->handleApiException($exception);
        }
    }
}
