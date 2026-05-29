<?php

namespace App\Repositories;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class SaleRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAll(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (isset($filters['date_from']) && $filters['date_from'] !== '') {
            $conditions[] = 'sold_at >= :date_from';
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (isset($filters['date_to']) && $filters['date_to'] !== '') {
            $conditions[] = 'sold_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $conditions[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        $limit = min(max((int)($filters['limit'] ?? 50), 1), 200);
        $offset = max((int)($filters['offset'] ?? 0), 0);

        $sql = 'SELECT id, receipt_number, sold_at, subtotal, tax_total, total,
                       payment_method, square_payment_id, cash_received, change_amount, status, note,
                       created_at, updated_at
                FROM sales';

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY sold_at DESC, id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findWithItems(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, receipt_number, sold_at, subtotal, tax_total, total,
                    payment_method, square_payment_id, cash_received, change_amount, status, note,
                    created_at, updated_at
             FROM sales
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $sale = $stmt->fetch();

        if (!$sale) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, sale_id, product_id, product_name, category_name, unit_price,
                    quantity, tax_rate, tax_amount, subtotal, total, created_at
             FROM sale_items
             WHERE sale_id = :sale_id
             ORDER BY id ASC'
        );
        $stmt->execute([':sale_id' => $id]);
        $sale['items'] = $stmt->fetchAll();

        return $sale;
    }

    public function create(array $body): array
    {
        $this->pdo->beginTransaction();

        try {
            $taxRate = isset($body['tax_rate']) && $body['tax_rate'] !== null ? (float)$body['tax_rate'] : null;
            $items = $this->buildItems($body['items'], $taxRate);
            $subtotal = array_sum(array_column($items, 'subtotal'));
            $taxTotal = array_sum(array_column($items, 'tax_amount'));
            $total = $subtotal + $taxTotal;
            $paymentMethod = (string)($body['payment_method'] ?? 'cash');
            $cashReceived = isset($body['cash_received']) && $body['cash_received'] !== null ? (int)$body['cash_received'] : null;

            if ($paymentMethod === 'cash' && $cashReceived !== null && $cashReceived < $total) {
                throw new InvalidArgumentException('Cash received is less than total.');
            }

            $changeAmount = $paymentMethod === 'cash' && $cashReceived !== null ? $cashReceived - $total : null;
            $soldAt = isset($body['sold_at']) && $body['sold_at'] !== '' ? (string)$body['sold_at'] : date('Y-m-d H:i:s');

            $squarePaymentId = isset($body['square_payment_id']) && $body['square_payment_id'] !== ''
                ? (string)$body['square_payment_id']
                : null;

            $stmt = $this->pdo->prepare(
                'INSERT INTO sales
                    (sold_at, subtotal, tax_total, total, payment_method, square_payment_id,
                     cash_received, change_amount, status, note)
                 VALUES
                    (:sold_at, :subtotal, :tax_total, :total, :payment_method, :square_payment_id,
                     :cash_received, :change_amount, :status, :note)'
            );
            $stmt->execute([
                ':sold_at' => $soldAt,
                ':subtotal' => $subtotal,
                ':tax_total' => $taxTotal,
                ':total' => $total,
                ':payment_method' => $paymentMethod,
                ':square_payment_id' => $squarePaymentId,
                ':cash_received' => $cashReceived,
                ':change_amount' => $changeAmount,
                ':status' => 'completed',
                ':note' => isset($body['note']) && $body['note'] !== '' ? (string)$body['note'] : null,
            ]);

            $saleId = (int)$this->pdo->lastInsertId();
            $receiptNumber = 'R' . date('Ymd', strtotime($soldAt)) . '-' . str_pad((string)$saleId, 6, '0', STR_PAD_LEFT);

            $stmt = $this->pdo->prepare('UPDATE sales SET receipt_number = :receipt_number WHERE id = :id');
            $stmt->execute([
                ':receipt_number' => $receiptNumber,
                ':id' => $saleId,
            ]);

            $this->insertItems($saleId, $items);
            $this->pdo->commit();

            $sale = $this->findWithItems($saleId);
            if ($sale === null) {
                throw new RuntimeException('Created sale could not be loaded.');
            }

            return $sale;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function buildItems(array $requestItems, ?float $taxRateOverride = null): array
    {
        $quantities = [];

        foreach ($requestItems as $item) {
            $productId = (int)$item['product_id'];
            $quantities[$productId] = ($quantities[$productId] ?? 0) + (int)$item['quantity'];
        }

        $placeholders = implode(',', array_fill(0, count($quantities), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.name, p.price, p.tax_rate, p.is_active, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id IN ($placeholders)"
        );
        $stmt->execute(array_keys($quantities));

        $products = [];
        foreach ($stmt->fetchAll() as $product) {
            $products[(int)$product['id']] = $product;
        }

        $saleItems = [];

        foreach ($quantities as $productId => $quantity) {
            if (!isset($products[$productId]) || (int)$products[$productId]['is_active'] !== 1) {
                throw new InvalidArgumentException("Product {$productId} is not available.");
            }

            $product = $products[$productId];
            $unitPrice = (int)$product['price'];
            $taxRate = $taxRateOverride ?? (float)$product['tax_rate'];
            $subtotal = $unitPrice * $quantity;
            $taxAmount = (int)round($subtotal * ($taxRate / 100));
            $total = $subtotal + $taxAmount;

            $saleItems[] = [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'category_name' => $product['category_name'],
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'subtotal' => $subtotal,
                'total' => $total,
            ];
        }

        return $saleItems;
    }

    private function insertItems(int $saleId, array $items): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sale_items
                (sale_id, product_id, product_name, category_name, unit_price, quantity,
                 tax_rate, tax_amount, subtotal, total)
             VALUES
                (:sale_id, :product_id, :product_name, :category_name, :unit_price, :quantity,
                 :tax_rate, :tax_amount, :subtotal, :total)'
        );

        foreach ($items as $item) {
            $stmt->execute([
                ':sale_id' => $saleId,
                ':product_id' => $item['product_id'],
                ':product_name' => $item['product_name'],
                ':category_name' => $item['category_name'],
                ':unit_price' => $item['unit_price'],
                ':quantity' => $item['quantity'],
                ':tax_rate' => $item['tax_rate'],
                ':tax_amount' => $item['tax_amount'],
                ':subtotal' => $item['subtotal'],
                ':total' => $item['total'],
            ]);
        }
    }
}
