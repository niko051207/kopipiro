# Kopipiro — Implementation Plan

Companion to `context.md`. `context.md` says *what the app must be*; this file says
*what we do next, in what order, and how we know it works*.

**Status:** Phases 0?1 complete (MySQL 8.4.3 initialised at `C:\laragon\data\mysql`, schema + seed imported, catalog renders clean).

**Rule for every phase:** the app must still run at the end of it, and the phase's
acceptance checklist must pass before moving on.

---

## Phase 0 — Environment + database (blocker)

Nothing else can be tested until the DB exists.

- [x] **Start MySQL.** There is no data dir and no service registered for
      `C:\laragon\bin\mysql\mysql-8.4.3-winx64`. Either start Laragon (it initialises
      `data\mysql` on first run) or `mysqld --initialize-insecure` then run it.
- [x] Create database `db_kopipiro` with `utf8mb4` / `utf8mb4_unicode_ci`.
- [x] Write `database.sql`: `users`, `categories`, `products`, `orders`, `order_items`,
      `reviews` (schema from `context.md` §4), FKs, indexes on
      `orders(cashier_id)`, `orders(created_at)`, `order_items(order_id)`.
- [x] Write `seed.sql`: 3 categories, ~10 products (mix of coffee/non-coffee/toast),
      owner + 2 kasir + 2 customers, 2–3 demo orders spread over recent dates so the
      owner chart has something to draw on day one.
- [x] `config.php`: add `date_default_timezone_set('Asia/Jakarta')`, `mysqli_set_charset`
      to `utf8mb4`, and a readable failure message instead of a blank page.
- [x] `.gitignore`: `uploads/*` (keep `uploads/.gitkeep`), `*.log`.
- [x] Delete `seed_admin.php` after the seed data replaces it.

**Done when:** `mysql -uroot db_kopipiro -e "select count(*) from products"` returns rows,
and `http://kopipiro.test/index.php` renders without warnings.

---

## Phase 1 — Auth, roles, registration

Foundation for every access rule. Do this before any feature page.

- [x] Rewrite `auth.php`:
      `current_user()`, `is_logged_in()`, `has_role(...$r)`, `require_login()`,
      `require_role(...$r)`, `is_customer()`. Fix the unguarded `$_SESSION['role']`.
- [x] Role vocabulary is `owner` | `kasir` | `customer`. No `admin` anywhere.
- [x] `logout.php`: clear session array, delete cookie, destroy session, redirect.
- [x] `register.php`: full page — form, POST handler, username uniqueness check,
      password confirm, min length, `password_hash`, forced role `customer`,
      success → login page with flash message.
- [x] `login.php`: redirect by role (owner → `admin/dashboard.php`, kasir →
      `cashier/orders.php`, customer → `index.php`); keep the existing secure pattern.
- [x] `navbar.php`: include `auth.php` defensively; role-aware menu
      (Cart + My Orders for customer, Orders for kasir, Dashboard for owner).
- [x] `helpers.php`: `e()` (htmlspecialchars), `rupiah()`, `flash()`,
      `csrf_token()`, `csrf_check()`. Move `rupiah()` here, not into views.
- [x] `403.php` shared page; `require_role()` redirects to it.

**Done when:** all three accounts log in and land on the right page; `admin/dashboard.php`
returns 403 for a customer; logout kills the session; a duplicate username is rejected.

---

## Phase 2 — Catalog, product detail, ratings

- [ ] `index.php` rewrite: card grid (not a table), category filter chips, rating stars,
      price with the customer-only struck-through 10% preview, "Add to cart" button,
      out-of-stock/inactive hidden.
- [ ] `product_detail.php`: image, description, aggregated rating, review list,
      qty selector + add to cart.
- [ ] Rating = `AVG(reviews.rating)` joined per product, rendered as 5 stars.
- [ ] `review_action.php`: customer only, rating 1–5, one per user per product
      (`INSERT ... ON DUPLICATE KEY UPDATE`), CSRF checked.
- [ ] Empty state when a category has no products.

**Done when:** guest sees normal price, customer sees struck-through + 10%; rating average
matches the seeded reviews; a second review from the same user updates instead of duplicating.

---

## Phase 3 — Cart + checkout (the core of the assignment)

This is where the 10% rule lives. Treat it as the most important phase.

- [ ] `cart_action.php`: add / update qty / remove. `$_SESSION['cart'][product_id] = qty`.
      Validate product exists and `is_active = 1`; clamp qty 1..99.
- [ ] `cart.php`: list with image, name, unit price, qty stepper, line total, subtotal,
      discount row, grand total — all recalculated from DB prices on every render.
- [ ] `checkout.php`: same summary + guest form (name, phone) or prefilled customer info.
      Shows the discount line only when `is_customer()`.
- [ ] `order_place.php` (POST only):
      - re-read every price from DB,
      - `subtotal = Σ(price × qty)`,
      - `discount = is_customer() ? round(subtotal × 0.10, 2) : 0`,
      - `total = subtotal − discount`,
      - one transaction: insert `orders`, insert `order_items` with name/price snapshots,
        commit, empty the cart.
