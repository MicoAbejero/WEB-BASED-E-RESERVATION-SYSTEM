-- =====================================================
-- FIX RESERVATION TABLE COLUMNS - COMPLETE UPDATE
-- =====================================================
-- Run this script in phpMyAdmin MySQL console
-- This fixes: pickup_date, total_amount columns that are missing data

USE e_reserve_db;

-- 1. First fix pickup_date column properly
ALTER TABLE reservations MODIFY COLUMN pickup_date DATE NULL DEFAULT NULL;

-- 2. Fix total_amount column (ensure it allows NULL and has proper default)
ALTER TABLE reservations MODIFY COLUMN total_amount DECIMAL(10,2) NULL DEFAULT NULL;

-- 3. UPDATE ALL EXISTING RESERVATIONS THAT HAVE MISSING DATA:

-- Fix pickup_date: set to reservation_date + 3 days if empty/zero
UPDATE reservations 
SET pickup_date = DATE_ADD(reservation_date, INTERVAL 3 DAY)
WHERE pickup_date IS NULL 
   OR pickup_date = '0000-00-00'
   OR pickup_date = '';

-- Fix total_amount: calculate from product price * quantity if missing
UPDATE reservations r
JOIN products p ON r.product_id = p.id
SET r.total_amount = p.price * r.quantity
WHERE r.total_amount IS NULL 
   OR r.total_amount = 0
   OR r.total_amount = '';

-- 4. Add missing tracking columns that were not created initially
ALTER TABLE reservations 
ADD COLUMN IF NOT EXISTS tracking_number VARCHAR(100) NULL DEFAULT NULL AFTER total_amount,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- 5. VERIFICATION CHECKS
SELECT 'RESERVATIONS TABLE STATUS AFTER FIX:' as message;

SELECT 
  COUNT(*) as total_reservations,
  SUM(CASE WHEN pickup_date IS NULL OR pickup_date = '0000-00-00' THEN 1 ELSE 0 END) as empty_pickup_date,
  SUM(CASE WHEN total_amount IS NULL OR total_amount = 0 THEN 1 ELSE 0 END) as empty_total_amount
FROM reservations;

-- Show sample fixed records
SELECT id, user_id, product_id, quantity, reservation_date, pickup_date, total_amount, status
FROM reservations 
ORDER BY id DESC 
LIMIT 10;

SELECT '✅ Database reservation columns fixed successfully!' as final_status;