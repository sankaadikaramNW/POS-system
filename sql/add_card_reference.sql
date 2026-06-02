-- =============================================================
-- Card Payment Reference Migration
-- File: sql/add_card_reference.sql
-- Run once against the fashion_pos database.
-- =============================================================

-- 1. Add card_reference column to sales table
ALTER TABLE `sales`
    ADD COLUMN `card_reference` VARCHAR(4) NULL DEFAULT NULL
        COMMENT 'Last 4 digits from card machine receipt (Card payments only)'
    AFTER `payment_method`;

-- 2. Add an index to support fast search by card_reference
ALTER TABLE `sales`
    ADD INDEX `idx_card_reference` (`card_reference`);

-- Verification query (run after migration to confirm):
-- SELECT column_name, data_type, character_maximum_length, is_nullable, column_comment
-- FROM information_schema.columns
-- WHERE table_schema = 'fashion_pos' AND table_name = 'sales' AND column_name = 'card_reference';
