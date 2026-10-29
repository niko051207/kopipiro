# Kopipiro — Project Context

> Working notes for anyone (human or AI) picking this project up.
> Read this before writing code. Update it when decisions change.

---

## 1. What this is

**Kopipiro** is a coffee-shop ordering web app built as a Week 9–10 individual case study.
Stack is deliberately simple: **PHP (procedural) + MySQL (mysqli) + Bootstrap 5**, served by Laragon.
No framework, no Composer, no build step. Students must be able to read every line.

Repo: `https://github.com/niko051207/kopipiro` — local path `C:\laragon\www\kopipiro`

---

## 2. The brief (verbatim, do not lose this)

> **Studi Kasus:**
> Seorang coffee shop owner Kopipiro membutuhkan aplikasi web pemesanan kopi. Admin menambahkan produk, unggah gambar, dan harganya. Jika pembeli register dan login untuk memesan kopi, maka dia mendapat diskon 10%. Namun jika pembeli membuka website dan memesan kopi tanpa login, maka harga kopi normal.
> Selain kopi, Kopipiro juga menjual produk lain, seperti toast, minuman non caffein, dsb.
>
> **Fitur:**
> 1. customer bisa explore produk (kopi atau non kopi). deskripsi, rating, harga dsb.
> 2. customer bisa memesan beberapa produk
> 3. di halaman checkout, muncul list produk, harga termasuk diskon
> 4. pengguna: owner, customer, kasir
> 5. kasir hanya bisa melihat order yang dia kelola
> 6. owner bisa melihat halaman semua order beserta chart penjualan dan unduh excel
>
> **Ketentuan:** free to discuss, copy beberapa kode dari eksternal. Goal: mahasiswa paham cara mengubah kebutuhan menjadi kode program.

### Requirement → implementation map

| # | Requirement | Where it lives | Status |
|---|---|---|---|
| 1 | Product browsing: coffee + non-coffee, description, rating, price | `index.php` (catalog), `product_detail.php` | partial — no category/description/rating yet |
| 2 | Order several products | `cart.php`, `cart_action.php` | missing |
| 3 | Checkout: item list + price after discount | `checkout.php`, `order_place.php` | missing |
| 4 | Roles: owner, customer, kasir | `auth.php`, `users.php` | partial — only `admin` exists |
| 5 | Kasir sees only orders they handle | `cashier/orders.php` scoped by `cashier_id` | missing |
| 6 | Owner: all orders + sales chart + Excel export | `owner/orders.php`, `owner/dashboard.php`, `owner/export_excel.php` | missing |

---

## 3. Current folder structure

```
C:\laragon\www\kopipiro\
├── .git/
├── auth.php          # session_start + is_admin() + require_admin() gate
├── config.php        # mysqli connection (localhost, root, no pass, db_kopipiro)
├── index.php         # product catalog (table) + 10% discount display for customers
├── login.php         # username/password login, password_verify, session regenerate
├── navbar.php        # dark navbar, role badge, Login/Logout, Users link for admin
├── register.php      # INCOMPLETE — a single orphan <input> fragment, no form, no handler
├── seed_admin.php    # one-off: inserts admin / admin123, tells you to delete it
├── logout.php        # MISSING (navbar links to it)
├── users.php         # MISSING (navbar links to it)
└── context.md        # this file
```

Everything is flat, no subfolders yet. That is fine for a case study, but the
number of pages required (catalog, cart, checkout, cashier, owner) will get messy —
see §7 for the proposed layout.

### Environment facts

- Laragon document root → app runs at `http://kopipiro.test` (or `localhost/kopipiro`).
- MySQL 8.4.3 data dir initialised at `C:\laragon\data\mysql`; root has no password
  (matches `config.php`). Schema and demo data are in `database.sql` / `seed.sql`.
- PHP 8.3.33 configured via `C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.ini`
  (mysqli, mbstring, fileinfo, openssl, pdo_mysql enabled). No PDO in app code — keep it
  that way; `mysqli` only, for consistency.
- Bootstrap 5.3 + its JS bundle via jsDelivr CDN. No local assets, no custom CSS file yet.

---

## 4. Database: what exists vs. what is needed

### Inferred existing tables

```sql
users   (id, username, password, role)      -- role currently 'admin'
product (id, name, price, url_image)
```

### Problems with the current schema

1. `role` has no constraint and only `admin` is seeded — the brief needs `owner`, `kasir`, `customer`.
2. `product` has no `category`, `description`, `rating`, `stock`, `is_active`, or `created_at`.
   Requirement 1 cannot be met with the current columns.
