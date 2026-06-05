<?php

namespace App\Repositories;

use PDO;
use RuntimeException;

final class CheckoutRequestRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly SaleRepository $sales,
        private readonly ?OrderDraftRepository $orderDrafts = null,
    )
    {
        $this->ensureTable();
    }

    public function create(array $body): array
    {
        // Ensure only one pending checkout exists at a time
        $this->pdo->exec("UPDATE checkout_requests SET status = 'canceled' WHERE status = 'pending'");

        $id = 'chk_' . bin2hex(random_bytes(12));
        $items = $body['items'];
        $paymentMethod = (string)($body['payment_method'] ?? 'cash');
        $orderType = (string)($body['order_type'] ?? 'dineIn');
        $taxRate = (int)($body['tax_rate'] ?? 10);
        $subtotal = (int)($body['subtotal'] ?? 0);
        $taxTotal = (int)($body['tax_total'] ?? 0);
        $total = (int)($body['total'] ?? 0);

        $stmt = $this->pdo->prepare(
            'INSERT INTO checkout_requests
                (id, status, payment_method, order_type, tax_rate, subtotal, tax_total, total, items_json)
             VALUES
                (:id, :status, :payment_method, :order_type, :tax_rate, :subtotal, :tax_total, :total, :items_json)'
        );
        $stmt->execute([
            ':id' => $id,
            ':status' => 'pending',
            ':payment_method' => $paymentMethod,
            ':order_type' => $orderType,
            ':tax_rate' => $taxRate,
            ':subtotal' => $subtotal,
            ':tax_total' => $taxTotal,
            ':total' => $total,
            ':items_json' => json_encode($items, JSON_THROW_ON_ERROR),
        ]);

        $request = $this->find($id);
        if ($request === null) {
            throw new RuntimeException('Created checkout request could not be loaded.');
        }

        return $request;
    }

    public function current(): ?array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM checkout_requests
             WHERE status = 'pending'
             ORDER BY created_at DESC
             LIMIT 1"
        );
        $request = $stmt->fetch();

        return $request ? $this->map($request) : null;
    }

    public function find(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM checkout_requests WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $request = $stmt->fetch();

        return $request ? $this->map($request) : null;
    }

    public function complete(string $id): array
    {
        $request = $this->find($id);
        if ($request === null) {
            throw new RuntimeException('Checkout request not found.');
        }

        if ($request['status'] === 'completed') {
            $this->orderDrafts?->clear();
            return $request;
        }

        if ($request['status'] !== 'pending') {
            throw new RuntimeException('Checkout request is not pending.');
        }

        $sale = $this->sales->create([
            'items' => $request['items'],
            'payment_method' => $request['payment_method'],
            'tax_rate' => $request['tax_rate'],
        ]);

        $stmt = $this->pdo->prepare(
            "UPDATE checkout_requests
             SET status = 'completed', sale_id = :sale_id
             WHERE id = :id"
        );
        $stmt->execute([
            ':sale_id' => $sale['id'],
            ':id' => $id,
        ]);
        $this->orderDrafts?->clear();

        $completed = $this->find($id);
        if ($completed === null) {
            throw new RuntimeException('Completed checkout request could not be loaded.');
        }

        return $completed;
    }

    public function cancel(string $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "UPDATE checkout_requests
             SET status = 'canceled'
             WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute([':id' => $id]);

        return $this->find($id);
    }

    private function map(array $request): array
    {
        $request['items'] = json_decode((string)$request['items_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($request['items_json']);

        $request['tax_rate'] = (int)$request['tax_rate'];
        $request['subtotal'] = (int)$request['subtotal'];
        $request['tax_total'] = (int)$request['tax_total'];
        $request['total'] = (int)$request['total'];
        $request['sale_id'] = $request['sale_id'] === null ? null : (int)$request['sale_id'];
        $request['sale'] = $request['sale_id'] === null ? null : $this->sales->findWithItems((int)$request['sale_id']);

        return $request;
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS checkout_requests (
                id VARCHAR(40) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                payment_method VARCHAR(20) NOT NULL DEFAULT 'cash',
                order_type VARCHAR(20) NOT NULL DEFAULT 'dineIn',
                tax_rate INT UNSIGNED NOT NULL DEFAULT 10,
                subtotal INT UNSIGNED NOT NULL DEFAULT 0,
                tax_total INT UNSIGNED NOT NULL DEFAULT 0,
                total INT UNSIGNED NOT NULL DEFAULT 0,
                items_json JSON NOT NULL,
                sale_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_checkout_requests_status_created (status, created_at),
                KEY idx_checkout_requests_sale (sale_id),
                CONSTRAINT fk_checkout_requests_sale
                    FOREIGN KEY (sale_id) REFERENCES sales (id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
