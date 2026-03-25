<?php

declare(strict_types=1);

namespace App\Services;

use App\Library\HttpException;
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\Enum;

final class ProductService
{
    public function __construct(
        private readonly AdapterInterface $db,
        private readonly CategoryService $categoryService,
    ) {
    }

    public function list(array $filters, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        [$whereSql, $params] = $this->buildFilter($filters, false);

        $items = $this->db->fetchAll(
            "SELECT p.id, p.name, p.content, p.price, p.in_stock, p.category_id, c.name AS category_name
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             {$whereSql}
             ORDER BY p.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            Enum::FETCH_ASSOC,
            $params
        );

        $total = (int) ($this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM products p {$whereSql}",
            Enum::FETCH_ASSOC,
            $params
        )['total'] ?? 0);

        $aggregate = $this->aggregate($filters);

        return [
            'items' => array_map(static function (array $item): array {
                return [
                    'id' => (int) $item['id'],
                    'name' => $item['name'],
                    'content' => $item['content'],
                    'price' => (float) $item['price'],
                    'in_stock' => (bool) $item['in_stock'],
                    'category_id' => (int) $item['category_id'],
                    'category' => $item['category_name'],
                ];
            }, $items),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
            'aggregate' => $aggregate,
        ];
    }

    public function aggregate(array $filters): array
    {
        [$whereSql, $params] = $this->buildFilter($filters, true);

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total_count, COALESCE(SUM(price), 0) AS total_value
             FROM products p
             {$whereSql}",
            Enum::FETCH_ASSOC,
            $params
        ) ?: ['total_count' => 0, 'total_value' => 0];

        return [
            'in_stock_count' => (int) $row['total_count'],
            'in_stock_total_value' => (float) $row['total_value'],
        ];
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT id, name, content, price, category_id, in_stock FROM products WHERE id = :id',
            Enum::FETCH_ASSOC,
            ['id' => $id]
        );

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'content' => $row['content'],
            'price' => (float) $row['price'],
            'category_id' => (int) $row['category_id'],
            'in_stock' => (bool) $row['in_stock'],
        ];
    }

    public function create(array $payload): array
    {
        $data = $this->validatePayload($payload);

        $this->db->execute(
            'INSERT INTO products (name, content, price, category_id, in_stock, created_at, updated_at)
             VALUES (:name, :content, :price, :category_id, :in_stock, NOW(), NOW())',
            $data
        );

        return $this->findById((int) $this->db->lastInsertId());
    }

    public function update(int $id, array $payload): array
    {
        $product = $this->findById($id);
        if (!$product) {
            throw new HttpException('Product not found.', 404);
        }

        $data = $this->validatePayload(array_merge($product, $payload));
        $data['id'] = $id;

        $this->db->execute(
            'UPDATE products
             SET name = :name, content = :content, price = :price, category_id = :category_id, in_stock = :in_stock, updated_at = NOW()
             WHERE id = :id',
            $data
        );

        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        if (!$this->findById($id)) {
            throw new HttpException('Product not found.', 404);
        }

        $this->db->execute('DELETE FROM products WHERE id = :id', ['id' => $id]);
    }

    private function validatePayload(array $payload): array
    {
        $allowedFields = ['name', 'content', 'price', 'category_id', 'in_stock', 'id', 'created_at', 'updated_at'];
        $unknownFields = array_diff(array_keys($payload), $allowedFields);

        $name = trim((string) ($payload['name'] ?? ''));
        $content = trim((string) ($payload['content'] ?? ''));
        $price = $payload['price'] ?? null;
        $categoryId = isset($payload['category_id']) ? (int) $payload['category_id'] : 0;
        $inStockRaw = $payload['in_stock'] ?? false;
        $inStock = filter_var($inStockRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Введите название товара.';
        }
        if ($content === '') {
            $errors['content'] = 'Введите описание товара.';
        }
        if (!is_numeric($price) || (float) $price < 0) {
            $errors['price'] = 'Цена должна быть неотрицательным числом.';
        }
        if ($categoryId <= 0 || !$this->categoryService->findById($categoryId)) {
            $errors['category_id'] = 'Выберите корректную категорию.';
        }
        if ($inStock === null) {
            $errors['in_stock'] = 'Признак наличия должен быть true или false.';
        }
        if ($unknownFields !== []) {
            $errors['_common'] = 'Переданы недопустимые поля: ' . implode(', ', $unknownFields);
        }

        if ($errors !== []) {
            throw new HttpException('Validation failed.', 422, $errors);
        }

        return [
            'name' => $name,
            'content' => $content,
            'price' => number_format((float) $price, 2, '.', ''),
            'category_id' => $categoryId,
            'in_stock' => $inStock ? 1 : 0,
        ];
    }

    private function buildFilter(array $filters, bool $forceInStock): array
    {
        $conditions = [];
        $params = [];

        $categoryId = isset($filters['category_id']) && $filters['category_id'] !== '' ? (int) $filters['category_id'] : null;
        if ($categoryId) {
            $categoryIds = $this->categoryService->getDescendantIds($categoryId);
            if ($categoryIds === []) {
                $conditions[] = '1 = 0';
            } else {
                $placeholders = [];
                foreach ($categoryIds as $index => $id) {
                    $key = 'category_' . $index;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $id;
                }
                $conditions[] = 'p.category_id IN (' . implode(', ', $placeholders) . ')';
            }
        }

        if ($forceInStock) {
            $conditions[] = 'p.in_stock = 1';
        } elseif (array_key_exists('in_stock', $filters) && $filters['in_stock'] !== '' && $filters['in_stock'] !== null) {
            $inStock = filter_var($filters['in_stock'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($inStock !== null) {
                $conditions[] = 'p.in_stock = :in_stock';
                $params['in_stock'] = $inStock ? 1 : 0;
            }
        }

        $whereSql = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        return [$whereSql, $params];
    }
}
