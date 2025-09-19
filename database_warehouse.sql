-- Multi-Store Warehouse and POS Management System Database Schema

-- Drop tables if they exist (in reverse order of dependencies)
DROP TABLE IF EXISTS invoice_items;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS returns;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS store_inventory;
DROP TABLE IF EXISTS inventory_transactions;
DROP TABLE IF EXISTS purchase_order_items;
DROP TABLE IF EXISTS purchase_orders;
DROP TABLE IF EXISTS container_items;
DROP TABLE IF EXISTS containers;
DROP TABLE IF EXISTS barcodes;
DROP TABLE IF EXISTS inventory_items;
DROP TABLE IF EXISTS subcategories;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS stores;
DROP TABLE IF EXISTS suppliers;

-- Stores table
CREATE TABLE stores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    store_code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    manager_id INT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample stores
INSERT INTO stores (store_code, name, address, phone) VALUES
('MAIN', 'Main Warehouse', 'Main Warehouse Address', '+1-555-100-0001'),
('STORE01', 'Downtown Store', '123 Downtown Street', '+1-555-100-0002'),
('STORE02', 'Mall Location', '456 Shopping Mall', '+1-555-100-0003');

-- Users table with role-based access
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    full_name VARCHAR(100),
    role ENUM('admin', 'store_manager', 'sales_person', 'inventory_manager', 'transfer_manager') NOT NULL,
    store_id INT,
    manager_password VARCHAR(255), -- For return operations
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL
);

-- Insert default users
INSERT INTO users (username, password, email, full_name, role, store_id, manager_password) VALUES 
('admin', 'admin123', 'admin@example.com', 'System Administrator', 'admin', 1, 'manager123'),
('manager1', 'manager123', 'manager1@example.com', 'Store Manager 1', 'store_manager', 2, 'manager123'),
('sales1', 'sales123', 'sales1@example.com', 'Sales Person 1', 'sales_person', 2, NULL),
('manager2', 'manager123', 'manager2@example.com', 'Store Manager 2', 'store_manager', 3, 'manager123'),
('sales2', 'sales123', 'sales2@example.com', 'Sales Person 2', 'sales_person', 3, NULL);

-- Update stores table to set manager references
UPDATE stores SET manager_id = 2 WHERE id = 2;
UPDATE stores SET manager_id = 4 WHERE id = 3;

-- Suppliers table
CREATE TABLE suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample suppliers
INSERT INTO suppliers (name, contact_person, email, phone, address) VALUES
('Clothing Supplier A', 'John Smith', 'john@suppliera.com', '+1-555-200-0001', '123 Supplier Street'),
('Fashion Wholesale B', 'Mary Johnson', 'mary@fashionb.com', '+1-555-200-0002', '456 Wholesale Ave'),
('Textile Import C', 'Robert Brown', 'robert@textilec.com', '+1-555-200-0003', '789 Import Blvd');

-- Categories for clothing items
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert clothing categories
INSERT INTO categories (name, description) VALUES
('Men Clothing', 'Men clothing items'),
('Women Clothing', 'Women clothing items'),
('Children Clothing', 'Children clothing items'),
('Accessories', 'Clothing accessories'),
('Shoes', 'All types of shoes');

-- Subcategories for detailed classification
CREATE TABLE subcategories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Insert sample subcategories
INSERT INTO subcategories (category_id, name, description) VALUES
-- Men Clothing
(1, 'Shirts', 'Men shirts'),
(1, 'Pants', 'Men pants'),
(1, 'Jackets', 'Men jackets'),
(1, 'T-Shirts', 'Men t-shirts'),
-- Women Clothing
(2, 'Dresses', 'Women dresses'),
(2, 'Blouses', 'Women blouses'),
(2, 'Skirts', 'Women skirts'),
(2, 'Pants', 'Women pants'),
-- Children Clothing
(3, 'Boys Clothes', 'Boys clothing'),
(3, 'Girls Clothes', 'Girls clothing'),
(3, 'Baby Clothes', 'Baby clothing'),
-- Accessories
(4, 'Belts', 'Belts and accessories'),
(4, 'Bags', 'Bags and purses'),
(4, 'Jewelry', 'Jewelry items'),
-- Shoes
(5, 'Men Shoes', 'Men footwear'),
(5, 'Women Shoes', 'Women footwear'),
(5, 'Children Shoes', 'Children footwear');

