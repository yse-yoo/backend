CREATE TABLE IF NOT EXISTS staffs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    login_id VARCHAR(100) NOT NULL,
    name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_staffs_login_id (login_id),
    KEY idx_staffs_active_role (is_active, role)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

INSERT INTO staffs (login_id, name, password_hash, role, is_active) VALUES
    ('admin', '管理者', '$2y$10$I2bs2ljxuPGzPA.WMLLbLOt5EGkZUxD7gmtW9uFGD2/VYOt6yfap6', 'admin', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    role = VALUES(role),
    is_active = VALUES(is_active);
