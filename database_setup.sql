-- =====================================================
-- E-RESERVE FOR CROCHET FLOWERS - DATABASE SETUP
-- =====================================================

-- Create database
CREATE DATABASE IF NOT EXISTS e_reserve_db;
USE e_reserve_db;

-- =====================================================
-- USERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('admin', 'customer') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- PRODUCTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image VARCHAR(255),
    category VARCHAR(50),
    stock INT DEFAULT 0,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- RESERVATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    reservation_date DATE NOT NULL,
    pickup_date DATE,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    total_amount DECIMAL(10, 2),
    notes TEXT,
    cancellation_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- =====================================================
-- CART TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- =====================================================
-- SETTINGS TABLE (For system configuration)
-- =====================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- INSERT DEFAULT ADMIN USER
-- Password: admin123
-- =====================================================
INSERT INTO users (name, email, password, role) VALUES 
('Admin', 'admin@crochet.com', 'admin123', 'admin');

-- =====================================================
-- INSERT DEFAULT SETTINGS
-- =====================================================
INSERT INTO settings (setting_key, setting_value, description) VALUES 
('max_reservations_per_day', '5', 'Maximum number of reservations allowed per pickup day');

-- =====================================================
-- INSERT SAMPLE PRODUCTS
-- =====================================================
INSERT INTO products (name, description, price, category, stock, is_available) VALUES 
('Rose Bouquet', 'Beautiful handmade crochet rose bouquet in red', 250.00, 'Bouquets', 20, TRUE),
('Sunflower Arrangement', 'Bright and cheerful crochet sunflower arrangement', 300.00, 'Arrangements', 15, TRUE),
('Mixed Flower Vase', 'Colorful mixed crochet flowers in a decorative vase', 450.00, 'Vases', 10, TRUE),
('Lily Corsage', 'Elegant crochet lily corsage for special occasions', 180.00, 'Corsages', 25, TRUE),
('Tulip Basket', 'Lovely crochet tulip basket perfect for gifts', 350.00, 'Baskets', 12, TRUE),
('Daisy Chain', 'Delicate handmade crochet daisy chain decoration', 150.00, 'Decorations', 30, TRUE),
('Orchid Plant', 'Realistic crochet orchid plant in a pot', 400.00, 'Plants', 8, TRUE),
('Wedding Bouquet', 'Special wedding crochet bouquet with pearl details', 800.00, 'Special Occasions', 5, TRUE);

-- =====================================================
-- DISPLAY SUCCESS MESSAGE
-- =====================================================
SELECT 'Database setup completed successfully!' AS status;
