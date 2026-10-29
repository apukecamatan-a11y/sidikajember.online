<?php
// api/generate-token.php
// SEKALI PAKAI: buka file ini lewat browser (mis. https://domainanda.com/api/generate-token.php)
// untuk mendapatkan token acak yang aman, lalu salin hasilnya ke $API_TOKEN di api/config.php.
//
// PENTING: HAPUS file ini dari server setelah token disalin, supaya tidak ada orang lain
// yang bisa membuka halaman ini dan (secara tidak sengaja) mengira itu token yang berlaku.
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
echo "Token acak baru (salin ke \$API_TOKEN di api/config.php):\n\n";
echo bin2hex(random_bytes(32)) . "\n\n";
echo "Setelah disalin, HAPUS file generate-token.php ini dari server.\n";
