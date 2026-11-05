# Kopipiro

Aplikasi pemesanan coffee shop berbasis PHP dan MySQL untuk Laragon.

## Menjalankan di Laragon

1. Simpan folder proyek di `C:\laragon\www\kopipiro`.
2. Buka Laragon dan pilih **Start All**.
3. Buka `http://localhost/phpmyadmin`, pilih tab **Import**, lalu impor file `database.sql`.
4. Buka `http://kopipiro.test` atau `http://localhost/kopipiro`.

Konfigurasi database bawaan Laragon ada di `config.php` (user `root`, kata sandi kosong, database `db_kopipiro`).

## Akun demo

- Owner: `owner` / `owner123`
- Kasir: `kasir1` / `kasir123`
- Customer: daftar melalui halaman aplikasi.

Pelanggan yang masuk ke akun mendapatkan diskon 10%. Pesanan tanpa akun tetap menggunakan harga normal dan muncul pada antrian kasir.
