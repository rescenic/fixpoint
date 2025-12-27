<?php
/**
 * Check FPDF Folder Structure
 * Simpan file ini di: /Applications/XAMPP/xamppfiles/htdocs/fixpoint/dist/
 * Akses: http://localhost/fixpoint/dist/check_fpdf.php
 */

$root = __DIR__;
$fpdf_dir = $root . '/lib/fpdf';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Check FPDF Structure</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        h1 { color: #333; border-bottom: 3px solid #2196F3; padding-bottom: 10px; }
        .path { background: #f4f4f4; padding: 10px; border-radius: 5px; margin: 10px 0; font-family: monospace; }
        .file-tree { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; font-family: monospace; }
        .solution { background: #fff3cd; border-left: 5px solid #ffc107; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Check FPDF Folder Structure</h1>
";

echo "<h3>📂 Path yang dicari:</h3>";
echo "<div class='path'>" . $fpdf_dir . "/fpdf.php</div>";

if (!is_dir($fpdf_dir)) {
    echo "<p style='color: red;'><strong>❌ Folder fpdf tidak ditemukan!</strong></p>";
    echo "<p>Path: <code>$fpdf_dir</code></p>";
} else {
    echo "<h3>📁 Isi folder fpdf/:</h3>";
    echo "<div class='file-tree'>";
    
    function listDirectory($dir, $prefix = '') {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            
            $path = $dir . '/' . $item;
            $icon = is_dir($path) ? '📁' : '📄';
            
            echo $prefix . $icon . ' ' . $item;
            
            if (is_dir($path)) {
                echo " (folder)\n";
                listDirectory($path, $prefix . '  ');
            } else {
                $size = filesize($path);
                echo " (" . number_format($size) . " bytes)\n";
            }
        }
    }
    
    listDirectory($fpdf_dir);
    echo "</div>";
    
    // Cari file fpdf.php
    echo "<h3>🔎 Mencari file fpdf.php...</h3>";
    
    function findFile($dir, $filename, &$found = []) {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            
            $path = $dir . '/' . $item;
            
            if (is_file($path) && strtolower($item) == strtolower($filename)) {
                $found[] = $path;
            }
            
            if (is_dir($path)) {
                findFile($path, $filename, $found);
            }
        }
        return $found;
    }
    
    $found_files = findFile($fpdf_dir, 'fpdf.php');
    
    if (empty($found_files)) {
        echo "<p style='color: red;'><strong>❌ File fpdf.php tidak ditemukan di dalam folder fpdf/</strong></p>";
        
        echo "<div class='solution'>";
        echo "<h3>💡 SOLUSI:</h3>";
        echo "<ol>";
        echo "<li><strong>Download FPDF:</strong><br>
              Kunjungi: <a href='http://www.fpdf.org/en/download.php' target='_blank'>http://www.fpdf.org/en/download.php</a><br>
              Download file: <code>fpdf186.zip</code> (atau versi terbaru)</li>";
        echo "<li><strong>Extract file ZIP</strong></li>";
        echo "<li><strong>Struktur setelah extract biasanya seperti ini:</strong><br>
              <pre style='background: #f4f4f4; padding: 10px; border-radius: 5px; color: #333;'>fpdf186/
├── fpdf.php          ← INI FILE YANG DICARI
├── font/
├── doc/
└── tutorial/</pre></li>";
        echo "<li><strong>Copy file fpdf.php</strong> ke:<br>
              <code>" . $fpdf_dir . "/fpdf.php</code></li>";
        echo "<li><strong>ATAU</strong> rename folder <code>fpdf186</code> menjadi <code>fpdf</code> lalu pindahkan ke folder lib/</li>";
        echo "</ol>";
        echo "</div>";
        
    } else {
        echo "<p style='color: green;'><strong>✅ File fpdf.php ditemukan!</strong></p>";
        foreach ($found_files as $file) {
            echo "<div class='path'>$file</div>";
        }
        
        $correct_path = $fpdf_dir . '/fpdf.php';
        if ($found_files[0] != $correct_path) {
            echo "<div class='solution'>";
            echo "<h3>⚠️ PERHATIAN:</h3>";
            echo "<p>File fpdf.php ditemukan di lokasi yang salah.</p>";
            echo "<p><strong>File ada di:</strong><br><code>" . $found_files[0] . "</code></p>";
            echo "<p><strong>Seharusnya di:</strong><br><code>$correct_path</code></p>";
            echo "<p><strong>Solusi:</strong> Pindahkan atau copy file fpdf.php ke lokasi yang benar.</p>";
            echo "</div>";
        }
    }
}

echo "
    </div>
</body>
</html>";
?>