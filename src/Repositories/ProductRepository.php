<?php

namespace App\Repositories;

use PDO;

final class ProductRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAll(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (($filters['include_inactive'] ?? false) !== true) {
            $conditions[] = 'p.is_active = 1';
        }

        if (isset($filters['category_id']) && $filters['category_id'] !== '') {
            $conditions[] = 'p.category_id = :category_id';
            $params[':category_id'] = (int)$filters['category_id'];
        }

        if (isset($filters['q']) && trim((string)$filters['q']) !== '') {
            $conditions[] = 'p.name LIKE :q';
            $params[':q'] = '%' . trim((string)$filters['q']) . '%';
        }

        $sql = $this->selectSql();

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY p.display_order ASC, p.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare($this->selectSql() . ' WHERE p.id = :id');
        $stmt->execute([':id' => $id]);
        $product = $stmt->fetch();

        return $product ?: null;
    }

    public function create(array $params): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO products
                (category_id, name, price, tax_rate, tax_type, icon, image_path, stock_quantity, is_active, display_order)
             VALUES
                (:category_id, :name, :price, :tax_rate, :tax_type, :icon, :image_path, :stock_quantity, :is_active, :display_order)'
        );
        $stmt->execute($params);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $params): bool
    {
        $params[':id'] = $id;

        $stmt = $this->pdo->prepare(
            'UPDATE products
             SET category_id = :category_id,
                 name = :name,
                 price = :price,
                 tax_rate = :tax_rate,
                 tax_type = :tax_type,
                 icon = :icon,
                 image_path = :image_path,
                 stock_quantity = :stock_quantity,
                 is_active = :is_active,
                 display_order = :display_order
             WHERE id = :id'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE products SET is_active = 0 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function exists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM products WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return (bool)$stmt->fetch();
    }

    private function selectSql(): string
    {
        return 'SELECT
                    p.id,
                    p.category_id,
                    c.name AS category_name,
                    p.name,
                    p.price,
                    p.tax_rate,
                    p.tax_type,
                    p.icon,
                    p.image_path,
                    p.stock_quantity,
                    p.is_active,
                    p.display_order,
                    p.created_at,
                    p.updated_at
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id';
    }
}
