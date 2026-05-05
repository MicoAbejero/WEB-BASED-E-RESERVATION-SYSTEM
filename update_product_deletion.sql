-- =====================================================
-- UPDATE SCRIPT: Change product deletion behavior
-- This script updates existing databases to prevent
-- accidental deletion of products with reservations/cart items
-- =====================================================

USE e_reserve_db;

-- Drop existing foreign keys with CASCADE
ALTER TABLE reservations DROP FOREIGN KEY reservations_ibfk_2;
ALTER TABLE cart DROP FOREIGN KEY cart_ibfk_2;

-- Add foreign keys with RESTRICT (prevents deletion if related records exist)
ALTER TABLE reservations 
    ADD CONSTRAINT fk_reservations_product 
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT;

ALTER TABLE cart 
    ADD CONSTRAINT fk_cart_product 
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT;

SELECT '✅ Product deletion protection updated successfully!' AS status;
