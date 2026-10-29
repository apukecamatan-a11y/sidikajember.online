# Panduan Instalasi — SIKINDA (Sistem Informasi Digital Imunisasi Kab. Jember) di Hostinger

Paket ini berisi:

```
index.html                 <- aplikasi (React, jalan di browser)
schema.sql                 <- struktur tabel MySQL, dijalankan sekali lewat phpMyAdmin
api/
  .htaccess                <- proteksi folder api/ (blokir akses config.php dari luar)
  config.php                <- kredensial database + pengaturan keamanan
  storage.php               <- endpoint API yang dipanggil index.html untuk baca/tulis data
  generate-token.php        <- alat bantu sekali-pakai untuk membuat token acak
```

## ⚠️ Sebelum mulai — soal kredensial

File `api/config.php` yang Anda unggah sudah berisi password database dan token API yang **sungguhan/aktif**, jadi paket ini saya kembalikan dengan nilai yang sama supaya sistem Anda tetap langsung jalan. Tapi karena kredensial itu sudah pernah tertulis di percakapan ini, sebagai kebiasaan keamanan yang baik:

- **Ganti password database** itu di hPanel Hostinger (Databases → MySQL Databases) kapan pun Anda sempat, lalu update juga di `api/config.php`.
- **Buat token API baru** (lihat Langkah 5) dan ganti nilainya di **dua tempat**: `$API_TOKEN` di `api/config.php` DAN `STORAGE_API_KEY` di `index.html` — keduanya harus identik persis.

Ini bukan darurat kalau server belum online ke publik, tapi lakukan sebelum sistem ini menyimpan data NIK bayi yang sesungguhnya.

## Perbaikan yang saya lakukan pada paket ini

1. **Bug kritis diperbaiki**: `index.html` sebelumnya tidak pernah mengirim header `X-Api-Key`, padahal `api/config.php` sudah mengisi `$API_TOKEN`. Akibatnya, di server sungguhan **setiap permintaan baca/tulis data akan ditolak 401 Unauthorized** dan aplikasi tidak akan bisa memuat data sama sekali. Sekarang `index.html` mengirim header itu, dan pesan error 401 dibuat lebih jelas kalau suatu saat token tidak cocok.
2. **Skema tabel audit (opsional) diperbaiki**: `storage.php` mencatat kolom `client_ip` ke tabel `app_storage_audit`, tapi definisi tabel di `schema.sql` belum punya kolom itu. Sudah ditambahkan, supaya kalau Anda mengaktifkan fitur audit log nanti, pencatatannya benar-benar tersimpan (bukan gagal diam-diam).
3. File `_htaccess` diganti nama menjadi `.htaccess` (nama yang benar untuk Apache/Hostinger — banyak sistem file/unggahan menyembunyikan file berawalan titik, makanya sering terunggah dengan nama `_htaccess`).

Selebihnya (`storage.php`, `config.php`, `generate-token.php`) sudah dirancang dengan baik: PDO + prepared statements (aman dari SQL injection), validasi panjang key/value, penanganan error yang tidak membocorkan detail ke publik, dan connection timeout di sisi browser. Tidak ada perubahan struktural yang diperlukan di situ.

---

## Langkah 1 — Buat database MySQL di Hostinger

1. Masuk **hPanel** → **Databases** → **MySQL Databases**.
2. Buat database baru (atau gunakan yang sudah ada di `config.php`: `u611934368_sikinda`).
3. Buat user database (atau gunakan yang sudah ada: `u611934368_admin`) dan **catat passwordnya**.
4. Pastikan user tersebut di-assign ke database tadi dengan semua hak akses (All Privileges).
5. Catat juga nama host — di Hostinger shared hosting biasanya `localhost`.

## Langkah 2 — Buat tabel lewat phpMyAdmin

1. Di hPanel, buka **Databases** → **phpMyAdmin**, lalu klik database Anda di panel kiri.
2. Buka tab **SQL**.
3. Buka file `schema.sql` dari paket ini, salin **seluruh isinya**, tempel ke kotak SQL di phpMyAdmin.
4. Klik **Go / Jalankan**.
5. Pastikan tabel `app_storage` muncul di daftar tabel. (Bagian yang diawali `--` adalah komentar/opsional — boleh dijalankan apa adanya, tidak akan error.)

## Langkah 3 — Upload file ke Hostinger

1. Buka hPanel → **File Manager** (atau pakai FTP/SFTP kalau Anda lebih terbiasa).
2. Masuk ke folder `public_html` (atau folder domain/subdomain tujuan Anda).
3. Upload struktur berikut **persis seperti ini**:
   ```
   public_html/
     index.html
     api/
       .htaccess
       config.php
       storage.php
       generate-token.php
   ```
   Folder `api` harus ada di dalam `public_html`, sejajar dengan `index.html` — jangan diletakkan di luar `public_html`, karena `index.html` memanggilnya lewat path relatif `api/storage.php`.
4. Kalau File Manager Hostinger menyembunyikan file yang diawali titik, pastikan `.htaccess` benar-benar ter-upload ke dalam folder `api/` (bukan hilang) — ini yang memblokir akses langsung ke `config.php` dari browser luar.

