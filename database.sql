-- ============================================================
-- Kopipiro — database schema
-- MySQL 8.x / MariaDB 10.4+
-- Import:  mysql -uroot < database.sql
--          mysql -uroot < seed.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS db_kopipiro
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE db_kopipiro;

-- Drop in FK-safe order so the file can be re-imported.
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- ------------------------------------------------------------
-- users: one table, three roles. 'owner' replaces the old 'admin'.
-- ------------------------------------------------------------
CREATE TABLE users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,               -- password_hash(), never plain text
  role       ENUM('owner','kasir','customer') NOT NULL DEFAULT 'customer',
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- categories: 'Kopi', 'Non-Kopi', 'Toast & Snack', ...
-- ------------------------------------------------------------
CREATE TABLE categories (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- products
--   price is DECIMAL so money never goes through float.
--   rating is NOT stored: it is AVG(reviews.rating) computed on read.
--   is_active = 0 is a soft delete, so old orders keep their FK.
-- ------------------------------------------------------------
CREATE TABLE products (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name        VARCHAR(100)  NOT NULL,
  description TEXT NULL,
  price       DECIMAL(10,2) NOT NULL,
  url_image   VARCHAR(255) NULL,                  -- relative path, e.g. uploads/abc.jpg
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category
    FOREIGN KEY (category_id) REFERENCES categories(id),
  INDEX idx_products_category (category_id),
  INDEX idx_products_active (is_active)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- orders
--   user_id NULL  = guest checkout (normal price, no discount)
--   cashier_id NULL until a kasir claims the order
--   subtotal/discount/total are snapshots of what was charged.
-- ------------------------------------------------------------
CREATE TABLE orders (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  order_code     VARCHAR(20) NOT NULL UNIQUE,
  user_id        INT NULL,
  customer_name  VARCHAR(100) NOT NULL,
  customer_phone VARCHAR(20)  NULL,
  cashier_id     INT NULL,
  subtotal       DECIMAL(10,2) NOT NULL,
  discount       DECIMAL(10,2) NOT NULL DEFAULT 0,
  total          DECIMAL(10,2) NOT NULL,
  status         ENUM('pending','processing','completed','cancelled')
                 NOT NULL DEFAULT 'pending',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user
    FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_orders_cashier
    FOREIGN KEY (cashier_id) REFERENCES users(id),
  INDEX idx_orders_cashier (cashier_id),
  INDEX idx_orders_status (status),
  INDEX idx_orders_created (created_at)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- order_items: product_name and price are snapshots.
-- Editing a product later must not rewrite yesterday's receipt.
-- ------------------------------------------------------------
CREATE TABLE order_items (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  order_id     INT NOT NULL,
  product_id   INT NOT NULL,
  product_name VARCHAR(100)  NOT NULL,
  price        DECIMAL(10,2) NOT NULL,
  qty          INT NOT NULL,
  line_total   DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_items_order
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product
    FOREIGN KEY (product_id) REFERENCES products(id),
  INDEX idx_items_order (order_id),
  INDEX idx_items_product (product_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- reviews: one rating per user per product, no purchase required.
-- ------------------------------------------------------------
CREATE TABLE reviews (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id    INT NOT NULL,
  rating     TINYINT NOT NULL,
  comment    TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT fk_reviews_product
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_review_user_product (user_id, product_id),
  INDEX idx_reviews_product (product_id)
) ENGINE=InnoDB;
