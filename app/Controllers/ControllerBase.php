<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Library\ApiResponse;
use App\Library\HttpException;
use Phalcon\Mvc\Controller;
use Phalcon\Http\ResponseInterface;

abstract class ControllerBase extends Controller
{
    protected function json(array $payload, int $status = 200): ResponseInterface
    {
        $this->response->setStatusCode($status);
        $this->response->setJsonContent($payload);

        return $this->response;
    }

    protected function readJsonBody(): array
    {
        $raw = $this->request->getJsonRawBody(true);
        if (!is_array($raw)) {
            throw new HttpException('Request body must be a valid JSON object.', 400);
        }

        return $raw;
    }

    /**
     * @return array{id:int,username:string,display_name:string}
     */
    protected function requireBearerToken(): array
    {
        return $this->container->get('authService')->authenticate($this->extractBearerToken());
    }

    protected function extractBearerToken(): string
    {
        $header = (string) $this->request->getHeader('Authorization');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            throw new HttpException('Bearer token is required.', 401);
        }

        $token = trim($matches[1]);
        if ($token === '') {
            throw new HttpException('Bearer token is required.', 401);
        }

        return $token;
    }

    protected function handleApiException(\Throwable $exception): ResponseInterface
    {
        $status = $exception instanceof HttpException ? $exception->getStatusCode() : 500;
        $details = $exception instanceof HttpException ? $exception->getDetails() : [];
        $message = $status === 500 ? 'Internal server error.' : $exception->getMessage();

        return $this->json(ApiResponse::error($message, $status, $details), $status);
    }

    /**
     * Возвращает пути к main.js и main.css из Vite manifest.json для шаблона.
     * @return array{js: string|null, css: string|null}
     */
    protected function getViteAssets(): array
    {
        $manifestCandidates = [
            BASE_PATH . '/public/dist/manifest.json',
            BASE_PATH . '/public/dist/.vite/manifest.json',
        ];

        $manifestPath = null;
        foreach ($manifestCandidates as $candidate) {
            if (file_exists($candidate)) {
                $manifestPath = $candidate;
                break;
            }
        }

        if ($manifestPath === null) {
            return ['js' => null, 'css' => null];
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);

        if (!is_array($manifest)) {
            return ['js' => null, 'css' => null];
        }

        $main = $manifest['src/main.ts'] ?? $manifest['main.ts'] ?? null;

        if (!$main) {
            foreach ($manifest as $entry) {
                if (is_array($entry) && !empty($entry['isEntry']) && !empty($entry['file'])) {
                    $main = $entry;
                    break;
                }
            }
        }

        if (!$main || !isset($main['file'])) {
            return ['js' => null, 'css' => null];
        }

        $js = '/dist/' . ltrim($main['file'], '/');
        $css = isset($main['css'][0]) ? ('/dist/' . ltrim($main['css'][0], '/')) : null;

        return ['js' => $js, 'css' => $css];
    }
}