- [ ] `order_success.php`: order number, item list, total paid, discount shown if any.
- [ ] `my_orders.php`: customer history with status badges.

**Done when:**
- guest order → `user_id NULL`, `discount 0`, `total = subtotal`;
- customer order → `discount = 10%`, `total = subtotal − discount`, matches the screen;
- editing a product price afterwards does not change the old order's numbers;
- tampering with a hidden price field changes nothing;
- cart survives refresh but clears after a successful order.

> **Decision needed:** discount is applied to the **order subtotal** (assumed). Confirm.

---

## Phase 4 — Owner: products, categories, image upload, users

- [ ] `admin/products.php`: table with thumbnail, category, price, rating, active toggle.
- [ ] `admin/product_form.php`: create + edit, shared form, validation, image upload.
- [ ] `includes/upload.php`: whitelist `jpg/jpeg/png/webp`, check real MIME with
      `finfo`, max 2 MB, random filename, `move_uploaded_file`, return relative path.
      `uploads/.htaccess` denies PHP execution.
- [ ] `admin/categories.php`: CRUD, block delete when products reference the category.
- [ ] Soft delete: `is_active = 0`, never `DELETE` a product that has order history.
- [ ] `admin/users.php`: list users, create kasir accounts, reset password, deactivate.

**Done when:** a product created through the UI appears in the catalog with its image;
uploading `shell.php` is rejected with an error message; deleting a category in use is blocked.

---

## Phase 5 — Kasir

- [ ] `cashier/orders.php`: `WHERE cashier_id = ?` **or** unclaimed pending orders,
      ordered by `created_at DESC`. Status filter.
- [ ] `cashier/order_detail.php`: full items, customer, totals; "Claim order" sets
      `cashier_id` to the logged-in kasir; status buttons per allowed transition.
- [ ] Guard every detail/update action: order must belong to this kasir (403 otherwise),
      status transitions validated server-side (no `pending → completed` shortcut).
- [ ] Claiming is atomic: `UPDATE orders SET cashier_id = ? WHERE id = ? AND cashier_id IS NULL`
      and check `affected_rows` — otherwise two kasir claim the same order.

**Done when:** kasir A cannot open kasir B's order URL; claim race loses gracefully;
completed orders show no status buttons.

---

## Phase 6 — Owner reporting

- [ ] `admin/orders.php`: all orders, filters (date range, status, kasir), pagination,
      totals footer.
- [ ] `admin/dashboard.php`: KPI cards (revenue, order count, avg order, top product) +
      Chart.js line/bar from `GROUP BY DATE(created_at)`, `json_encode` the data.
- [ ] `admin/export_excel.php`: same filter as `admin/orders.php`, `header()` +
      HTML table saved as `.xls`, filename with date.
- [ ] Chart and export must use the same "completed orders count as revenue" definition.

**Done when:** chart sum equals `SUM(total)` for completed orders in the same range;
the exported file opens in Excel with clean columns; filters affect both chart and export.

---

## Phase 7 — Polish + submission

- [ ] CSRF token on every POST form (login, register, cart, checkout, admin, kasir, review).
- [ ] Flash messages for success/error; empty states everywhere.
- [ ] `README.md`: setup (import SQL, start MySQL), test accounts, feature list,
      screenshots, known limitations.
- [ ] Security pass: no unescaped output, no string-concatenated user input in SQL,
      no `.php` in `uploads/`, `seed_admin.php` gone.
- [ ] Full manual test from `context.md` §11.
- [ ] Commit history: one commit per phase, message names the feature.

---

## Ordering rationale

Auth before catalog (every page needs role awareness) → catalog before cart (cart needs
valid products + prices) → cart/checkout before kasir (kasir needs orders to exist) →
kasir before reporting (reporting needs statuses to be meaningful) → polish last.

Owner product management could move before checkout if the owner wants to demo adding
products first; nothing else depends on that ordering.

---

## Decisions blocking later phases

| # | Question | Default if unanswered |
|---|---|---|
| 1 | Discount per order or per item? | per **order subtotal** |
| 2 | Who can review a product? | any logged-in customer, one review per product (no purchase requirement) |
| 3 | Excel: `.xls`-HTML or real `.xlsx`? | `.xls`-HTML, no extra dependency |
| 4 | Guest checkout fields? | name + phone required |
| 5 | Kasir assignment: self-claim or owner-assigned? | self-claim from a pending pool |

---

## Risky spots to watch

- **Discount recomputation** — the single most likely place to lose marks. Server-side only.
- **Session cart prices** — re-read from DB on render, never trust the session's price.
- **Kasir scoping** — must be in the WHERE clause, not a UI filter.
- **FK on delete** — product deletion will throw once order history exists.
- **MySQL not running** — a fresh clone shows a blank page; `config.php` must explain it.
- **Windows paths in uploads** — use `__DIR__` and forward slashes in stored paths.
