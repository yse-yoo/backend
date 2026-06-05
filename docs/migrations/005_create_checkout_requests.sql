-- Migration: 005 - Create checkout requests table
CREATE TABLE IF NOT EXISTS checkout_requests (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
