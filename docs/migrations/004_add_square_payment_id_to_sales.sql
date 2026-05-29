-- Square 決済 ID を sales テーブルに追加
ALTER TABLE sales
    ADD COLUMN square_payment_id VARCHAR(255) NULL AFTER payment_method;
