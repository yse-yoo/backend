<?php

namespace App\Controllers;

use App\Lib\Http;
use App\Repositories\SaleRepository;
use InvalidArgumentException;

final class SquareController
{
    public function __construct(private readonly SaleRepository $sales)
    {
    }

    public function checkout(): void
    {
        $body = Http::jsonBody();
        $errors = $this->validate($body);

        if ($errors !== []) {
            Http::error('Validation failed.', 422, $errors);
        }

        $paymentMethod = $body['payment_method'] ?? 'cash';

        if ($paymentMethod === 'square') {
            $body['square_payment_id'] = 'mock_ckout_' . bin2hex(random_bytes(8));
        }

        // 疑似API処理: Square 端末との通信遅延をサーバー側でシミュレート
        $delayMs = (int)($_ENV['PAYMENT_PROCESSING_DELAY'] ?? 2000);
        usleep($delayMs * 1000);

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
        if (!in_array($paymentMethod, ['cash', 'card', 'qr', 'other', 'square'], true)) {
            $errors['payment_method'] = 'Payment method must be cash, card, qr, other, or square.';
        }

        if (isset($body['cash_received']) && $body['cash_received'] !== null && !is_numeric($body['cash_received'])) {
            $errors['cash_received'] = 'Cash received must be numeric or null.';
        }

        if (isset($body['tax_rate']) && (!is_numeric($body['tax_rate']) || !in_array((float)$body['tax_rate'], [8.0, 10.0], true))) {
            $errors['tax_rate'] = 'Tax rate must be 8 or 10.';
        }

        return $errors;
    }
}
