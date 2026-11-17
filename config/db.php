<?php
// ============================================
// config/db.php — Koneksi Database
// ============================================

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'pink_outdoor');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("<div style='font-family:sans-serif;padding:2rem;color:red;'>
        <h3>❌ Koneksi Database Gagal</h3>
        <p>" . $conn->connect_error . "</p>
        <p>Pastikan XAMPP MySQL aktif dan database <b>pink_outdoor</b> sudah diimport.</p>
    </div>");
}

$conn->set_charset('utf8mb4');
