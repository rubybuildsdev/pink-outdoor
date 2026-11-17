<?php
// config/config.php

// ── 0. Load file .env jika ada ──────────────────────────────
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            // Bersihkan tanda kutip jika ada
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("$key=$value");
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Auto-detect BASE_URL agar bisa jalan di localhost maupun hosting
if (!defined('BASE_URL')) {
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script   = $_SERVER['SCRIPT_NAME'] ?? '/';
    // Ambil sub-folder pertama (misalnya /pink-outdoor/)
    $parts    = explode('/', trim($script, '/'));
    $basePath = (count($parts) > 1 && !str_ends_with($parts[0], '.php'))
                ? '/' . $parts[0] . '/'
                : '/';
    define('BASE_URL', $scheme . '://' . $host . $basePath);
}

define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('SITE_NAME',   'Pink Outdoor Gear Rent');

// Konfigurasi Google OAuth terpusat (dimuat dari .env)
define('GOOGLE_CLIENT_ID',     getenv('GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
define('GOOGLE_REDIRECT_URI',  BASE_URL . 'auth/google-callback.php');

// Standar Status Booking
define('BOOKING_STATUS_PENDING',     'pending');
define('BOOKING_STATUS_DIPROSES',    'diproses');
define('BOOKING_STATUS_SEDANG_SEWA', 'sedang_sewa');
define('BOOKING_STATUS_SELESAI',     'selesai');
define('BOOKING_STATUS_CANCELLED',   'cancelled');

// Data Rekening Pembayaran Resmi Toko
define('PAYMENT_CHANNELS', [
    'bca' => [
        'name'        => 'BCA (Bank Central Asia)',
        'type'        => 'bank',
        'account_no'  => '148-098-7654',
        'holder_name' => 'PINK OUTDOOR GEAR',
        'instructions'=> 'Transfer via ATM / m-BCA / KlikBCA ke nomor rekening di atas.',
    ],
    'bri' => [
        'name'        => 'BRI (Bank Rakyat Indonesia)',
        'type'        => 'bank',
        'account_no'  => '0123-01-098765-50-1',
        'holder_name' => 'PINK OUTDOOR GEAR',
        'instructions'=> 'Transfer via ATM BRI / BRImo ke nomor rekening di atas.',
    ],
    'mandiri' => [
        'name'        => 'Bank Mandiri',
        'type'        => 'bank',
        'account_no'  => '131-00-1234567-8',
        'holder_name' => 'PINK OUTDOOR GEAR',
        'instructions'=> 'Transfer via Mandiri Livin / ATM Mandiri ke nomor rekening di atas.',
    ],
    'gopay' => [
        'name'        => 'GoPay',
        'type'        => 'ewallet',
        'account_no'  => '0812-3456-7890',
        'holder_name' => 'Pink Outdoor Store',
        'instructions'=> 'Kirim saldo GoPay ke nomor ponsel di atas.',
    ],
    'dana' => [
        'name'        => 'DANA',
        'type'        => 'ewallet',
        'account_no'  => '0812-3456-7890',
        'holder_name' => 'Pink Outdoor Store',
        'instructions'=> 'Kirim saldo DANA / Transfer Saldo ke nomor ponsel di atas.',
    ],
    'ovo' => [
        'name'        => 'OVO',
        'type'        => 'ewallet',
        'account_no'  => '0812-3456-7890',
        'holder_name' => 'Pink Outdoor Store',
        'instructions'=> 'Transfer OVO Cash ke nomor ponsel di atas.',
    ],
]);

// Info Basecamp Resmi Toko
define('BASECAMP_ADDRESS', 'Jl. Patriot No. 12, Tarogong Kidul, Garut, Jawa Barat 44151');
define('BASECAMP_HOURS',   'Buka Setiap Hari: 07.00 – 21.00 WIB');
define('STORE_PHONE',      '0812-3456-7890');
define('STORE_WA_LINK',    'https://wa.me/6281234567890');
define('MAPS_LINK',        'https://maps.google.com/?q=Tarogong+Kidul+Garut');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