-- Inventory items (master product catalog)
CREATE TABLE inventory_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT,
    subcategory_id INT,
    base_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    size VARCHAR(20),
    color VARCHAR(50),
    material VARCHAR(100),
    brand VARCHAR(100),
    image_path VARCHAR(255),
    status ENUM('active', 'discontinued') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE SET NULL
);

-- Barcode management
CREATE TABLE barcodes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    barcode VARCHAR(50) UNIQUE NOT NULL,
    item_id INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    is_shared BOOLEAN DEFAULT FALSE, -- TRUE for items with same price (1000 XFA)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    INDEX idx_barcode (barcode),
    INDEX idx_price_shared (price, is_shared)
);

-- Containers (incoming shipments)
CREATE TABLE containers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    container_number VARCHAR(50) UNIQUE NOT NULL,
    supplier_id INT NOT NULL,
    total_weight_kg DECIMAL(10, 2) NOT NULL,
    price_per_kg DECIMAL(10, 2) NOT NULL,
    total_cost DECIMAL(12, 2) NOT NULL,
    amount_paid DECIMAL(12, 2) DEFAULT 0.00,
    remaining_balance DECIMAL(12, 2) NOT NULL,
    arrival_date DATE,
    status ENUM('pending', 'received', 'processed', 'completed') DEFAULT 'pending',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Container items (what was found in each container)
CREATE TABLE container_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    container_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(10, 2) NOT NULL,
    total_cost DECIMAL(10, 2) NOT NULL,
    selling_price DECIMAL(10, 2) NOT NULL,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
);

-- Store-specific inventory
CREATE TABLE store_inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    store_id INT NOT NULL,
    item_id INT NOT NULL,
    barcode_id INT NOT NULL,
    current_stock INT DEFAULT 0,
    minimum_stock INT DEFAULT 0,
    selling_price DECIMAL(10, 2) NOT NULL,
    cost_price DECIMAL(10, 2) NOT NULL,
    location_in_store VARCHAR(100),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (barcode_id) REFERENCES barcodes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_store_item_barcode (store_id, item_id, barcode_id)
);

-- Inventory transactions for tracking stock movements
CREATE TABLE inventory_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    store_id INT NOT NULL,
    item_id INT NOT NULL,
    barcode_id INT NOT NULL,
    transaction_type ENUM('in', 'out', 'adjustment', 'transfer') NOT NULL,
    quantity INT NOT NULL,
    reference_type ENUM('container', 'sale', 'return', 'adjustment', 'transfer') NOT NULL,
    reference_id INT,
    unit_price DECIMAL(10, 2),
    total_amount DECIMAL(10, 2),
    notes TEXT,
    user_id INT,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (barcode_id) REFERENCES barcodes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Purchase orders (now for container orders)
CREATE TABLE purchase_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    po_number VARCHAR(50) UNIQUE NOT NULL,
    supplier_id INT NOT NULL,
    container_id INT,
    order_date DATE,
    expected_delivery_date DATE,
    status ENUM('draft', 'pending', 'approved', 'received', 'cancelled') DEFAULT 'draft',
    total_amount DECIMAL(12, 2) DEFAULT 0.00,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Purchase order items
CREATE TABLE purchase_order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    purchase_order_id INT NOT NULL,
    description TEXT NOT NULL,
    expected_weight_kg DECIMAL(10, 2),
    unit_price DECIMAL(10, 2) DEFAULT 0.00,
    total_price DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
);

-- Invoices (POS transactions)
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    store_id INT NOT NULL,
    customer_name VARCHAR(100),
    customer_phone VARCHAR(20),
    subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('cash', 'card', 'mobile', 'credit') DEFAULT 'cash',
    payment_status ENUM('pending', 'paid', 'partial', 'refunded') DEFAULT 'pending',
    status ENUM('draft', 'completed', 'cancelled', 'returned') DEFAULT 'draft',
    sales_person_id INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (sales_person_id) REFERENCES users(id) ON DELETE RESTRICT
);

-- Invoice items
CREATE TABLE invoice_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    item_id INT NOT NULL,
    barcode_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    discount_amount DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (barcode_id) REFERENCES barcodes(id) ON DELETE CASCADE
);

