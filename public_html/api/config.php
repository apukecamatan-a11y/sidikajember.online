<?php
// api/config.php
// Isi 5 nilai koneksi database di bawah ini sesuai database MySQL yang dibuat
// di hPanel Hostinger (hPanel > Databases > MySQL Databases).
// Nama host di Hostinger biasanya "localhost".

$DB_HOST = 'localhost';
$DB_PORT = 3306; // ganti kalau Hostinger memberi port khusus, biasanya tidak perlu diubah
$DB_NAME = 'u611934368_sidika';
$DB_USER = 'u611934368_admin'; // ganti dengan user database yang tersimpan di hPanel
$DB_PASS = 'Kb1324pgr!'; // ganti dengan password database yang tersimpan di hPanel

// ------------------------------------------------------------------
// KEAMANAN #1 — TOKEN API (opsional, tapi SANGAT disarankan untuk data kesehatan bayi)
// ------------------------------------------------------------------
// Endpoint api/storage.php ini TIDAK punya login sendiri — dia hanya media
// baca/tulis mentah untuk key-value yang dipakai index.html. Tanpa token,
// siapa pun yang tahu alamat api/storage.php bisa membaca/mengubah/menghapus
// SELURUH data bayi 50 Puskesmas (termasuk NIK), langsung lewat browser/curl,
// tanpa perlu login lewat index.html sama sekali.
//
// Isi $API_TOKEN dengan string acak/rahasia untuk mengaktifkan proteksi ini.
// Biarkan kosong ('') kalau belum siap mengubah index.html — aplikasi akan
// tetap berjalan seperti biasa (tanpa proteksi tambahan) selama kosong.
//
// Cara membuat token acak yang aman (pilih salah satu):
//   - Jalankan file generate-token.php yang disertakan bersama paket ini lewat
//     browser (isinya cukup `echo bin2hex(random_bytes(32));`), salin hasilnya
//     ke sini, LALU HAPUS file generate-token.php dari server setelah dipakai.
//   - Atau pakai generator password acak 40+ karakter dari password manager Anda.
//
// Kalau diisi, index.html WAJIB mengirim header berikut pada setiap request
// ke storage.php (lihat catatan & contoh di storage.php dan panduan instalasi):
//   X-Api-Key: <isi yang sama persis dengan $API_TOKEN>
//
// PENTING — SINKRONISASI: nilai di bawah ini HARUS SAMA PERSIS dengan nilai
// variabel STORAGE_API_KEY di index.html (dekat bagian atas file, sebelum
// "window.storage = {"). Kalau salah satu diubah, yang satunya HARUS
// diubah juga (copy-paste), atau semua request dari index.html akan gagal
// dengan error 401 Unauthorized.
$API_TOKEN = 'i3jdPYhCtIsmzpN75dzZbIfLA3J56qWr6oROPpI40d00fe55';

// ------------------------------------------------------------------
// KEAMANAN #2 — PAKSA HTTPS
// ------------------------------------------------------------------
// Kalau diisi true, storage.php akan menolak (403) permintaan yang datang
// lewat http:// biasa (bukan https://) — mencegah data (termasuk X-Api-Key
// dan NIK bayi) terkirim tanpa enkripsi di jaringan publik/WiFi Puskesmas.
// Aktifkan setelah domain Anda punya SSL aktif (Hostinger biasanya sudah
// menyediakan SSL gratis lewat hPanel > SSL). Biarkan false kalau situs
// masih diakses lewat http:// (mis. saat pengujian awal).
$FORCE_HTTPS = true;

// Isi dengan domain resmi aplikasi (mis. 'https://sidika.jemberkab.go.id')
// HANYA kalau index.html dan api/storage.php pernah diakses dari domain/subdomain
// yang BERBEDA (cross-origin). Kosongkan ('') kalau index.html & storage.php
// selalu berada di satu domain yang sama (kasus paling umum di Hostinger) —
// browser sudah otomatis mengizinkan tanpa header CORS tambahan.
$ALLOWED_ORIGIN = '';
