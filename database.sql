CREATE DATABASE IF NOT EXISTS db_kopipiro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_kopipiro;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('owner', 'kasir', 'customer') NOT NULL DEFAULT 'customer',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    price INT UNSIGNED NOT NULL,
    image VARCHAR(255) NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NULL,
    cashier_id INT NULL,
    customer_name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    subtotal INT UNSIGNED NOT NULL,
    discount INT UNSIGNED NOT NULL DEFAULT 0,
    total INT UNSIGNED NOT NULL,
    status ENUM('pending', 'preparing', 'ready', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_orders_cashier FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_orders_cashier_status (cashier_id, status),
    INDEX idx_orders_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(120) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price INT UNSIGNED NOT NULL,
    line_total INT UNSIGNED NOT NULL,
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_customer_product (customer_id, product_id),
    CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO categories (name, slug, sort_order) VALUES
('Kopi', 'kopi', 1),
('Non-kopi', 'non-kopi', 2),
('Toast & Snack', 'toast-snack', 3);

INSERT INTO products (category_id, name, slug, description, price, image, is_featured) VALUES
(1, 'Kopi Susu Piro', 'kopi-susu-piro', 'Espresso house blend dengan susu segar dan gula aren racikan kami.', 28000, 'assets/img/kopsupiro.jpg', 1),
(1, 'Es Kopi Karamel', 'es-kopi-karamel', 'Kopi dingin dengan sentuhan karamel lembut dan aftertaste yang hangat.', 32000, 'assets/img/karamlelatte.jpg', 1),
(1, 'Americano', 'americano', 'Double espresso dan air mineral, sederhana dengan karakter yang kuat.', 22000, 'assets/img/americano.jpg', 0),
(1, 'Cappuccino', 'cappuccino', 'Espresso seimbang dengan steamed milk dan lapisan foam yang lembut.', 30000, 'assets/img/cappucino.jpg', 0),
(2, 'Matcha Cloud', 'matcha-cloud', 'Matcha pilihan dengan susu creamy dan lapisan foam lembut.', 32000, 'assets/img/matcha.jpg', 1),
(2, 'Cokelat Hangat', 'cokelat-hangat', 'Cokelat premium dengan susu segar, cocok untuk teman bersantai.', 28000, 'assets/img/cokelat.jpg', 0),
(3, 'Toast Kaya Butter', 'toast-kaya-butter', 'Roti panggang renyah dengan selai kaya dan butter yang meleleh.', 24000, 'assets/img/toast.jpg', 1),
(3, 'Croissant Almond', 'croissant-almond', 'Croissant berlapis dengan almond panggang dan taburan gula halus.', 29000, 'assets/img/croissant.jpg', 0);

INSERT INTO users (username, name, password, role) VALUES
('owner', 'Owner Kopipiro', '$2y$10$lEFA/NlRH/35lnI6M4dU2uU7LQdx0YIcE0Olm2XlAy/v7BkZ1fZmi', 'owner'),
('kasir1', 'Kasir Satu', '$2y$10$rYzCVwjpylZJc7cXnwYiYO.TM9ZXFVjKtBZb3KpzN.2hSLPxi2aCu', 'kasir'),
('customer', 'Demo Customer', '$2y$10$YRcdRVTErpo4UPM4VdJs0OA4Jr3B4cbE5Fgj1gMPVPrOIZbKesWAi', 'customer');

INSERT INTO reviews (customer_id, product_id, rating, comment) VALUES
(3, 1, 5, 'Kopi susunya pas, tidak terlalu manis.'),
(3, 2, 4, 'Karamelnya lembut dan tidak berlebihan.'),
(3, 5, 5, 'Matcha-nya creamy, favorit banget.'),
(3, 7, 4, 'Toast hangat dan renyah.');
