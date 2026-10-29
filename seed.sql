-- ============================================================
-- Kopipiro — demo data
-- Passwords are bcrypt hashes of:
--   owner    / owner123
--   kasir1   / kasir123
--   kasir2   / kasir123
--   budi     / budi123
--   sari     / sari123
-- Import after database.sql:  mysql -uroot < seed.sql
-- ============================================================

USE db_kopipiro;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE reviews;
TRUNCATE TABLE order_items;
TRUNCATE TABLE orders;
TRUNCATE TABLE products;
TRUNCATE TABLE categories;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- users
-- ------------------------------------------------------------
INSERT INTO users (id, username, password, role) VALUES
(1, 'owner',  '$2y$10$VZEdr/2FooS4aM3DyYG0TOjGf/93PPeh/seBm4Pk.mxWthsX3cSFi', 'owner'),
(2, 'kasir1', '$2y$10$kfxBAzdanXKIa4rKU1sgCu9K0QezqGRFEE5KkvQllMFl9AEOcwoOa', 'kasir'),
(3, 'kasir2', '$2y$10$kfxBAzdanXKIa4rKU1sgCu9K0QezqGRFEE5KkvQllMFl9AEOcwoOa', 'kasir'),
(4, 'budi',   '$2y$10$a8MquMjtxOVDw.aK7N45guUDJwFtWcJl9jX/Xckl0z34jPHM8nl5K', 'customer'),
(5, 'sari',   '$2y$10$TDM58fdDDRLKac46shgpJutJzzu3.bvqTJxHE7g.gXWemeBEfX2uC', 'customer');

-- ------------------------------------------------------------
-- categories
-- ------------------------------------------------------------
INSERT INTO categories (id, name) VALUES
(1, 'Kopi'),
(2, 'Non-Kopi'),
(3, 'Toast & Snack');

-- ------------------------------------------------------------
-- products (10 demo items, local SVG placeholders)
-- ------------------------------------------------------------
INSERT INTO products (id, category_id, name, description, price, url_image) VALUES
(1, 1, 'Espresso', 'Single shot espresso dari biji arabika Gayo, pekat dan beraroma cokelat gelap.', 18000, 'assets/img/kopi.svg'),
(2, 1, 'Americano', 'Espresso dengan air panas, ringan dan pahit bersih. Cocok untuk yang tidak suka susu.', 22000, 'assets/img/kopi.svg'),
(3, 1, 'Kopi Susu Gula Aren', 'Espresso, susu segar, dan gula aren asli. Menu paling laris di Kopipiro.', 25000, 'assets/img/kopi.svg'),
(4, 1, 'Cappuccino', 'Espresso dengan susu steamed dan foam tebal, ditaburi bubuk kayu manis.', 28000, 'assets/img/kopi.svg'),
(5, 1, 'Caffè Latte', 'Espresso dengan susu steamed lembut, rasio susu lebih banyak dari cappuccino.', 28000, 'assets/img/kopi.svg'),
(6, 2, 'Matcha Latte', 'Matcha Jepang grade premium dengan susu segar. Manisnya bisa request less sugar.', 30000, 'assets/img/non-kopi.svg'),
(7, 2, 'Chocolate', 'Cokelat bubuk Belgia dengan susu panas, creamy dan tidak terlalu manis.', 26000, 'assets/img/non-kopi.svg'),
(8, 2, 'Teh Lemon', 'Teh hitam dingin dengan perasan lemon segar dan es batu.', 18000, 'assets/img/non-kopi.svg'),
(9, 3, 'Toast Cokelat Keju', 'Roti tawar panggang dengan cokelat lumer dan keju cheddar parut.', 20000, 'assets/img/toast.svg'),
(10, 3, 'Croissant Butter', 'Croissant panggang dengan mentega Prancis, renyah di luar lembut di dalam.', 22000, 'assets/img/toast.svg');

-- ------------------------------------------------------------
-- demo orders spread over the last week (so the owner chart is not empty)
--   completed orders count as revenue
-- ------------------------------------------------------------
INSERT INTO orders
  (id, order_code, user_id, customer_name, customer_phone, cashier_id,
   subtotal, discount, total, status, created_at) VALUES
(1, 'KP-DEMO-001', 4,    'Budi Santoso',  '081234567801', 2,
 74000.00,  7400.00, 66600.00, 'completed',  DATE_SUB(NOW(), INTERVAL 6 DAY)),
(2, 'KP-DEMO-002', NULL, 'Rina (guest)',  '081234567802', 2,
 54000.00,     0.00, 54000.00, 'completed',  DATE_SUB(NOW(), INTERVAL 4 DAY)),
(3, 'KP-DEMO-003', NULL, 'Agus (guest)',  '081234567803', 3,
 45000.00,     0.00, 45000.00, 'cancelled',  DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4, 'KP-DEMO-004', 4,    'Budi Santoso',  '081234567801', 2,
 70000.00,  7000.00, 63000.00, 'completed',  DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 'KP-DEMO-005', NULL, 'Dewi (guest)',  '081234567805', 3,
 44000.00,     0.00, 44000.00, 'completed',  DATE_SUB(NOW(), INTERVAL 1 DAY)),
(6, 'KP-DEMO-006', 5,    'Sari Wulandari','081234567806', NULL,
 56000.00,  5600.00, 50400.00, 'pending',    NOW());

INSERT INTO order_items
  (order_id, product_id, product_name, price, qty, line_total) VALUES
-- order 1
(1,  4, 'Cappuccino',           28000.00, 1, 28000.00),
(1,  5, 'Caffè Latte',          28000.00, 1, 28000.00),
(1,  8, 'Teh Lemon',            18000.00, 1, 18000.00),
-- order 2
(2,  1, 'Espresso',             18000.00, 3, 54000.00),
-- order 3 (cancelled)
(3,  3, 'Kopi Susu Gula Aren',  25000.00, 1, 25000.00),
(3,  9, 'Toast Cokelat Keju',   20000.00, 1, 20000.00),
-- order 4
(4,  3, 'Kopi Susu Gula Aren',  25000.00, 2, 50000.00),
(4,  9, 'Toast Cokelat Keju',   20000.00, 1, 20000.00),
-- order 5
(5,  2, 'Americano',            22000.00, 1, 22000.00),
(5, 10, 'Croissant Butter',     22000.00, 1, 22000.00),
-- order 6 (pending, unclaimed)
(6,  6, 'Matcha Latte',         30000.00, 1, 30000.00),
(6,  7, 'Chocolate',            26000.00, 1, 26000.00);

-- ------------------------------------------------------------
-- reviews (guests cannot review — they have no account)
-- ------------------------------------------------------------
INSERT INTO reviews (product_id, user_id, rating, comment) VALUES
(3, 4, 5, 'Kopi susu gula arennya pas, tidak terlalu manis. Wajib coba.'),
(4, 4, 5, 'Foam-nya tebal dan aromanya enak.'),
(6, 5, 4, 'Matcha-nya legit, tapi menurutku masih agak manis.'),
(9, 5, 4, 'Toast-nya masih hangat waktu sampai, kejunya banyak.');
