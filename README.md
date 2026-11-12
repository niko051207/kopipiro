# Kopipiro

Aplikasi pemesanan coffee shop berbasis PHP dan MySQL untuk Laragon, dengan lapisan REST API JSON.

## Menjalankan di Laragon

1. Simpan folder proyek di `C:\laragon\www\kopipiro`.
2. Buka Laragon dan pilih **Start All**.
3. Buka `http://localhost/phpmyadmin`, pilih tab **Import**, lalu impor file `database.sql`.
4. Buka `http://kopipiro.test` atau `http://localhost/kopipiro`.

Konfigurasi database bawaan Laragon ada di `config.php` (user `root`, kata sandi kosong, database `db_kopipiro`).

## Akun demo

- Owner: `owner` / `owner123`
- Kasir: `kasir1` / `kasir123`
- Customer demo: `customer` / `customer123`
- Customer baru: daftar melalui halaman aplikasi atau lewat `api/auth.php?action=register`.

Pelanggan yang masuk ke akun mendapatkan diskon 10%. Pesanan tanpa akun tetap menggunakan harga normal dan muncul pada antrian kasir.

## REST API

Semua endpoint mengembalikan JSON dan memakai sesi PHP yang sama dengan halaman web,
jadi login lewat API juga membuat halaman biasa ikut masuk.

| Method | Endpoint | Siapa | Keterangan |
| --- | --- | --- | --- |
| GET | `api/auth.php?action=me` | siapa saja | User aktif, atau `{"role":"guest"}` |
| POST | `api/auth.php?action=login` | siapa saja | Body `{username, password}` |
| POST | `api/auth.php?action=register` | siapa saja | Body `{name, username, password}`, selalu membuat role `customer` |
| POST | `api/auth.php?action=logout` | siapa saja | Mengakhiri sesi |
| GET | `api/products.php` | siapa saja | Katalog; `?category=<slug>` dan `?q=<cari>` opsional |
| POST | `api/products.php` | owner | Tambah produk (multipart, gambar maks. 2 MB) |
| PUT | `api/products.php?id=3` | owner | Body `{price}`; `name`, `description`, `is_available` opsional |
| DELETE | `api/products.php` | owner | 405 — produk dinonaktifkan lewat `is_available` |
| POST | `api/orders.php` | tamu / customer | Body `{items:[{product_id, qty}], customer_name, phone}` |
| GET | `api/orders.php` | owner / kasir / customer | Owner melihat semua, kasir hanya pesanan miliknya, customer hanya pesanannya |
| PUT | `api/orders.php?id=5` | owner / kasir | Body `{status}`: `pending`, `preparing`, `ready`, `completed`, `cancelled` |
| GET | `api/sales.php` | owner | Data grafik: `daily` dan `top` |

Kode status yang dipakai: `200` OK, `201` dibuat, `400` input salah, `401` belum login,
`403` role tidak diizinkan, `404` data tidak ditemukan, `405` method salah, `409` username
sudah dipakai, `500` server, `502` layanan pihak ketiga gagal.

### Contoh cURL

```bat
curl.exe "http://kopipiro.test/api/products.php"

curl.exe -X POST "http://kopipiro.test/api/auth.php?action=login" ^
  -H "Content-Type: application/json" -c cookies.txt ^
  -d "{\"username\":\"owner\",\"password\":\"owner123\"}"

curl.exe -X POST "http://kopipiro.test/api/orders.php" ^
  -H "Content-Type: application/json" -b cookies.txt ^
  -d "{\"items\":[{\"product_id\":1,\"qty\":2}],\"phone\":\"081200000000\"}"
```

### Aturan yang dijalankan di server

- Harga **selalu** dibaca dari database; `price` yang dikirim browser diabaikan.
- Diskon 10% hanya ada di `final_price()` (`helpers.php`), dipakai halaman maupun API.
- Role dicek di dalam endpoint (`need_role()`), bukan hanya dengan menyembunyikan tombol.
- Pesanan dan itemnya disimpan dalam satu transaksi.
- Pesanan baru otomatis diberikan ke kasir dengan pesanan aktif paling sedikit.
- Upload gambar: batas ukuran, MIME asli lewat `finfo`, nama file acak.

### Perbedaan dengan materi modul

Modul memakai skema contoh; proyek ini memakai `database.sql` yang sudah ada, jadi:

- login memakai `username` (bukan `email`), role kasir bernama `kasir` (bukan `cashier`);
- `phone` wajib diisi karena kolomnya `NOT NULL`;
- status pembayaran `paid`/`done` dipetakan ke `completed`;
- `order_code` belum ada, pesanan diidentifikasi dengan `id`;
- `Access-Control-Allow-Origin: *` hanya dipasang di `GET api/products.php` (baca saja).
  Di produksi batasi domain asalnya, karena `*` membuat API bisa dipanggil situs mana pun.

### Impor dari public API

Modul meminta data diambil dari API publik. Jalankan sekali:

```bat
C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe tools\seed_from_api.php
```

Skrip memanggil `https://api.sampleapis.com/coffee/hot` (butuh `extension=curl` di
`php.ini`), menyimpan kopi yang belum ada beserta harga karangan, lalu menambahkan
beberapa rating demo. Jika API sedang mati, skrip berhenti dengan pesan jelas dan tidak
menyimpan data setengah jadi.

### Menguji API

```bat
C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe tools\api_smoke.php
C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe tools\api_smoke.php http://kopipiro.test
```

Skrip menguji status code, diskon, penolakan harga palsu, pembatasan role, dan validasi
upload. Keluar dengan kode 0 bila semua pemeriksaan lolos. Setiap kali dijalankan, skrip
membuat satu akun customer uji dan satu produk uji yang langsung dinonaktifkan.

### Belum termasuk

Pembayaran Midtrans sandbox, kolom `order_code`, login berbasis email, `POST api/reviews.php`,
ekspor `.xlsx` (masih CSV), dan pembatasan percobaan login.
