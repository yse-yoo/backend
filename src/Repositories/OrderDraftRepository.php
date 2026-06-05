<?php

namespace App\Repositories;

final class OrderDraftRepository
{
    private const CACHE_KEY = 'order_draft';
    private const TTL_SECONDS = 4 * 3600; // 4時間：営業セッション1日分

    public function current(): ?array
    {
        $draft = apcu_fetch(self::CACHE_KEY);

        return $draft !== false ? $draft : null;
    }

    public function save(array $body): ?array
    {
        $items = $body['items'] ?? [];

        if (!is_array($items) || $items === []) {
            $this->clear();
            return null;
        }

        $draft = [
            'id' => 1,
            'order_type' => (string)($body['order_type'] ?? 'dineIn'),
            'tax_rate' => (int)($body['tax_rate'] ?? 10),
            'subtotal' => (int)($body['subtotal'] ?? 0),
            'tax_total' => (int)($body['tax_total'] ?? 0),
            'total' => (int)($body['total'] ?? 0),
            'items' => $items,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        apcu_store(self::CACHE_KEY, $draft, self::TTL_SECONDS);

        return $draft;
    }

    public function clear(): void
    {
        apcu_delete(self::CACHE_KEY);
    }
}
