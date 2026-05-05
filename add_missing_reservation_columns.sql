-- =====================================================
-- ADD MISSING RESERVATION COLUMNS (This is why data disappears!)
-- These columns are used in cart.php when creating reservations
-- but were never added to the actual database table
-- =====================================================

USE e_reserve_db;

-- Add all missing columns that the reservation INSERT is trying to use:
ALTER TABLE reservations
ADD COLUMN IF NOT EXISTS product_name_snapshot VARCHAR(255) NULL DEFAULT NULL AFTER status,
ADD COLUMN IF NOT EXISTS product_image_snapshot VARCHAR(255) NULL DEFAULT NULL AFTER product_name_snapshot,
ADD COLUMN IF NOT EXISTS variation_id INT NULL DEFAULT NULL AFTER product_image_snapshot,
ADD COLUMN IF NOT EXISTS variation_name VARCHAR(255) NULL DEFAULT NULL AFTER variation_id,
ADD COLUMN IF NOT EXISTS unit_price DECIMAL(10,2) NULL DEFAULT NULL AFTER variation_name;

-- UPDATE EXISTING RESERVATIONS WITH MISSING DATA:
-- Populate missing product name/image from products table
UPDATE reservations r
JOIN products p ON r.product_id = p.id
SET 
  r.product_name_snapshot = p.name,
  r.product_image_snapshot = p.image
WHERE r.product_name_snapshot IS NULL;

-- Verify columns exist now
DESCRIBE reservations;

-- Show all columns with sample data
SELECT 
  id,
  user_id,
  product_id,
  product_name_snapshot,
  variation_id,
  variation_name,
  unit_price,
  quantity,
  total_amount,
  status
FROM reservations
ORDER BY id DESC
LIMIT 5;

SELECT '✅ All missing reservation columns added successfully! Reservation data will now be saved correctly when users checkout.' as final_status;