3. No orders at all. No cart persistence. No rating storage.
4. `price` is presumably `DECIMAL`/`INT` but `index.php` multiplies by `0.9` and prints raw —
   money must be `DECIMAL(10,2)` and formatted, not float-multiplied in the view.

### Target schema

```sql
-- users: one table, three roles. 'owner' replaces the old 'admin'.
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,          -- password_hash(), never plain text
  role          ENUM('owner','kasir','customer') NOT NULL DEFAULT 'customer',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(50) NOT NULL UNIQUE            -- 'Kopi', 'Non-Kopi', 'Toast', 'Snack'
);

CREATE TABLE products (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  category_id  INT NOT NULL,
  name         VARCHAR(100)  NOT NULL,
  description  TEXT,
  price        DECIMAL(10,2) NOT NULL,
  url_image    VARCHAR(255),                    -- relative path, e.g. uploads/abc.jpg
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- guest orders are allowed => user_id NULL and customer_name/phone required
CREATE TABLE orders (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NULL,                      -- NULL = guest
  customer_name  VARCHAR(100) NOT NULL,
  customer_phone VARCHAR(20)  NULL,
  cashier_id     INT NULL,                      -- who handled it; NULL until claimed
  subtotal       DECIMAL(10,2) NOT NULL,
  discount       DECIMAL(10,2) NOT NULL DEFAULT 0,
  total          DECIMAL(10,2) NOT NULL,
  status         ENUM('pending','processing','completed','cancelled') DEFAULT 'pending',
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)    REFERENCES users(id),
  FOREIGN KEY (cashier_id) REFERENCES users(id)
);

-- price snapshot: history must not change when the owner edits a product price
CREATE TABLE order_items (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  order_id       INT NOT NULL,
  product_id     INT NOT NULL,
  product_name   VARCHAR(100) NOT NULL,
  price          DECIMAL(10,2) NOT NULL,        -- unit price at time of order
  qty            INT NOT NULL,
  line_total     DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
);

-- one rating per user per product; guests cannot rate (they cannot be identified)
CREATE TABLE reviews (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id    INT NOT NULL,
  rating     TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment    TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_product (user_id, product_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
);
```

`products.rating` is **not** stored — it is `AVG(reviews.rating)` computed on read.
Denormalising it is allowed only if you also update it on every review write; avoid
that complexity in a teaching project.

---

## 5. Business rules (the part students always get wrong)

### Discount

- **10% off only when** `$_SESSION['role'] === 'customer'` **and** the user is logged in.
- Guest (`user_id` NULL) pays **normal price**. Owner/kasir ordering is out of scope —
  treat them as normal price if it ever happens, never as a customer discount.
- **Calculate on the server, at order placement.** The catalog may *display* a struck-through
  price, but the number stored in `orders` must be recomputed from DB prices, never from
  a hidden input or a JS total.
- Store `subtotal`, `discount`, `total` on the order. If the rule changes later,
  historical orders must keep the amount actually charged.
- Rounding: `round($subtotal * 0.10, 2)`, then `total = subtotal - discount`.

### Cart

- Cart lives in `$_SESSION['cart']` as `[product_id => qty]`.
- Re-fetch name/price/image from DB on every render — never trust session prices.
- Reject qty < 1 and products with `is_active = 0`.

### Roles

| Capability | Guest | Customer | Kasir | Owner |
|---|---|---|---|---|
| Browse catalog / product detail | ✅ | ✅ | ✅ | ✅ |
| Add to cart, checkout | ✅ | ✅ | — | — |
| 10% discount | ❌ | ✅ | ❌ | ❌ |
| Submit review | ❌ | ✅ (own purchase) | ❌ | ❌ |
| See **only own** handled orders | ❌ | own order history only | ✅ | ✅ all |
| Update order status | ❌ | ❌ | ✅ own orders | ✅ |
| Manage products + image upload | ❌ | ❌ | ❌ | ✅ |
| Sales chart + Excel export | ❌ | ❌ | ❌ | ✅ |

"Kasir sees only the orders they handle" is enforced **in SQL**, not in the view:
`WHERE cashier_id = {$_SESSION['user_id']}`. Hiding rows with CSS/if-statements is a fail.

### Order status flow

`pending` → `processing` (claimed by a kasir) → `completed`; `cancelled` at any point
before completion. Claiming sets `cashier_id`. Completed/cancelled orders are read-only.

