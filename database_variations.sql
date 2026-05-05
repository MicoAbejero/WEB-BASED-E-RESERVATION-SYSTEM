-- =====================================================
-- DATABASE UPDATE: Product Variations
-- This script adds product variation functionality
-- =====================================================

USE e_reserve_db;

-- Create product variations table
CREATE TABLE IF NOT EXISTS product_variations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    variation_name VARCHAR(100) NOT NULL,
    variation_label VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Insert sample variations for existing products
INSERT INTO product_variations (product_id, variation_name, variation_label, price, is_default)
SELECT id, '1pc', '1 Piece', price, TRUE FROM products
WHERE id NOT IN (SELECT DISTINCT product_id FROM product_variations);

INSERT INTO product_variations (product_id, variation_name, variation_label, price, is_default)
SELECT id, '3pcs', '3 Pieces', price * 2.5, FALSE FROM products
WHERE id NOT IN (SELECT DISTINCT product_id FROM product_variations WHERE variation_name = '3pcs');

INSERT INTO product_variations (product_id, variation_name, variation_label, price, is_default)
SELECT id, '5pcs', '5 Pieces', price * 4, FALSE FROM products
WHERE id NOT IN (SELECT DISTINCT product_id FROM product_variations WHERE variation_name = '5pcs');

-- Update reservations table to track variation
ALTER TABLE reservations ADD COLUMN IF NOT EXISTS variation_id INT DEFAULT NULL;
ALTER TABLE reservations ADD COLUMN IF NOT EXISTS variation_name VARCHAR(100) DEFAULT NULL;
ALTER TABLE reservations ADD COLUMN IF NOT EXISTS unit_price DECIMAL(10, 2) DEFAULT NULL;

-- Update cart table to track variation
ALTER TABLE cart ADD COLUMN IF NOT EXISTS variation_id INT DEFAULT NULL;
ALTER TABLE cart ADD COLUMN IF NOT EXISTS variation_name VARCHAR(100) DEFAULT NULL;

SELECT '✅ Product variations database update completed!' AS status;