## Langkah 4 — Isi kredensial di `api/config.php`

Buka `api/config.php` lewat File Manager (Edit), isi 4 baris berikut sesuai data dari Langkah 1:

```php
$DB_HOST = 'localhost';
$DB_PORT = 3306;
$DB_NAME = 'u611934368_sidika';
$DB_USER = 'u611934368_admin';
$DB_PASS = 'Kb1324pgr!';
```

## Langkah 5 — Buat & pasang token API (sangat disarankan)

Data yang dikelola sistem ini adalah data kesehatan bayi (termasuk NIK), jadi endpoint `api/storage.php` sebaiknya tidak dibiarkan bisa diakses siapa saja yang tahu alamatnya.

1. Buka `https://domain-anda.com/api/generate-token.php` lewat browser. Halaman itu akan menampilkan satu baris token acak.
2. Salin token tersebut.
3. Tempel ke `$API_TOKEN` di `api/config.php`: API ODOwN2k8vF3AB8XdvSvLZhJs5ax8pplvvRVL5FUYdac05ab6
   ```php
   $API_TOKEN = 'token-acak-hasil-generate-tadi';
   ```
4. Buka `index.html`, cari baris (dekat bagian atas file, di dalam tag `<script>` pertama):
   ```js
   var STORAGE_API_KEY = "...";
   ```
   Ganti isinya dengan **token yang sama persis** dari langkah 3.
5. **Hapus file `api/generate-token.php` dari server** setelah token disalin — file ini tidak perlu online lagi dan bisa jadi celah kalau dibiarkan.

Kalau Anda belum siap mengatur ini, biarkan `$API_TOKEN = ''` (kosong) di `config.php` **dan** `STORAGE_API_KEY = ""` (kosong) di `index.html` — sistem akan tetap jalan tanpa proteksi token tambahan, tapi ini tidak disarankan untuk data produksi/sungguhan.

## Langkah 6 — Aktifkan SSL (HTTPS)

1. Di hPanel → **SSL**, aktifkan SSL gratis untuk domain Anda (biasanya otomatis di Hostinger).
2. Setelah SSL aktif dan situs sudah bisa diakses lewat `https://`, pastikan `api/config.php` punya:
   ```php
   $FORCE_HTTPS = true;
   ```
   supaya token dan data (termasuk NIK) tidak pernah terkirim tanpa enkripsi.
3. Kalau situs masih dites lewat `http://` biasa (SSL belum aktif), set dulu `$FORCE_HTTPS = false` sementara, supaya tidak semua request ditolak 403.

## Langkah 7 — Uji coba

1. Buka `https://domain-anda.com/api/storage.php?action=ping` langsung di browser.
   - Kalau `$API_TOKEN` kosong → harus muncul `{"ok":true,"time":"..."}`.
   - Kalau `$API_TOKEN` diisi → browser biasa tidak mengirim header `X-Api-Key`, jadi wajar kalau muncul `{"error":"unauthorized"}` di sini. Itu tandanya proteksi bekerja. Pengujian sesungguhnya ada di langkah berikut.
2. Buka `https://domain-anda.com/index.html` (atau langsung `https://domain-anda.com/` kalau `index.html` sudah jadi file utama).
3. Aplikasi harus memuat tanpa pesan "Gagal terhubung ke server database" di layar. Coba login/pilih Puskesmas dan simpan satu data uji untuk memastikan tulis-baca ke database benar-benar berfungsi.
4. Kalau muncul pesan error di layar, buka Console browser (F12) untuk detail, dan cocokkan dengan daftar berikut:
   - **401 Unauthorized** → `$API_TOKEN` di `config.php` dan `STORAGE_API_KEY` di `index.html` tidak sama persis. Cek lagi Langkah 5.
   - **db_connect_failed** / **config_not_set** → kredensial di `config.php` (Langkah 4) belum benar, atau database/user belum dibuat/di-assign dengan benar.
   - **https_required** → situs diakses lewat `http://` padahal `$FORCE_HTTPS = true`. Aktifkan SSL dulu (Langkah 6) atau set sementara ke `false`.
   - Request gagal total / CORS error → pastikan `index.html` dan folder `api/` berada di domain yang **sama** (bukan cross-origin). Kalau memang beda domain, isi `$ALLOWED_ORIGIN` di `config.php` dengan domain tempat `index.html` di-host.

## Catatan tambahan

- Aplikasi ini murni file statis (`index.html`) + satu endpoint PHP (`api/storage.php`) — tidak perlu Node.js, Composer, atau proses build apa pun. Cocok untuk shared hosting seperti Hostinger.
- Semua 50 Puskesmas & Dinas membaca/menulis ke tabel `app_storage` yang sama, jadi datanya benar-benar terpusat di satu database (bukan lagi per-browser seperti localStorage).
- Kalau ke depan Anda ingin pencatatan audit "siapa mengubah data apa, kapan" untuk data bayi, aktifkan tabel `app_storage_audit` yang komentarnya ada di bagian bawah `schema.sql` (hapus tanda `--` di depan setiap barisnya, lalu jalankan lagi lewat phpMyAdmin).