---

## 6. Code review of what is already here

### Works

- `login.php` — `mysqli_prepare` + `bind_param`, `password_verify`, `session_regenerate_id(true)`.
  This is the pattern to copy everywhere. Good.
- `seed_admin.php` — hashed seed password, one-shot script. Good habit.

### Bugs / risks to fix before adding features

1. **`auth.php:5`** — `$_SESSION['role']` is read without `isset()`. On a fresh visit this
   throws a warning (or a fatal in strict setups). Must be
   `return isset($_SESSION['role']) && $_SESSION['role'] === 'owner';`
2. **Role name mismatch** — code says `admin`, brief says `owner`. Pick `owner`, migrate the
   seed, and keep `require_owner()` / `require_role()` helpers.
3. **`navbar.php` calls `is_admin()`** but does not include `auth.php`. Any page that includes
   the navbar without `auth.php` first will fatal. Make `auth.php` the mandatory first include
   of every page (it already does `session_start()`), and have `navbar.php` require it defensively.
4. **`index.php` does not start a session itself** — it relies on `include 'auth.php'`; keep that
   order, never reorder the includes.
5. **`index.php:41`** — `price * 0.9` is float math printed raw (`18000` → `16200`), no thousand
   separator, no `number_format`, no rounding guard. Format at the view layer only.
6. **`index.php`** has an empty `<thead>` and an empty action `<td>` — the "add to cart" button
   is the missing piece for requirement 2.
7. **`register.php` is a fragment**, not a page: no `<form>`, no POST handler, no duplicate-username
   check, no password confirmation. It must be rewritten end-to-end.
8. **`logout.php` and `users.php` do not exist**, yet the navbar links to both → 404s.
9. **No CSRF protection** on any POST form. For a case study, add one token helper
   (`csrf_token()` / `csrf_check()`) and use it on login, register, cart, checkout, admin actions.
10. **No image upload validation** anywhere. Requirement says the admin uploads images:
    validate extension + MIME + size, generate a random filename, store under `uploads/`,
    never trust `$_FILES['name']`, and never allow PHP files in that folder.
11. **`seed_admin.php` is still web-accessible.** Delete it after seeding (it says so itself).
12. **No `product` delete/edit guard** — deleting a product referenced by `order_items` will
    break the FK. Use `is_active = 0` (soft delete) instead of `DELETE`.
13. **DB name** `db_kopipiro` only in `config.php`; no `.sql` dump is committed. Add `database.sql`
    + `seed.sql` so a classmate can run the project in one import.

---

## 7. Proposed page layout

Keep it flat-ish and predictable; group only where it earns its keep.

```
index.php                 catalog + category filter + rating stars
product_detail.php        description, rating, reviews, add to cart
cart.php                  session cart, qty update/remove
checkout.php              item list, subtotal, discount, total  (req. 3)
order_place.php           validates + INSERTs order & items (POST only)
order_success.php         receipt / order number
my_orders.php             customer order history

register.php  login.php  logout.php

admin/
  products.php            list + toggle active
  product_form.php        create/edit + image upload
  categories.php
  orders.php              ALL orders + filter by status/date   (req. 6)
  dashboard.php           sales chart (Chart.js) + totals       (req. 6)
  export_excel.php        .xls via header() + HTML table, or PhpSpreadsheet if allowed
  users.php               manage kasir/customer accounts

cashier/
  orders.php              WHERE cashier_id = me                 (req. 5)
  order_detail.php        claim + update status

includes/
  auth.php  config.php  navbar.php  csrf.php  helpers.php  upload.php
uploads/                  product images (writable, .htaccess deny php)
database.sql  seed.sql
```

If splitting into `admin/` and `cashier/` feels heavy, the minimum viable structure is
one folder per role with a shared `auth.php` — but do **not** put role checks only in the navbar.

### Access-control helpers to add

```php
function current_user(): ?array
function is_logged_in(): bool
function has_role(string ...$roles): bool
function require_login(): void
function require_role(string ...$roles): void   // 403 + die
function is_customer(): bool                    // drives the 10% rule
```

Only `is_customer()` may be used to decide the discount. One source of truth.

---

## 8. Excel export without Composer

The brief says "unduh excel". Simplest teaching-friendly route:

```php
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="orders-' . date('Y-m-d') . '.xls"');
```

then echo an HTML `<table>`. Excel opens it. Say so in the report — it is an HTML
table with an `.xls` extension, not a real XLSX. If a real `.xlsx` is required, vendor
PhpSpreadsheet manually or via Composer and note the extra dependency.

