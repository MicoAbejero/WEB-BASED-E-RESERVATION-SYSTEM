-- =====================================================
-- DATABASE IMPROVEMENTS FOR PROFESSIONAL SYSTEM
-- =====================================================

-- 1. Add activity_log table for tracking user actions
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- 2. Add notifications table for in-app notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- 3. Add password_change_log for security audit
CREATE TABLE IF NOT EXISTS password_change_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    INDEX idx_user_id (user_id)
);

-- 4. Add indexes for better performance
-- Check if reservations table has indexes, add if not
-- ALTER TABLE reservations ADD INDEX idx_user_pickup (user_id, pickup_date);
-- ALTER TABLE reservations ADD INDEX idx_status_date (status, pickup_date);
-- ALTER TABLE cart ADD INDEX idx_user_product (user_id, product_id);

-- 5. Add address column to users if not exists
ALTER TABLE users ADD COLUMN IF NOT EXISTS address TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20);
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64);
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_expires DATETIME;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login DATETIME;

-- 6. Add product image column
ALTER TABLE products ADD COLUMN IF NOT EXISTS image VARCHAR(255) DEFAULT NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) DEFAULT 0;

-- 7. Create settings table for system configuration
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES 
('max_reservations_per_day', '5', 'Maximum number of reservations allowed per pickup day'),
('store_name', 'E-Reserve Crochet', 'Name of the store'),
('store_email', 'admin@crochet.com', 'Store contact email'),
('store_phone', '09123456789', 'Store contact phone'),
('currency', 'PHP', 'Currency code'),
('timezone', 'Asia/Manila', 'Store timezone');

-- 8. Create date_settings table for per-date availability
CREATE TABLE IF NOT EXISTS date_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pickup_date DATE UNIQUE NOT NULL,
    max_reservations INT DEFAULT 5,
    is_available TINYINT(1) DEFAULT 1,
    notes TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pickup_date (pickup_date)
);

-- 9. Add system log table
CREATE TABLE IF NOT EXISTS system_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
    message TEXT NOT NULL,
    context TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_level (level),
    INDEX idx_created_at (created_at)
);

SELECT '✅ Database improvements applied successfully!' AS status;
