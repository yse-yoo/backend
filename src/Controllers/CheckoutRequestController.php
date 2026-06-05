<?php

namespace App\Controllers;

use App\Lib\Http;
use App\Repositories\CheckoutRequestRepository;
use RuntimeException;

final class CheckoutRequestController
{
    public function __construct(private readonly CheckoutRequestRepository $checkoutRequests)
    {
    }

    public function current(): void
    {
        Http::success($this->checkoutRequests->current());
    }

    public function show(string $id): void
    {
        $request = $this->checkoutRequests->find($id);

        if ($request === null) {
            Http::error('Checkout request not found.', 404);
        }

        Http::success($request);
    }

    public function store(): void
    {
        $body = Http::jsonBody();
        $errors = $this->validate($body);

        if ($errors !== []) {
            Http::error('Validation failed.', 422, $errors);
        }

        Http::success($this->checkoutRequests->create($body), 201);
    }

    public function complete(string $id): void
    {
        try {
            $delayMs = (int)($_ENV['CHECKOUT_REQUEST_PROCESSING_DELAY'] ?? 5000);
            usleep($delayMs * 1000);

            Http::success($this->checkoutRequests->complete($id));
        } catch (RuntimeException $exception) {
            Http::error($exception->getMessage(), 422);
        }
    }

    public function cancel(string $id): void
    {
        $request = $this->checkoutRequests->cancel($id);

        if ($request === null) {
            Http::error('Checkout request not found.', 404);
        }

        Http::success($request);
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

        $orderType = $body['order_type'] ?? 'dineIn';
        if (!in_array($orderType, ['dineIn', 'takeout'], true)) {
            $errors['order_type'] = 'Order type must be dineIn or takeout.';
        }

        if (isset($body['tax_rate']) && (!is_numeric($body['tax_rate']) || !in_array((float)$body['tax_rate'], [8.0, 10.0], true))) {
            $errors['tax_rate'] = 'Tax rate must be 8 or 10.';
        }

        foreach (['subtotal', 'tax_total', 'total'] as $amountKey) {
            if (isset($body[$amountKey]) && (!is_numeric($body[$amountKey]) || (int)$body[$amountKey] < 0)) {
                $errors[$amountKey] = "{$amountKey} must be 0 or greater.";
            }
        }

        return $errors;
    }
}
