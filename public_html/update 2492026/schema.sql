-- Skema database MySQL untuk Sistem Register & Laporan Imunisasi Bayi (terpusat)
-- Jalankan file ini SEKALI lewat phpMyAdmin di Hostinger (hPanel > Databases > phpMyAdmin > tab SQL)
--
-- Cara pakai:
-- 1. Buat database & user MySQL dulu dari hPanel (hPanel > Databases > MySQL Databases).
-- 2. Buka phpMyAdmin, pilih database tsb, buka tab "SQL", tempel seluruh isi file ini, lalu jalankan.
-- 3. Isi kredensial database yang sama persis ke file api/config.php.

CREATE TABLE IF NOT EXISTS app_storage (
  storage_key   VARCHAR(191)  NOT NULL PRIMARY KEY,
  storage_value LONGTEXT      NULL,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_updated_at (updated_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  ROW_FORMAT=DYNAMIC;

-- Kalau tabel app_storage sudah pernah dibuat sebelumnya dari versi schema.sql lama
-- (tanpa kolom created_at), jalankan baris berikut sekali saja untuk menambahkannya
-- tanpa kehilangan data yang sudah ada:
-- ALTER TABLE app_storage
--   ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER storage_value;

-- Catatan penting:
-- * VARCHAR(191) dipilih (bukan 255) supaya PRIMARY KEY tetap valid di kombinasi
--   MySQL/MariaDB + charset utf8mb4 yang batas indexnya 767 byte (191 x 4 byte = 764 byte, aman).
--   api/storage.php membatasi panjang key di sisi aplikasi ke 191 karakter juga —
--   kalau angka ini diubah, ubah juga MAX_KEY_LEN di api/storage.php.
-- * ROW_FORMAT=DYNAMIC + utf8mb4 memastikan teks Bahasa Indonesia (termasuk simbol/emoji)
--   tersimpan tanpa error "Row size too large" atau karakter rusak (mojibake).
-- * Koneksi PDO di api/storage.php WAJIB pakai charset=utf8mb4 agar konsisten dengan tabel ini.
-- * idx_updated_at ditambahkan agar query audit/debug "data mana yang terakhir diubah" cepat,
--   sifatnya opsional untuk operasional harian tapi tidak merugikan.
-- * created_at dicatat terpisah dari updated_at supaya bisa dibedakan "kapan key ini pertama
--   kali dibuat" vs "kapan terakhir diubah" — berguna untuk audit data kesehatan bayi.

-- Catatan struktur data (tidak perlu tabel terpisah, semua tersimpan sebagai key-value):
--   role:<slug-pkm>              -> "admin" | "operator"
--   assigned-posy:<slug-pkm>     -> nama posyandu (untuk role operator)
--   assigned-desa:<slug-pkm>     -> nama desa (untuk role operator)
--   account-pwd:<slug-pkm>       -> kata sandi akun Puskesmas/Dinas
--   bayi-records:<slug-pkm>      -> JSON array data Bayi milik 1 Puskesmas
--   operator-accounts:<slug-pkm> -> JSON array akun Operator Posyandu (sub-akun) milik 1 Puskesmas.
--                                    Tiap akun terkunci ke satu Posyandu (dan opsional Desa/Kelurahan),
--                                    punya kata sandi sendiri, dan hanya dikelola oleh Admin Puskesmas
--                                    yang bersangkutan (dibuat/reset/hapus). Terpisah dari akun Admin
--                                    Puskesmas utama (key account-pwd:<slug-pkm>).
--   balita-records:<slug-pkm>    -> JSON array data Balita milik 1 Puskesmas (LEGACY: menu "Data
--                                    Balita" & "Impor Data Balita" sudah dihapus dari index.html,
--                                    tidak ada lagi UI untuk mengelola key ini; data lama pada key
--                                    ini dibiarkan apa adanya di database, tidak dihapus otomatis)
--   wus-records:<slug-pkm>       -> JSON array data WUS (Wanita Usia Subur) milik 1 Puskesmas
--   bumil-records:<slug-pkm>     -> JSON array data Ibu Hamil milik 1 Puskesmas
--   pusdatin-target:<slug-pkm>   -> JSON target/populasi acuan Pusdatin, diinput manual oleh Dinas
--   data-basis:<slug-pkm>        -> JSON data acuan wilayah (Kecamatan, Posyandu/Desa) hasil "Generate
--                                    Data Basis" oleh Admin Dinas, disebar ke tiap Puskesmas
--   data-basis-generated-at      -> (key tunggal, bukan per-Puskesmas) waktu terakhir "Generate Data
--                                    Basis" dijalankan
--   selected-pkm                 -> (key tunggal) slug Puskesmas/Dinas yang sedang login di sesi
--                                    browser tsb
--   sasaran-last-update          -> (key tunggal) cap waktu kecil yang ditulis tiap kali data sasaran
--                                    (bayi/balita/WUS/bumil/target Pusdatin) disimpan; dipakai
--                                    perangkat lain untuk tahu kapan harus memuat ulang data
--   sasaran-desa-target-override -> (key tunggal) JSON target sasaran per Desa hasil ubahan manual
--   helpdesk-index               -> (key tunggal) JSON daftar metadata modul/panduan Helpdesk
--   helpdesk-file:<id>           -> isi file 1 modul Helpdesk sebagai data URL base64 (maks. file asli
--                                    4 MB di index.html => value sekitar 5,5 MB; muat di LONGTEXT)
--   helpdesk-read:<slug-pkm>     -> JSON { [idModul]: waktuBaca } per Puskesmas (lencana "Baru"
--                                    & statistik baca Dinas)
-- Catatan akses: mulai versi index.html terbaru, impor data massal (bayi-records, wus-records,
-- bumil-records lintas Puskesmas) HANYA dilakukan lewat akun Admin Dinas (Super Admin) —
-- akun Puskesmas tidak lagi punya menu impor, tapi tetap membaca/menulis key-key di atas seperti biasa
-- untuk data miliknya sendiri (tambah/ubah/hapus satu per satu). Menu "Data Balita" (list per-Puskesmas)
-- dan "Impor Data Balita" (Admin Dinas) sudah dihapus sepenuhnya dari index.html — key balita-records
-- tidak lagi punya UI baca/tulis sama sekali, tapi tetap boleh ada di tabel app_storage kalau
-- sebelumnya sudah pernah diisi. Tidak ada perubahan skema/tabel yang diperlukan untuk perubahan ini
-- karena app_storage bersifat key-value generik.
-- Semua 50 Puskesmas dan Dinas membaca/menulis ke tabel yang sama ini lewat api/storage.php,
-- sehingga datanya benar-benar terpusat di satu database MySQL, bukan lagi localStorage per-browser.

-- Catatan untuk fitur "Akun Operator Posyandu" (key operator-accounts:<slug-pkm>):
-- * TIDAK PERLU menjalankan ALTER TABLE atau membuat tabel baru apa pun untuk fitur ini.
-- * Tabel app_storage sudah generik (key-value), jadi key baru apa pun otomatis tertampung
--   selama panjangnya <= 191 karakter (lihat MAX_KEY_LEN di api/storage.php) dan isinya
--   berupa teks/JSON (cocok dengan tipe LONGTEXT di kolom storage_value).
-- * Kalau schema.sql versi ini dijalankan di database yang SUDAH ADA (tabel app_storage
--   sudah pernah dibuat sebelumnya), tidak akan terjadi apa-apa yang merusak data lama:
--   CREATE TABLE IF NOT EXISTS akan dilewati begitu saja karena tabelnya sudah ada.
-- * Jadi untuk mengaktifkan fitur Akun Operator Posyandu di server yang sudah jalan,
--   Anda CUKUP meng-upload ulang index.html yang baru — tidak perlu menyentuh database
--   atau menjalankan SQL apa pun lagi.

-- ------------------------------------------------------------------------------------------
-- OPSIONAL — jejak audit perubahan data (disarankan untuk data kesehatan bayi, tapi tidak
-- wajib dan TIDAK otomatis aktif). api/storage.php versi saat ini belum menulis ke tabel ini;
-- aktifkan hanya kalau memang dibutuhkan pencatatan siapa/kapan setiap key diubah/dihapus.
-- ------------------------------------------------------------------------------------------
-- CREATE TABLE IF NOT EXISTS app_storage_audit (
--   id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
--   storage_key   VARCHAR(191)  NOT NULL,
--   action        ENUM('set','delete') NOT NULL,
--   client_ip     VARCHAR(45)   NULL,
--   changed_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
--   KEY idx_key (storage_key),
--   KEY idx_changed_at (changed_at)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Catatan: kolom client_ip WAJIB ada persis dengan nama ini kalau tabel ini diaktifkan —
-- api/storage.php (fungsi writeAudit) menulis ke kolom client_ip setiap kali key
-- di-set/dihapus. Kalau kolom ini tidak ada, insert audit akan gagal setiap saat
-- (disenyapkan otomatis oleh try/catch di storage.php, jadi aplikasi tetap jalan
-- normal, tapi audit log tidak akan pernah terisi).
