-- =====================================================
-- SETTINGS TABLE UPDATE FOR E-RESERVE CROCHET
-- Run this file to add the settings and date_settings tables
-- =====================================================

-- Create settings table if it doesn't exist
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings (will be ignored if already exists)
INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES 
('max_reservations_per_day', '5', 'Maximum number of reservations allowed per pickup day');

-- Create date_settings table for per-date limits
CREATE TABLE IF NOT EXISTS date_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pickup_date DATE UNIQUE NOT NULL,
    max_reservations INT DEFAULT 5,
    notes TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Verify the tables were created
SELECT 'Settings table:' AS info;
SELECT * FROM settings;

SELECT 'Date Settings table (empty initially):' AS info;
SELECT * FROM date_settings;

-- =====================================================
-- SUCCESS MESSAGE
-- =====================================================
SELECT 'All settings tables created successfully!' AS status;
