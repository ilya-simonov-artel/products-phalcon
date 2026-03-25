<?php

declare(strict_types=1);

namespace App\Services;

use App\Library\HttpException;
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\Enum;

final class CategoryService
{
    public function __construct(private readonly AdapterInterface $db)
    {
    }

    public function getTree(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, name, parent_id FROM categories ORDER BY parent_id IS NOT NULL, parent_id, name',
            Enum::FETCH_ASSOC
        );

        $byParent = [];
        foreach ($rows as $row) {
            $row['id'] = (int) $row['id'];
            $row['parent_id'] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
            $row['children'] = [];
            $byParent[$row['parent_id'] ?? 0][] = $row;
        }

        $build = function (?int $parentId) use (&$build, $byParent): array {
            $branch = [];
            foreach ($byParent[$parentId ?? 0] ?? [] as $item) {
                $item['children'] = $build($item['id']);
                $branch[] = $item;
            }

            return $branch;
        };

        return $build(null);
    }

    public function getFlat(): array
    {
        return $this->db->fetchAll(
            'WITH RECURSIVE category_path AS (
                SELECT id, name, parent_id, CAST(name AS CHAR(255)) AS full_name
                FROM categories
                WHERE parent_id IS NULL
                UNION ALL
                SELECT c.id, c.name, c.parent_id, CONCAT(cp.full_name, " / ", c.name) AS full_name
                FROM categories c
                INNER JOIN category_path cp ON cp.id = c.parent_id
            )
            SELECT id, name, parent_id, full_name FROM category_path ORDER BY full_name',
            Enum::FETCH_ASSOC
        );
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetchOne('SELECT id, name, parent_id FROM categories WHERE id = :id', Enum::FETCH_ASSOC, ['id' => $id]);

        if (!$row) {
            return null;
        }

        $row['id'] = (int) $row['id'];
        $row['parent_id'] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;

        return $row;
    }

    public function create(array $payload): array
    {
        $this->ensureAllowedFields($payload, ['name', 'parent_id']);

        $name = trim((string) ($payload['name'] ?? ''));
        $parentId = isset($payload['parent_id']) && $payload['parent_id'] !== '' ? (int) $payload['parent_id'] : null;

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Введите название категории.';
        }

        if ($parentId !== null && !$this->findById($parentId)) {
            $errors['parent_id'] = 'Родительская категория не найдена.';
        }

        if ($errors !== []) {
            throw new HttpException('Validation failed.', 422, $errors);
        }

        $this->db->execute(
            'INSERT INTO categories (name, parent_id, created_at, updated_at) VALUES (:name, :parent_id, NOW(), NOW())',
            ['name' => $name, 'parent_id' => $parentId]
        );

        return $this->findById((int) $this->db->lastInsertId());
    }

    public function update(int $id, array $payload): array
    {
        $this->ensureAllowedFields($payload, ['name', 'parent_id']);

        $category = $this->findById($id);
        if (!$category) {
            throw new HttpException('Category not found.', 404);
        }

        $name = trim((string) ($payload['name'] ?? $category['name']));
        $parentId = array_key_exists('parent_id', $payload)
            ? ($payload['parent_id'] !== '' && $payload['parent_id'] !== null ? (int) $payload['parent_id'] : null)
            : $category['parent_id'];

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Введите название категории.';
        }

        if ($parentId === $id) {
            $errors['parent_id'] = 'Категория не может быть родителем самой себя.';
        }

        if ($parentId !== null) {
            if (!$this->findById($parentId)) {
                $errors['parent_id'] = 'Родительская категория не найдена.';
            }

            if (in_array($parentId, $this->getDescendantIds($id), true)) {
                $errors['parent_id'] = 'Нельзя переносить категорию внутрь её поддерева.';
            }
        }

        if ($errors !== []) {
            throw new HttpException('Validation failed.', 422, $errors);
        }

        $this->db->execute(
            'UPDATE categories SET name = :name, parent_id = :parent_id, updated_at = NOW() WHERE id = :id',
            ['id' => $id, 'name' => $name, 'parent_id' => $parentId]
        );

        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        $category = $this->findById($id);
        if (!$category) {
            throw new HttpException('Category not found.', 404);
        }

        $hasChildren = (bool) $this->db->fetchOne('SELECT 1 FROM categories WHERE parent_id = :id LIMIT 1', Enum::FETCH_NUM, ['id' => $id]);
        if ($hasChildren) {
            throw new HttpException('Delete child categories first.', 409);
        }

        $hasProducts = (bool) $this->db->fetchOne('SELECT 1 FROM products WHERE category_id = :id LIMIT 1', Enum::FETCH_NUM, ['id' => $id]);
        if ($hasProducts) {
            throw new HttpException('Delete or move products from the category first.', 409);
        }

        $this->db->execute('DELETE FROM categories WHERE id = :id', ['id' => $id]);
    }

    public function getDescendantIds(int $categoryId): array
    {
        $rows = $this->db->fetchAll(
            'WITH RECURSIVE descendants AS (
                SELECT id, parent_id FROM categories WHERE id = :id
                UNION ALL
                SELECT c.id, c.parent_id FROM categories c
                INNER JOIN descendants d ON d.id = c.parent_id
            )
            SELECT id FROM descendants',
            Enum::FETCH_ASSOC,
            ['id' => $categoryId]
        );

        return array_map(static fn(array $row): int => (int) $row['id'], $rows);
    }

    private function ensureAllowedFields(array $payload, array $allowedFields): void
    {
        $unknown = array_diff(array_keys($payload), $allowedFields);
        if ($unknown !== []) {
            throw new HttpException('Validation failed.', 422, [
                '_common' => 'Переданы недопустимые поля: ' . implode(', ', $unknown),
            ]);
        }
    }
}
