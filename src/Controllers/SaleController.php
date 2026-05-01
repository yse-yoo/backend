<?php

namespace App\Controllers;

use App\Lib\Http;
use App\Repositories\SaleRepository;
use InvalidArgumentException;

final class SaleController
{
    public function __construct(private readonly SaleRepository $sales)
    {
    }

    public function index(): void
    {
        Http::success($this->sales->findAll([
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'status' => $_GET['status'] ?? null,
            'limit' => $_GET['limit'] ?? 50,
            'offset' => $_GET['offset'] ?? 0,
        ]));
    }

    public function show(int $id): void
    {
        $sale = $this->sales->findWithItems($id);

        if ($sale === null) {
            Http::error('Sale not found.', 404);
        }

        Http::success($sale);
    }

    public function store(): void
    {
        $body = Http::jsonBody();
        $errors = $this->validate($body);

        if ($errors !== []) {
            Http::error('Validation failed.', 422, $errors);
        }

        try {
            Http::success($this->sales->create($body), 201);
        } catch (InvalidArgumentException $exception) {
            Http::error($exception->getMessage(), 422);
        }
    }

    /**
     * @return array<string, string>
     */
    private function validate(array $body): array
    {
        $errors = [];

        if (!isset($body['items']) || !is_array($body['items']) || $body['items'] === []) {
            $errors['items'] = 'At least one item is required.';
            return $errors;
        }

        foreach ($body['items'] as $index => $item) {
            if (!is_array($item)) {
                $errors["items.$index"] = 'Item must be an object.';
                continue;
            }

            if (!isset($item['product_id']) || !is_numeric($item['product_id'])) {
                $errors["items.$index.product_id"] = 'Product ID is required.';
            }

            if (!isset($item['quantity']) || !is_numeric($item['quantity']) || (int)$item['quantity'] < 1) {
                $errors["items.$index.quantity"] = 'Quantity must be 1 or greater.';
            }
        }

        $paymentMethod = $body['payment_method'] ?? 'cash';
        if (!in_array($paymentMethod, ['cash', 'card', 'qr', 'other'], true)) {
            $errors['payment_method'] = 'Payment method must be cash, card, qr, or other.';
        }

        if (isset($body['cash_received']) && $body['cash_received'] !== null && !is_numeric($body['cash_received'])) {
            $errors['cash_received'] = 'Cash received must be numeric or null.';
        }

        return $errors;
    }
}
