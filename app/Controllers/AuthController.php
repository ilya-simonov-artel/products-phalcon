<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Library\ApiResponse;
use App\Library\HttpException;
use Phalcon\Http\ResponseInterface;

final class AuthController extends ControllerBase
{
    public function loginAction(): ResponseInterface
    {
        try {
            $payload = $this->readJsonBody();
            $username = $payload['username'] ?? null;
            $password = $payload['password'] ?? null;

            $errors = [];

            if (!is_string($username) || trim($username) === '') {
                $errors['username'] = 'Поле "Логин" обязательно для заполнения.';
            }

            if (!is_string($password) || $password === '') {
                $errors['password'] = 'Поле "Пароль" обязательно для заполнения.';
            }

            if ($errors !== []) {
                throw new HttpException('Пожалуйста, заполните все обязательные поля корректно.', 422, $errors);
            }

            $username = trim($username);
            $data = $this->container->get('authService')->login($username, $password);

            return $this->json(ApiResponse::success($data));
        } catch (\Throwable $exception) {
            return $this->handleApiException($exception);
        }
    }

    public function meAction(): ResponseInterface
    {
        try {
            $user = $this->requireBearerToken();
            return $this->json(ApiResponse::success(['user' => $user]));
        } catch (HttpException $exception) {
            return $this->json(ApiResponse::error('Необходима авторизация. Пожалуйста, войдите в систему.', 401), 401);
        } catch (\Throwable $exception) {
            return $this->handleApiException($exception);
        }
    }

    public function logoutAction(): ResponseInterface
    {
        try {
            $token = $this->extractBearerToken();

            if (!$token) {
                throw new HttpException('Не найден токен авторизации. Повторите вход.', 401);
            }

            $this->container->get('authService')->revoke($token);

            return $this->json(ApiResponse::success(['logged_out' => true]));
        } catch (HttpException $exception) {
            return $this->json(ApiResponse::error('Необходима авторизация для выхода.', 401), 401);
        } catch (\Throwable $exception) {
            return $this->handleApiException($exception);
        }
    }
}
