-- Migration: 002 - Add image_path column to products
-- Run once against the target database.

ALTER TABLE products
    ADD COLUMN image_path VARCHAR(255) NULL AFTER icon;
