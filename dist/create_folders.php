<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>📁 Buat Folder untuk TTE</h2>";
echo "<hr>";

$base_dir = __DIR__;

// Daftar folder yang dibutuhkan
$folders = [
    '/uploads',
    '/uploads/documents',
    '/uploads/signed',
    '/uploads/qr_temp',
    '/uploads/test'
];

echo "<h3>1. Membuat Folder</h3>";

foreach ($folders as $folder) {
    $full_path = $base_dir . $folder;
    
    if (!is_dir($full_path)) {
        if (mkdir($full_path, 0755, true)) {
            echo "✅ Folder <strong>$folder</strong> berhasil dibuat<br>";
        } else {
            echo "❌ Folder <strong>$folder</strong> gagal dibuat<br>";
        }
    } else {
        echo "✅ Folder <strong>$folder</strong> sudah ada<br>";
    }
    
    // Cek permission
    if (is_writable($full_path)) {
        echo "&nbsp;&nbsp;&nbsp;&nbsp;→ Writable ✅<br>";
    } else {
        echo "&nbsp;&nbsp;&nbsp;&nbsp;→ NOT Writable ❌ (Perlu chmod)<br>";
        
        // Coba chmod
        if (chmod($full_path, 0755)) {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;→ Berhasil di-chmod ke 0755 ✅<br>";
        } else {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;→ Gagal chmod (Lakukan manual via Terminal)<br>";
        }
    }
}

echo "<hr>";

// Test buat file di folder
echo "<h3>2. Test Write File</h3>";

$test_file = $base_dir . '/uploads/test/test_write_' . time() . '.txt';
$test_content = "Test write: " . date('Y-m-d H:i:s');

if (file_put_contents($test_file, $test_content)) {
    echo "✅ Berhasil membuat file test: <code>" . basename($test_file) . "</code><br>";
    
    if (file_exists($test_file)) {
        echo "&nbsp;&nbsp;&nbsp;&nbsp;→ File exist ✅<br>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;→ Content: " . file_get_contents($test_file) . "<br>";
        @unlink($test_file);
        echo "&nbsp;&nbsp;&nbsp;&nbsp;→ File test dihapus ✅<br>";
    }
} else {
    echo "❌ Gagal membuat file test<br>";
    echo "&nbsp;&nbsp;&nbsp;&nbsp;→ <strong>MASALAH PERMISSION!</strong><br>";
}

echo "<hr>";

// Cek struktur folder
echo "<h3>3. Struktur Folder Uploads</h3>";
echo "<pre>";
echo "fixpoint/dist/\n";
echo "├── bubuhkan_tte.php\n";
echo "├── lib/\n";
echo "│   ├── autoload.php\n";
echo "│   ├── fpdf/\n";
echo "│   ├── fpdi/\n";
echo "│   └── phpword/\n";
echo "└── uploads/\n";
echo "    ├── documents/     → File yang diupload user\n";
echo "    ├── signed/        → File hasil TTE (download dari sini)\n";
echo "    ├── qr_temp/       → QR Code temporary\n";
echo "    └── test/          → Test folder\n";
echo "</pre>";

echo "<hr>";

// Check PHP settings
echo "<h3>4. PHP Settings</h3>";

$settings = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'file_uploads' => ini_get('file_uploads') ? 'Enabled' : 'Disabled'
];

foreach ($settings as $key => $value) {
    echo "<strong>$key:</strong> $value<br>";
}

echo "<hr>";

echo "<h3>✅ Kesimpulan</h3>";
echo "<div style='background:#d4edda; padding:15px; border-left:4px solid #28a745;'>";
echo "<p><strong>Jika semua folder sudah dibuat dan writable:</strong></p>";
echo "<ol>";
echo "<li>Folder sudah siap untuk digunakan</li>";
echo "<li>Sekarang coba upload dokumen di <a href='bubuhkan_tte.php'>bubuhkan_tte.php</a></li>";
echo "<li>Jika masih error, aktifkan error reporting untuk lihat error detail</li>";
echo "</ol>";
echo "</div>";

echo "<br>";

echo "<div style='background:#fff3cd; padding:15px; border-left:4px solid #ffc107;'>";
echo "<p><strong>Jika ada folder yang NOT Writable:</strong></p>";
echo "<p>Jalankan command ini di Terminal:</p>";
echo "<code>cd /Applications/XAMPP/xamppfiles/htdocs/fixpoint/dist</code><br>";
echo "<code>chmod -R 755 uploads/</code><br>";
echo "</div>";

?>