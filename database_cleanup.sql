-- =====================================================
-- DATABASE CLEANUP SCRIPT
-- Remove unused tables that are not needed by the system
-- =====================================================

USE e_reserve_db;

-- 1. Drop unused tables
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS tokens;
DROP TABLE IF EXISTS email_verifications;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS product_categories;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS payments;

-- 2. Remove unused columns from reservations table that are causing issues
ALTER TABLE reservations 
DROP COLUMN IF EXISTS product_name_snapshot,
DROP COLUMN IF EXISTS product_image_snapshot,
DROP COLUMN IF EXISTS last_updated_by,
DROP COLUMN IF EXISTS last_updated_by_role,
DROP COLUMN IF EXISTS last_updated_at;

-- 3. Remove unused columns from cart table
ALTER TABLE cart 
DROP COLUMN IF EXISTS variation_id,
DROP COLUMN IF EXISTS variation_name;

-- 4. Remove product_variations table (not fully implemented / not used properly)
DROP TABLE IF EXISTS product_variations;

-- 5. Verify remaining tables
SELECT '✅ Database cleanup completed! Remaining tables:' AS status;
SHOW TABLES;

-- 6. Show final table count
SELECT COUNT(*) as total_remaining_tables FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'e_reserve_db';