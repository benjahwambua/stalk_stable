-- Stalk & Stable: Supply Chain + M-PESA integration
-- Run this once in phpMyAdmin against stalk_stable_db.

CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(60) NOT NULL UNIQUE,
    supplier_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    po_date DATETIME NOT NULL,
    expected_date DATE NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('Draft','Approved','Partially Received','Fully Received','Cancelled') NOT NULL DEFAULT 'Draft',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_po_supplier (supplier_id),
    INDEX idx_po_status (status),
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_po_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    ordered_quantity INT UNSIGNED NOT NULL,
    received_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax DECIMAL(14,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    INDEX idx_poi_po (purchase_order_id),
    INDEX idx_poi_product (product_id),
    CONSTRAINT fk_poi_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_poi_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goods_receipts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grn_number VARCHAR(60) NOT NULL UNIQUE,
    purchase_order_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    received_date DATETIME NOT NULL,
    supplier_invoice VARCHAR(100) NULL,
    delivery_note VARCHAR(100) NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_grn_po (purchase_order_id),
    INDEX idx_grn_supplier (supplier_id),
    CONSTRAINT fk_grn_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
    CONSTRAINT fk_grn_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_grn_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goods_receipt_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    goods_receipt_id INT UNSIGNED NOT NULL,
    purchase_order_item_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity_received INT UNSIGNED NOT NULL,
    unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    INDEX idx_gri_grn (goods_receipt_id),
    CONSTRAINT fk_gri_grn FOREIGN KEY (goods_receipt_id) REFERENCES goods_receipts(id) ON DELETE CASCADE,
    CONSTRAINT fk_gri_poi FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items(id),
    CONSTRAINT fk_gri_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS supplier_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(60) NOT NULL UNIQUE,
    supplier_id INT UNSIGNED NOT NULL,
    goods_receipt_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_method ENUM('Cash','M-Pesa','Bank','Cheque','Other') NOT NULL DEFAULT 'M-Pesa',
    transaction_reference VARCHAR(100) NULL,
    payment_date DATETIME NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sp_supplier (supplier_id),
    CONSTRAINT fk_sp_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_sp_grn FOREIGN KEY (goods_receipt_id) REFERENCES goods_receipts(id) ON DELETE SET NULL,
    CONSTRAINT fk_sp_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mpesa_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_type ENUM('STK Push','C2B','B2C','B2B','Reversal') NOT NULL DEFAULT 'STK Push',
    direction ENUM('Incoming','Outgoing') NOT NULL DEFAULT 'Incoming',
    phone_number VARCHAR(20) NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    account_reference VARCHAR(100) NULL,
    transaction_reference VARCHAR(100) NULL,
    merchant_request_id VARCHAR(100) NULL,
    checkout_request_id VARCHAR(100) NULL,
    result_code VARCHAR(20) NULL,
    result_description VARCHAR(255) NULL,
    result_status ENUM('Pending','Success','Failed','Cancelled') NOT NULL DEFAULT 'Pending',
    reference_type VARCHAR(50) NULL,
    reference_id INT UNSIGNED NULL,
    raw_response LONGTEXT NULL,
    transaction_date DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mpesa_checkout (checkout_request_id),
    INDEX idx_mpesa_reference (reference_type, reference_id),
    INDEX idx_mpesa_status (result_status),
    INDEX idx_mpesa_transaction (transaction_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (setting_key, setting_value)
SELECT 'mpesa_environment','sandbox' WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='mpesa_environment');
INSERT INTO settings (setting_key, setting_value)
SELECT 'mpesa_shortcode','' WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='mpesa_shortcode');
INSERT INTO settings (setting_key, setting_value)
SELECT 'mpesa_passkey','' WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='mpesa_passkey');
INSERT INTO settings (setting_key, setting_value)
SELECT 'mpesa_consumer_key','' WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='mpesa_consumer_key');
INSERT INTO settings (setting_key, setting_value)
SELECT 'mpesa_consumer_secret','' WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='mpesa_consumer_secret');
INSERT INTO settings (setting_key, setting_value)
SELECT 'mpesa_callback_url','' WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='mpesa_callback_url');
INSERT INTO settings (setting_key, setting_value)
SELECT 'mpesa_account_reference','STALKSTABLE' WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='mpesa_account_reference');
