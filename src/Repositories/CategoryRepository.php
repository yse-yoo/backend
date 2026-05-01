<?php

namespace App\Repositories;

use PDO;

final class CategoryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAll(bool $includeInactive = false): array
    {
        $sql = 'SELECT id, name, display_order, is_active, created_at, updated_at FROM categories';

        if (!$includeInactive) {
            $sql .= ' WHERE is_active = 1';
        }

        $sql .= ' ORDER BY display_order ASC, id ASC';

        return $this->pdo->query($sql)->fetchAll();
    }

    public function create(array $params): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (name, display_order, is_active) VALUES (:name, :display_order, :is_active)'
        );
        $stmt->execute($params);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $params): bool
    {
        $params[':id'] = $id;

        $stmt = $this->pdo->prepare(
            'UPDATE categories
             SET name = :name, display_order = :display_order, is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE categories SET is_active = 0 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function exists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM categories WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return (bool)$stmt->fetch();
    }
}