-- Returns management
CREATE TABLE returns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    return_number VARCHAR(50) UNIQUE NOT NULL,
    original_invoice_id INT NOT NULL,
    store_id INT NOT NULL,
    return_reason TEXT,
    total_amount DECIMAL(10, 2) NOT NULL,
    return_type ENUM('full', 'partial') DEFAULT 'partial',
    status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending',
    processed_by INT, -- Manager who approved
    return_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_date TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (original_invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Return items
CREATE TABLE return_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    return_id INT NOT NULL,
    invoice_item_id INT NOT NULL,
    item_id INT NOT NULL,
    barcode_id INT NOT NULL,
    quantity_returned INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_refund DECIMAL(10, 2) NOT NULL,
    condition_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_item_id) REFERENCES invoice_items(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (barcode_id) REFERENCES barcodes(id) ON DELETE CASCADE
);

-- Expenses tracking for store managers
CREATE TABLE expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    expense_number VARCHAR(50) UNIQUE NOT NULL,
    store_id INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    expense_date DATE NOT NULL,
    receipt_image VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    added_by INT NOT NULL,
    approved_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Add indexes for better performance
CREATE INDEX idx_store_inventory_store ON store_inventory(store_id);
CREATE INDEX idx_store_inventory_item ON store_inventory(item_id);
CREATE INDEX idx_inventory_transactions_store ON inventory_transactions(store_id);
CREATE INDEX idx_inventory_transactions_date ON inventory_transactions(transaction_date);
CREATE INDEX idx_invoices_store ON invoices(store_id);
CREATE INDEX idx_invoices_date ON invoices(created_at);
CREATE INDEX idx_invoice_items_invoice ON invoice_items(invoice_id);
CREATE INDEX idx_returns_store ON returns(store_id);
CREATE INDEX idx_returns_date ON returns(return_date);
CREATE INDEX idx_expenses_store ON expenses(store_id);
CREATE INDEX idx_expenses_date ON expenses(expense_date);

-- Insert sample data for testing
-- Sample inventory items
INSERT INTO inventory_items (item_code, name, description, category_id, subcategory_id, base_price, size, color, brand) VALUES
('SHIRT-001', 'Men Cotton Shirt', 'High quality cotton shirt', 1, 1, 25.00, 'L', 'Blue', 'BrandA'),
('SHIRT-002', 'Men Cotton Shirt', 'High quality cotton shirt', 1, 1, 25.00, 'M', 'White', 'BrandA'),
('DRESS-001', 'Women Summer Dress', 'Light summer dress', 2, 5, 45.00, 'M', 'Red', 'BrandB'),
('SHOE-001', 'Men Casual Shoes', 'Comfortable casual shoes', 5, 15, 75.00, '42', 'Black', 'BrandC'),
('PREMIUM-001', 'Designer Jacket', 'Premium designer jacket', 1, 3, 1000.00, 'L', 'Black', 'Designer');

-- Sample barcodes
INSERT INTO barcodes (barcode, item_id, price, is_shared) VALUES
('1234567890001', 1, 30.00, FALSE),
('1234567890002', 2, 30.00, FALSE),
('1234567890003', 3, 55.00, FALSE),
('1234567890004', 4, 85.00, FALSE),
('1000XFA000001', 5, 1000.00, TRUE); -- Shared barcode for 1000 XFA items

-- Sample store inventory
INSERT INTO store_inventory (store_id, item_id, barcode_id, current_stock, minimum_stock, selling_price, cost_price, location_in_store) VALUES
(2, 1, 1, 50, 10, 30.00, 25.00, 'Section A1'),
(2, 2, 2, 30, 10, 30.00, 25.00, 'Section A1'),
(2, 3, 3, 25, 5, 55.00, 45.00, 'Section B1'),
(3, 1, 1, 40, 10, 30.00, 25.00, 'Floor 1'),
(3, 4, 4, 20, 5, 85.00, 75.00, 'Shoe Section'); 

-- Stock transfers
CREATE TABLE stock_transfers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    source VARCHAR(100) NOT NULL,
    destination VARCHAR(100) NOT NULL,
    quantity INT NOT NULL,
    transferred_by VARCHAR(100),
    transferred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
);