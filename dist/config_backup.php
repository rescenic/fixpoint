<?php
// File ini berisi konstanta untuk koneksi database
// Sesuaikan dengan setting di file koneksi.php Anda

// Jika di koneksi.php Anda belum ada konstanta, tambahkan ini:
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost'); // Sesuaikan dengan host database Anda
}

if (!defined('DB_USER')) {
    define('DB_USER', 'root'); // Sesuaikan dengan username database Anda
}

if (!defined('DB_PASS')) {
    define('DB_PASS', ''); // Sesuaikan dengan password database Anda
}

if (!defined('DB_NAME')) {
    define('DB_NAME', 'fixpoint_system'); // Sesuaikan dengan nama database Anda
}
?>