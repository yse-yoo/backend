<?php

namespace App\Controllers;

use App\Lib\Http;
use App\Repositories\OrderDraftRepository;

final class OrderDraftController
{
    public function __construct(private readonly OrderDraftRepository $orderDrafts)
    {
    }

    public function current(): void
    {
        Http::success($this->orderDrafts->current());
    }

    public function save(): void
    {
        $body = Http::jsonBody();
        $errors = $this->validate($body);

        if ($errors !== []) {
            Http::error('Validation failed.', 422, $errors);
        }

        Http::success($this->orderDrafts->save($body));
    }

    public function clear(): void
    {
        $this->orderDrafts->clear();
        Http::success(null);
    }

    /**
     * @return array<string, string>
     */
    private function validate(array $body): array
    {
        $errors = [];
        $items = $body['items'] ?? [];

        if (!is_array($items)) {
            $errors['items'] = 'Items must be an array.';
            return $errors;
        }

        foreach ($items as $index => $item) {
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
