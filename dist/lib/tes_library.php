<?php
/**
 * Test Library Installation - AUTO DETECT VERSION
 * Upload file ini ke root project (sejajar dengan bubuhkan_tte.php)
 */

// Auto-detect root directory
$current_dir = __DIR__;
$root_dir = $current_dir;

// Jika file ini ada di folder lib/, naik 1 level
if (basename($current_dir) == 'lib') {
    $root_dir = dirname($current_dir);
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Library Installation</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        .test-item { padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 5px solid #ccc; }
        .success { background: #d4edda; border-color: #28a745; color: #155724; }
        .error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffc107; color: #856404; }
        .info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
        .status { font-weight: bold; font-size: 18px; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
        .path-check { margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 13px; }
        .btn { background: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px; font-weight: bold; }
        .btn:hover { background: #45a049; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Test Library Installation</h1>
";

// Info lokasi file
echo "<div class='test-item info'>";
echo "<div class='status'>ℹ️ Lokasi File Saat Ini</div>";
echo "<p><strong>File test_library.php ada di:</strong> <code>" . $current_dir . "</code></p>";
echo "<p><strong>Root directory terdeteksi:</strong> <code>" . $root_dir . "</code></p>";
if ($current_dir != $root_dir) {
    echo "<p><span style='color: orange;'>⚠️ <strong>Peringatan:</strong> File ini sebaiknya dipindahkan ke root project.</span></p>";
}
echo "</div>";

// Test 1: Cek file autoload.php
$autoload_path = $root_dir . '/lib/autoload.php';
echo "<div class='test-item " . (file_exists($autoload_path) ? 'success' : 'error') . "'>";
echo "<div class='status'>1. File autoload.php: " . (file_exists($autoload_path) ? '✅ FOUND' : '❌ NOT FOUND') . "</div>";
echo "<p>Path: <code>" . $autoload_path . "</code></p>";
if (!file_exists($autoload_path)) {
    echo "<p><strong>Solusi:</strong> Buat file autoload.php di folder lib/</p>";
}
echo "</div>";

// Test 2: Cek folder FPDF
$fpdf_path = $root_dir . '/lib/fpdf/fpdf.php';
echo "<div class='test-item " . (file_exists($fpdf_path) ? 'success' : 'error') . "'>";
echo "<div class='status'>2. FPDF Library: " . (file_exists($fpdf_path) ? '✅ FOUND' : '❌ NOT FOUND') . "</div>";
echo "<p>Path: <code>" . $fpdf_path . "</code></p>";
if (!file_exists($fpdf_path)) {
    echo "<p><strong>Solusi:</strong> Download FPDF dan extract ke folder lib/fpdf/</p>";
}
echo "</div>";

// Test 3: Cek folder FPDI
$fpdi_paths = [
    $root_dir . '/lib/fpdi/src/Fpdi.php',
    $root_dir . '/lib/fpdi/Fpdi.php',
    $root_dir . '/lib/fpdi/src/setasign/Fpdi/Fpdi.php'
];
$fpdi_found = false;
foreach ($fpdi_paths as $path) {
    if (file_exists($path)) {
        $fpdi_found = $path;
        break;
    }
}
echo "<div class='test-item " . ($fpdi_found ? 'success' : 'error') . "'>";
echo "<div class='status'>3. FPDI Library: " . ($fpdi_found ? '✅ FOUND' : '❌ NOT FOUND') . "</div>";
if ($fpdi_found) {
    echo "<p>Ditemukan di: <code>" . $fpdi_found . "</code></p>";
} else {
    echo "<p>Dicari di paths berikut:</p><ul style='margin-left: 20px;'>";
    foreach ($fpdi_paths as $path) {
        echo "<li><code>$path</code></li>";
    }
    echo "</ul><p><strong>Solusi:</strong> Download FPDI dari GitHub dan extract folder src/ ke lib/fpdi/</p>";
}
echo "</div>";

// Test 4: Cek folder PHPWord
$phpword_paths = [
    $root_dir . '/lib/phpword/src/PhpWord/IOFactory.php',
    $root_dir . '/lib/phpword/PhpWord/IOFactory.php',
    $root_dir . '/lib/phpword/src/PhpOffice/PhpWord/IOFactory.php'
];
$phpword_found = false;
foreach ($phpword_paths as $path) {
    if (file_exists($path)) {
        $phpword_found = $path;
        break;
    }
}
echo "<div class='test-item " . ($phpword_found ? 'success' : 'error') . "'>";
echo "<div class='status'>4. PHPWord Library: " . ($phpword_found ? '✅ FOUND' : '❌ NOT FOUND') . "</div>";
if ($phpword_found) {
    echo "<p>Ditemukan di: <code>" . $phpword_found . "</code></p>";
} else {
    echo "<p>Dicari di paths berikut:</p><ul style='margin-left: 20px;'>";
    foreach ($phpword_paths as $path) {
        echo "<li><code>$path</code></li>";
    }
    echo "</ul><p><strong>Solusi:</strong> Download PHPWord dari GitHub dan extract folder src/ ke lib/phpword/</p>";
}
echo "</div>";

// Test 5: Cek isi folder lib
echo "<div class='test-item info'>";
echo "<div class='status'>📂 Isi Folder lib/</div>";
$lib_dir = $root_dir . '/lib';
if (is_dir($lib_dir)) {
    $items = scandir($lib_dir);
    echo "<ul style='margin-left: 20px;'>";
    foreach ($items as $item) {
        if ($item != '.' && $item != '..') {
            $icon = is_dir($lib_dir . '/' . $item) ? '📁' : '📄';
            echo "<li>$icon <code>$item</code></li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>❌ Folder lib/ tidak ditemukan!</p>";
}
echo "</div>";

// Test 6: Load autoloader dan cek class
if (file_exists($autoload_path)) {
    require_once($autoload_path);
    
    echo "<div class='test-item " . (class_exists('FPDF') ? 'success' : 'error') . "'>";
    echo "<div class='status'>5. FPDF Class: " . (class_exists('FPDF') ? '✅ LOADED' : '❌ NOT LOADED') . "</div>";
    echo "</div>";
    
    echo "<div class='test-item " . (class_exists('setasign\\Fpdi\\Fpdi') ? 'success' : 'error') . "'>";
    echo "<div class='status'>6. FPDI Class: " . (class_exists('setasign\\Fpdi\\Fpdi') ? '✅ LOADED' : '❌ NOT LOADED') . "</div>";
    if (!class_exists('setasign\\Fpdi\\Fpdi')) {
        echo "<p><strong>Error:</strong> Class tidak bisa di-load. Periksa struktur folder FPDI.</p>";
    }
    echo "</div>";
    
    echo "<div class='test-item " . (class_exists('PhpOffice\\PhpWord\\IOFactory') ? 'success' : 'error') . "'>";
    echo "<div class='status'>7. PHPWord Class: " . (class_exists('PhpOffice\\PhpWord\\IOFactory') ? '✅ LOADED' : '❌ NOT LOADED') . "</div>";
    if (!class_exists('PhpOffice\\PhpWord\\IOFactory')) {
        echo "<p><strong>Error:</strong> Class tidak bisa di-load. Periksa struktur folder PHPWord.</p>";
    }
    echo "</div>";
} else {
    echo "<div class='test-item warning'>";
    echo "<div class='status'>⚠️ Autoloader tidak ditemukan</div>";
    echo "<p>Tidak bisa test loading class karena file autoload.php tidak ada.</p>";
    echo "</div>";
}

// Informasi Path
echo "<div class='path-check'>";
echo "<h3>📁 Struktur Folder yang Diharapkan</h3>";
echo "<pre>" . $root_dir . "/
├── bubuhkan_tte.php
├── test_library.php (file ini)
└── lib/
    ├── autoload.php
    ├── fpdf/
    │   └── fpdf.php
    ├── fpdi/
    │   └── src/
    │       └── Fpdi.php
    └── phpword/
        └── src/
            └── PhpWord/
                └── IOFactory.php
</pre>";
echo "</div>";

// Kesimpulan
$all_ok = file_exists($autoload_path) && 
          file_exists($fpdf_path) && 
          $fpdi_found && 
          $phpword_found;

if (file_exists($autoload_path)) {
    $all_ok = $all_ok && class_exists('FPDF') && class_exists('setasign\\Fpdi\\Fpdi');
}

if ($all_ok) {
    echo "<div class='test-item success'>";
    echo "<h2>🎉 SEMUA LIBRARY TERINSTALL DENGAN BENAR!</h2>";
    echo "<p>Anda sekarang bisa menggunakan fitur bubuhkan TTE.</p>";
    echo "<p><a href='../bubuhkan_tte.php' class='btn'>Mulai Bubuhkan TTE →</a></p>";
    echo "</div>";
} else {
    echo "<div class='test-item error'>";
    echo "<h2>❌ ADA LIBRARY YANG BELUM TERINSTALL</h2>";
    echo "<p>Perbaiki error di atas terlebih dahulu. Lihat solusi di setiap item yang error.</p>";
    echo "</div>";
}

echo "
    </div>
</body>
</html>";
?>