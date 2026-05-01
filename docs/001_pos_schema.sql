SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name),
    KEY idx_categories_active_order (is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    price INT UNSIGNED NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    tax_type VARCHAR(20) NOT NULL DEFAULT 'standard',
    icon VARCHAR(20) NULL,
    stock_quantity INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_products_category (category_id),
    KEY idx_products_active_order (is_active, display_order),
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_number VARCHAR(40) NULL,
    sold_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    subtotal INT UNSIGNED NOT NULL,
    tax_total INT UNSIGNED NOT NULL,
    total INT UNSIGNED NOT NULL,
    payment_method VARCHAR(20) NOT NULL DEFAULT 'cash',
    cash_received INT UNSIGNED NULL,
    change_amount INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sales_receipt_number (receipt_number),
    KEY idx_sales_sold_at (sold_at),
    KEY idx_sales_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sale_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    product_name VARCHAR(150) NOT NULL,
    category_name VARCHAR(100) NULL,
    unit_price INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    tax_amount INT UNSIGNED NOT NULL,
    subtotal INT UNSIGNED NOT NULL,
    total INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sale_items_sale (sale_id),
    KEY idx_sale_items_product (product_id),
    CONSTRAINT fk_sale_items_sale
        FOREIGN KEY (sale_id) REFERENCES sales (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_sale_items_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (id, name, display_order, is_active) VALUES
    (1, 'フード', 10, 1),
    (2, 'ドリンク', 20, 1),
    (3, 'トッピング', 30, 1),
    (4, '有料袋', 40, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    display_order = VALUES(display_order),
    is_active = VALUES(is_active);

INSERT INTO products (id, category_id, name, price, tax_rate, tax_type, icon, display_order, is_active) VALUES
    (1, 1, 'チョコバナナ生クリーム', 580, 10.00, 'standard', '🍌', 10, 1),
    (2, 1, 'イチゴカスタード', 620, 10.00, 'standard', '🍓', 20, 1),
    (3, 1, '塩キャラメルアーモンド', 550, 10.00, 'standard', '🍯', 30, 1),
    (4, 1, '抹茶あずき白玉', 650, 10.00, 'standard', '🍵', 40, 1),
    (5, 1, 'ハムエッグチーズ', 680, 10.00, 'standard', '🥓', 50, 1),
    (6, 1, 'ツナサラダマヨネーズ', 600, 10.00, 'standard', '🐟', 60, 1),
    (7, 1, '照り焼きチキンサラダ', 720, 10.00, 'standard', '🍗', 70, 1),
    (8, 2, '自家製レモネード', 450, 10.00, 'standard', '🍋', 80, 1),
    (9, 2, 'タピオカミルクティー', 550, 10.00, 'standard', '🧋', 90, 1),
    (10, 2, 'アイスコーヒー', 380, 10.00, 'standard', '☕', 100, 1),
    (11, 3, 'バニラアイス追加', 100, 10.00, 'standard', '🍨', 110, 1),
    (12, 4, 'お持ち帰り用袋', 10, 10.00, 'standard', '🛍️', 120, 1)
ON DUPLICATE KEY UPDATE
    category_id = VALUES(category_id),
    name = VALUES(name),
    price = VALUES(price),
    tax_rate = VALUES(tax_rate),
    tax_type = VALUES(tax_type),
    icon = VALUES(icon),
    display_order = VALUES(display_order),
    is_active = VALUES(is_active);

INSERT INTO settings (setting_key, setting_value) VALUES
    ('store_name', 'POS Demo Store'),
    ('default_tax_rate', '10'),
    ('receipt_footer', 'Thank you')
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value);
