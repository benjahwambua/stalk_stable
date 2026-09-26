-- Stalk & Stable price lists
CREATE TABLE IF NOT EXISTS price_lists (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL UNIQUE,
 code VARCHAR(40) NOT NULL UNIQUE,
 description VARCHAR(255) NULL,
 is_default TINYINT(1) NOT NULL DEFAULT 0,
 status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_prices (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 price_list_id INT UNSIGNED NOT NULL,
 product_id INT UNSIGNED NOT NULL,
 min_quantity INT UNSIGNED NOT NULL DEFAULT 1,
 price DECIMAL(14,2) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_product_price_tier (price_list_id, product_id, min_quantity),
 INDEX idx_pp_product (product_id),
 CONSTRAINT fk_pp_list FOREIGN KEY (price_list_id) REFERENCES price_lists(id) ON DELETE CASCADE,
 CONSTRAINT fk_pp_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @has_customer_price_list := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='price_list_id');
SET @sql_customer_price_list := IF(@has_customer_price_list=0,'ALTER TABLE customers ADD COLUMN price_list_id INT UNSIGNED NULL AFTER credit_limit','SELECT 1');
PREPARE stmt_customer_price_list FROM @sql_customer_price_list;
EXECUTE stmt_customer_price_list;
DEALLOCATE PREPARE stmt_customer_price_list;

SET @has_fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='price_list_id' AND REFERENCED_TABLE_NAME='price_lists');
SET @sql_customer_fk := IF(@has_fk=0,'ALTER TABLE customers ADD INDEX idx_customer_price_list (price_list_id), ADD CONSTRAINT fk_customer_price_list FOREIGN KEY (price_list_id) REFERENCES price_lists(id) ON DELETE SET NULL','SELECT 1');
PREPARE stmt_customer_fk FROM @sql_customer_fk;
EXECUTE stmt_customer_fk;
DEALLOCATE PREPARE stmt_customer_fk;

INSERT INTO price_lists(name,code,description,is_default,status)
SELECT 'Normal Price','normal','Standard retail/customer price',1,'Active'
WHERE NOT EXISTS (SELECT 1 FROM price_lists WHERE code='normal');

INSERT INTO price_lists(name,code,description,is_default,status)
SELECT 'Wholesale Price','wholesale','Wholesale customer pricing',0,'Active'
WHERE NOT EXISTS (SELECT 1 FROM price_lists WHERE code='wholesale');

INSERT INTO product_prices(price_list_id,product_id,min_quantity,price)
SELECT pl.id,p.id,1,p.selling_price
FROM price_lists pl CROSS JOIN products p
WHERE pl.code='normal'
AND NOT EXISTS (SELECT 1 FROM product_prices pp WHERE pp.price_list_id=pl.id AND pp.product_id=p.id AND pp.min_quantity=1);

INSERT INTO product_prices(price_list_id,product_id,min_quantity,price)
SELECT pl.id,p.id,1,p.wholesale_price
FROM price_lists pl CROSS JOIN products p
WHERE pl.code='wholesale' AND p.wholesale_price>0
AND NOT EXISTS (SELECT 1 FROM product_prices pp WHERE pp.price_list_id=pl.id AND pp.product_id=p.id AND pp.min_quantity=1);
