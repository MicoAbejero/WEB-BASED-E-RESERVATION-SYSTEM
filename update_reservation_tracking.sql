-- =====================================================
-- UPDATE SCRIPT: Add reservation status tracking
-- This script adds columns to track who updated reservation status
-- =====================================================

USE e_reserve_db;

-- Add columns to track status updates
ALTER TABLE reservations 
    ADD COLUMN IF NOT EXISTS last_updated_by INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS last_updated_by_role ENUM('admin', 'customer') DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS last_updated_at TIMESTAMP NULL DEFAULT NULL;

SELECT '✅ Reservation status tracking columns added successfully!' AS status;
