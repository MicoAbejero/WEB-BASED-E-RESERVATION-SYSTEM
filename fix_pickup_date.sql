-- Fix pickup_date column for reservations table
-- Run this in phpMyAdmin or MySQL

USE e_reserve_db;

-- Check if pickup_date column exists, if not add it
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'e_reserve_db' 
    AND TABLE_NAME = 'reservations' 
    AND COLUMN_NAME = 'pickup_date'
);

-- Add pickup_date column if it doesn't exist
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE reservations ADD COLUMN pickup_date DATE DEFAULT NULL AFTER reservation_date',
    'SELECT "Column already exists" as status');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing reservations with NULL pickup_date to use reservation_date + 3 days
UPDATE reservations 
SET pickup_date = DATE_ADD(reservation_date, INTERVAL 3 DAY)
WHERE pickup_date IS NULL OR pickup_date = '0000-00-00';

-- Verify the column exists now
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'e_reserve_db' 
AND TABLE_NAME = 'reservations' 
AND COLUMN_NAME = 'pickup_date';

-- Show current reservations data to verify
SELECT id, user_id, product_id, quantity, reservation_date, pickup_date, status, total_amount 
FROM reservations 
LIMIT 10;

SELECT CONCAT('✅ Fixed ', COUNT(*), ' reservations with pickup dates!') AS status
FROM reservations 
WHERE pickup_date IS NOT NULL AND pickup_date != '0000-00-00';
