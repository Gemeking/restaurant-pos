-- ============================================================
-- Restaurant POS Database Setup
-- Run this in phpMyAdmin or MySQL command line
-- Default password for ALL accounts: password
-- ============================================================

CREATE DATABASE IF NOT EXISTS restaurant_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE restaurant_pos;

-- ============================================================
-- TABLE: staff
-- ============================================================
CREATE TABLE IF NOT EXISTS staff (
    staff_id    INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50) UNIQUE NOT NULL,
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NOT NULL,
    role        ENUM('Manager','Waiter','Kitchen') NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE: menu_categories
-- ============================================================
CREATE TABLE IF NOT EXISTS menu_categories (
    category_id  INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    display_order INT DEFAULT 0
);

-- ============================================================
-- TABLE: menu_items
-- ============================================================
CREATE TABLE IF NOT EXISTS menu_items (
    menu_item_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id  INT,
    name         VARCHAR(150) NOT NULL,
    description  TEXT,
    base_price   DECIMAL(10,2) NOT NULL,
    is_available TINYINT(1) DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES menu_categories(category_id) ON DELETE SET NULL
);

-- ============================================================
-- TABLE: restaurant_tables
-- ============================================================
CREATE TABLE IF NOT EXISTS restaurant_tables (
    table_id     INT AUTO_INCREMENT PRIMARY KEY,
    table_number INT UNIQUE NOT NULL,
    capacity     INT DEFAULT 4,
    status       ENUM('Available','Active','Reserved') DEFAULT 'Available'
);

-- ============================================================
-- TABLE: orders
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
    order_id        INT AUTO_INCREMENT PRIMARY KEY,
    staff_id        INT NULL,
    table_number    INT NOT NULL,
    order_status    ENUM('Pending','Sent to Kitchen','In Progress','Ready','Paid','Cancelled') DEFAULT 'Pending',
    order_datetime  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    subtotal        DECIMAL(10,2) DEFAULT 0.00,
    tax_amount      DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    total_amount    DECIMAL(10,2) DEFAULT 0.00,
    notes           TEXT,
    source          ENUM('Staff','Customer') DEFAULT 'Staff',
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL
);

-- ============================================================
-- TABLE: order_items
-- ============================================================
CREATE TABLE IF NOT EXISTS order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id      INT NOT NULL,
    menu_item_id  INT NOT NULL,
    quantity      INT DEFAULT 1,
    price_at_sale DECIMAL(10,2) NOT NULL,
    notes         VARCHAR(255),
    item_status   ENUM('Pending','In Progress','Ready') DEFAULT 'Pending',
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(menu_item_id)
);

-- ============================================================
-- TABLE: payments
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    payment_id       INT AUTO_INCREMENT PRIMARY KEY,
    order_id         INT NOT NULL,
    method           ENUM('Cash','Card','Split-Cash','Split-Card') NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    reference_note   VARCHAR(255),
    payment_datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id)
);

-- ============================================================
-- SEED: Staff (password = "password" for all)
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- ============================================================
INSERT INTO staff (username, first_name, last_name, role, password_hash) VALUES
('manager1',  'Abebe',  'Girma',   'Manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('waiter1',   'Tigist', 'Haile',   'Waiter',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('waiter2',   'Dawit',  'Bekele',  'Waiter',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('kitchen1',  'Yonas',  'Tesfaye', 'Kitchen', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- ============================================================
-- SEED: Menu Categories
-- ============================================================
INSERT INTO menu_categories (name, display_order) VALUES
('Appetizers',         1),
('Main Course',        2),
('Burgers',            3),
('Salads',             4),
('Drinks',             5),
('Desserts',           6);

-- ============================================================
-- SEED: Menu Items (prices in ETB)
-- ============================================================
INSERT INTO menu_items (category_id, name, description, base_price) VALUES
-- Appetizers (cat 1)
(1, 'Spring Rolls',       'Crispy vegetable rolls served with sweet chili sauce',    85.00),
(1, 'Soup of the Day',    'Ask your waiter for today\'s fresh homemade soup',         65.00),
(1, 'Bruschetta',         'Toasted bread topped with tomatoes, basil and garlic',    75.00),
(1, 'Chicken Wings',      '6 pcs spiced chicken wings with dipping sauce',           120.00),

-- Main Course (cat 2)
(2, 'Grilled Chicken',    'Herb-marinated chicken breast with seasonal vegetables',  250.00),
(2, 'Beef Steak',         '250g sirloin steak, medium-rare, with fries and salad',  380.00),
(2, 'Fish & Chips',       'Beer-battered fish fillet with crispy fries and tartar',  280.00),
(2, 'Pasta Carbonara',    'Spaghetti with egg, parmesan, pancetta and black pepper', 220.00),
(2, 'Vegetarian Platter', 'Grilled seasonal veggies, hummus, pita and falafel',      180.00),
(2, 'Lamb Tibs',          'Sautéed lamb cubes with onions, jalapeño and rosemary',   320.00),

-- Burgers (cat 3)
(3, 'Classic Burger',     'Beef patty, lettuce, tomato, onion, pickles, ketchup',   180.00),
(3, 'Cheese Burger',      'Classic burger with double cheddar cheese',               200.00),
(3, 'Chicken Burger',     'Crispy chicken fillet with coleslaw and mayo',            190.00),
(3, 'BBQ Burger',         'Beef patty with BBQ sauce, bacon, and caramelized onion', 220.00),

-- Salads (cat 4)
(4, 'Caesar Salad',       'Romaine lettuce, croutons, parmesan and Caesar dressing', 130.00),
(4, 'Greek Salad',        'Tomatoes, cucumber, olives, feta cheese and oregano',    120.00),
(4, 'Garden Salad',       'Mixed greens, cherry tomatoes, carrots and vinaigrette', 100.00),

-- Drinks (cat 5)
(5, 'Water (500ml)',      'Still mineral water',                                      25.00),
(5, 'Soft Drink',         'Coca-Cola, Pepsi, 7Up or Sprite (330ml can)',              45.00),
(5, 'Fresh Juice',        'Orange, mango, avocado or mixed (freshly blended)',        65.00),
(5, 'Coffee',             'Espresso, Americano, Cappuccino or Macchiato',             55.00),
(5, 'Tea',                'Black, green, ginger or Ethiopian spiced tea',             40.00),
(5, 'Beer (Bottle)',      'St. George, Dashen, Bedele or Meta (330ml)',               90.00),

-- Desserts (cat 6)
(6, 'Chocolate Cake',     'Rich chocolate layer cake with ganache frosting',         110.00),
(6, 'Ice Cream',          'Two scoops: vanilla, chocolate or strawberry',             80.00),
(6, 'Tiramisu',           'Classic Italian dessert with mascarpone and espresso',    120.00);

-- ============================================================
-- SEED: Restaurant Tables (10 tables)
-- ============================================================
INSERT INTO restaurant_tables (table_number, capacity) VALUES
(1, 2), (2, 2), (3, 4), (4, 4), (5, 4),
(6, 4), (7, 6), (8, 6), (9, 8), (10, 10);