The chart is Chart.js from a CDN, fed by a `GROUP BY DATE(created_at)` query.
Never embed raw DB values into JS without `json_encode`.

---

## 9. Conventions

- **PHP**: procedural, `snake_case` functions, prepared statements for **every** query that
  touches user input (including `LIMIT`/sort params). No string-concatenated SQL.
- **Output**: every echoed DB/user value goes through `htmlspecialchars()`.
- **Money**: `DECIMAL(10,2)` in DB, `number_format($n, 0, ',', '.')` + `Rp` prefix in views.
- **Auth**: session only. `session_start()` in `auth.php`; `auth.php` is the first include.
- **Roles**: `owner`, `kasir`, `customer`. Old `admin` rows must be migrated.
- **UI**: Bootstrap 5.3 utility classes, one card per page section, dark navbar. No inline
  `<style>` blocks beyond a one-off; move shared CSS to `assets/style.css` if it grows.
- **Comments**: explain *why* a rule exists (e.g. "discount snapshot — price may change later"),
  not *what* the line does.
- **Timezone**: set `date_default_timezone_set('Asia/Jakarta')` once, in `config.php`.

---

## 10. Build order (suggested, maps to weeks 9–10)

1. **Foundation** — `database.sql` + `seed.sql`; migrate `admin` → `owner`; rewrite `auth.php`
   helpers; fix `navbar.php` include order; delete `seed_admin.php`.
2. **Accounts** — finish `register.php` (customer only), `logout.php`, role-aware redirect after login.
3. **Catalog** — categories, descriptions, `is_active`, rating aggregate, product detail page,
   star rendering.
4. **Cart + checkout** — session cart, server-side price recalculation, discount branch,
   order + order_items insert in a transaction (`mysqli_begin_transaction`).
5. **Owner admin** — product CRUD + validated image upload, category CRUD.
6. **Kasir** — own-orders list (SQL-scoped), claim order, status transitions.
7. **Owner reporting** — all-orders page, Chart.js sales chart, Excel download.
8. **Polish** — CSRF on all POSTs, empty states, 403 pages, flash messages, README with
   setup steps and test accounts.

Each step should end with the app still runnable. Commit per step.

---

## 11. Test accounts (after seeding)

| Role | Username | Password |
|---|---|---|
| owner | `owner` | `owner123` |
| kasir | `kasir1` | `kasir123` |
| customer | `budi` | `budi123` |

Guest checkout must be tested with a **private window** so no session leaks in.

### Manual test checklist

- [ ] Guest sees normal prices; logged-in customer sees struck-through price + 10% off.
- [ ] Guest checkout stores `user_id = NULL`, `discount = 0`.
- [ ] Customer checkout stores `discount = 10%`, `total = subtotal - discount`.
- [ ] Cart total matches the sum of line totals after a page refresh.
- [ ] Kasir A cannot see kasir B's orders — test by URL, not just by menu.
- [ ] `cashier/orders.php?id=...` for another kasir's order returns 403, not an empty page.
- [ ] Product price edited after an order does not change the old order's total.
- [ ] Owner export opens in Excel with correct columns and no HTML warnings.
- [ ] Chart totals equal the sum of `orders.total` for completed orders.
- [ ] Uploading `evil.php` as an image is rejected.

---

## 12. Open questions for the owner

1. Does checkout require cashier involvement (pay at counter), or is it online payment?
   Current assumption: order is placed online, paid and fulfilled at the counter.
2. Is the 10% discount per order or per item? Assumed **per order subtotal**.
3. Should guests be able to track an order? Assumed via order ID + phone, or not at all.
4. Stock tracking — out of scope unless the owner asks; `is_active` only.
5. Does "rating" mean an average only, or written reviews too? Assumed both, reviews table above.
6. Real XLSX needed, or is the `.xls`-HTML trick acceptable?

---

## 13. Notes for the next agent

- Do not introduce a framework, Composer, or a JS SPA. The assignment is about mapping
  requirements to plain PHP.
- Do not rename columns that existing code already reads (`product.url_image`,
  `users.username`) without updating every reader.
- Preserve the good patterns already present (prepared statements, `password_hash`).
- The database may not exist on a fresh clone: always ship `database.sql` + `seed.sql`,
  and make `config.php` fail with a readable message instead of a blank page.
- Keep `context.md` current — especially §5 (business rules) and §10 (build order).
