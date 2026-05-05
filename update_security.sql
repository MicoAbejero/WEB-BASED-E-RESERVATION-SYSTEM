-- =====================================================
-- SECURITY UPDATE: Add address column and password reset columns
-- =====================================================

-- Add address column to users table (for profile management)
ALTER TABLE users ADD COLUMN IF NOT EXISTS address TEXT;

-- Add password reset token columns
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_expires DATETIME NULL;

-- Create date_settings table for pickup availability management
CREATE TABLE IF NOT EXISTS date_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pickup_date DATE UNIQUE NOT NULL,
    max_reservations INT DEFAULT 5,
    notes TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create settings table if not exists
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings if not exists
INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES 
('max_reservations_per_day', '5', 'Maximum number of reservations allowed per pickup day');

SELECT 'Security update completed successfully!' AS status;
