-- Retail POS - database schema
-- Run this once against an empty database (see README.md for instructions).

SET NAMES utf8mb4;

CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    role          VARCHAR(20) NOT NULL DEFAULT 'staff',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(150) NOT NULL,
    category            VARCHAR(50) NOT NULL DEFAULT 'other',
    tracking_type       ENUM('bulk','serialized') NOT NULL,
    barcode             VARCHAR(64) NULL UNIQUE,
    unit_price          DECIMAL(12,2) NOT NULL DEFAULT 0,
    stock_qty           INT NOT NULL DEFAULT 0,
    low_stock_threshold INT NOT NULL DEFAULT 5,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vendors (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    phone      VARCHAR(30) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vendor_purchases (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id      INT NOT NULL,
    product_id     INT NOT NULL,
    tracking_type  ENUM('bulk','serialized') NOT NULL,
    quantity       INT NOT NULL,
    unit_price     DECIMAL(12,2) NOT NULL,
    total_amount   DECIMAL(12,2) NOT NULL,
    amount_paid    DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_due     DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_status ENUM('paid','partial','due') NOT NULL DEFAULT 'due',
    purchase_date  DATE NOT NULL,
    notes          VARCHAR(255) NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vendor_payments (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id          INT NOT NULL,
    vendor_purchase_id INT NULL,
    amount             DECIMAL(12,2) NOT NULL,
    payment_date       DATE NOT NULL,
    notes              VARCHAR(255) NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (vendor_purchase_id) REFERENCES vendor_purchases(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE serial_numbers (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    product_id         INT NOT NULL,
    serial_barcode     VARCHAR(64) NOT NULL UNIQUE,
    status             ENUM('in_stock','sold','damaged','returned','internal_use','other') NOT NULL DEFAULT 'in_stock',
    vendor_id          INT NULL,
    vendor_purchase_id INT NULL,
    purchase_price     DECIMAL(12,2) NULL,
    purchase_date      DATE NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (vendor_purchase_id) REFERENCES vendor_purchases(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    phone      VARCHAR(30) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no     VARCHAR(20) NOT NULL UNIQUE,
    customer_id    INT NULL,
    subtotal       DECIMAL(12,2) NOT NULL,
    total          DECIMAL(12,2) NOT NULL,
    amount_paid    DECIMAL(12,2) NOT NULL DEFAULT 0,
    balance_due    DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_status ENUM('paid','partial','unpaid') NOT NULL DEFAULT 'unpaid',
    created_by     INT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sale_items (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    sale_id          INT NOT NULL,
    product_id       INT NOT NULL,
    tracking_type    ENUM('bulk','serialized') NOT NULL,
    serial_number_id INT NULL,
    quantity         INT NOT NULL,
    unit_price       DECIMAL(12,2) NOT NULL,
    line_total       DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (serial_number_id) REFERENCES serial_numbers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customer_payments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    customer_id  INT NOT NULL,
    sale_id      INT NULL,
    amount       DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    notes        VARCHAR(255) NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (sale_id) REFERENCES sales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_out (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    product_id       INT NOT NULL,
    tracking_type    ENUM('bulk','serialized') NOT NULL,
    serial_number_id INT NULL,
    quantity         INT NOT NULL DEFAULT 1,
    reason           ENUM('sale','damage','return','internal_use','other') NOT NULL,
    reference_sale_id INT NULL,
    notes            VARCHAR(255) NULL,
    created_by       INT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (serial_number_id) REFERENCES serial_numbers(id),
    FOREIGN KEY (reference_sale_id) REFERENCES sales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default login: username "admin", password "admin123" (change after first login).
INSERT INTO users (username, password_hash, full_name, role) VALUES
('admin', '$2y$12$fstcuZf.auVJTwcCqt7Bz.cLjtEWZ34uAP6Jm6ewfYSn.gXvSNDka', 'Shop Admin', 'admin');

-- Sample data so the app is instantly demo-able.
INSERT INTO vendors (name, phone) VALUES
('Al-Karam Traders', '0300-1234567'),
('Tech Distributors Lahore', '0321-9876543');

INSERT INTO products (name, category, tracking_type, barcode, unit_price, stock_qty, low_stock_threshold) VALUES
('Toner 47A', 'toner', 'bulk', '8964000000011', 4500.00, 25, 5),
('Toner 12A', 'toner', 'bulk', '8964000000028', 3800.00, 3, 5),
('HP Scanjet 2020', 'scanner', 'bulk', '8964000000035', 21000.00, 6, 3);

INSERT INTO products (name, category, tracking_type, unit_price, low_stock_threshold) VALUES
('HP LaserJet Pro M126', 'printer', 'serialized', 32000.00, 3),
('Dell Inspiron 15 Laptop', 'laptop', 'serialized', 145000.00, 2);

INSERT INTO serial_numbers (product_id, serial_barcode, status, vendor_id, purchase_price, purchase_date) VALUES
(4, 'SN-HP2025-0001', 'in_stock', 2, 27000.00, CURDATE()),
(4, 'SN-HP2025-0002', 'in_stock', 2, 27000.00, CURDATE()),
(4, 'SN-HP2025-0003', 'in_stock', 2, 27000.00, CURDATE()),
(5, 'SN-DELL15-0001', 'in_stock', 2, 122000.00, CURDATE());
