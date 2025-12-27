<?php
// FILE DEBUG UNTUK CEK MASALAH BUBUHKAN TTE
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Debug Bubuhkan TTE</h2>";
echo "<hr>";

// 1. CEK STRUKTUR FOLDER
echo "<h3>1. Struktur Folder</h3>";
$base_dir = __DIR__;
echo "Base Directory: <strong>" . $base_dir . "</strong><br>";
echo "File ini ada di: <strong>" . __FILE__ . "</strong><br><br>";

// 2. CEK AUTOLOAD
echo "<h3>2. Cek Autoload</h3>";
$autoload_path = $base_dir . '/lib/autoload.php';
echo "Path autoload: <strong>" . $autoload_path . "</strong><br>";
if (file_exists($autoload_path)) {
    echo "✅ File autoload.php DITEMUKAN<br>";
    echo "Mencoba load autoload...<br>";
    try {
        require_once($autoload_path);
        echo "✅ Autoload berhasil di-load<br>";
    } catch (Exception $e) {
        echo "❌ Error load autoload: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ File autoload.php TIDAK DITEMUKAN<br>";
}
echo "<br>";

// 3. CEK CLASS FPDI
echo "<h3>3. Cek Class FPDI</h3>";
$fpdi_classes = [
    'Fpdi\Fpdi',
    'setasign\Fpdi\Fpdi',
    'FPDI'
];

foreach ($fpdi_classes as $class) {
    if (class_exists($class)) {
        echo "✅ Class <strong>$class</strong> TERSEDIA<br>";
        echo "   → Gunakan class ini di bubuhkan_tte.php<br>";
    } else {
        echo "❌ Class <strong>$class</strong> TIDAK TERSEDIA<br>";
    }
}
echo "<br>";

// 4. CEK CLASS PHPWord
echo "<h3>4. Cek Class PHPWord</h3>";
if (class_exists('PhpOffice\PhpWord\IOFactory')) {
    echo "✅ Class PHPWord IOFactory TERSEDIA<br>";
} else {
    echo "❌ Class PHPWord IOFactory TIDAK TERSEDIA<br>";
}
echo "<br>";

// 5. TEST BUAT PDF SEDERHANA
echo "<h3>5. Test Membuat PDF Sederhana</h3>";
try {
    // Cek class mana yang tersedia
    if (class_exists('Fpdi\Fpdi')) {
        $pdf = new \Fpdi\Fpdi();
        $class_used = 'Fpdi\Fpdi';
    } elseif (class_exists('setasign\Fpdi\Fpdi')) {
        $pdf = new \setasign\Fpdi\Fpdi();
        $class_used = 'setasign\Fpdi\Fpdi';
    } elseif (class_exists('FPDI')) {
        $pdf = new FPDI();
        $class_used = 'FPDI';
    } else {
        throw new Exception("Tidak ada class FPDI yang tersedia");
    }
    
    echo "✅ Berhasil membuat object FPDI menggunakan class: <strong>$class_used</strong><br>";
    echo "   → Pastikan bubuhkan_tte.php menggunakan class yang sama<br>";
    
    // Test buat PDF sederhana
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(40, 10, 'Test PDF');
    
    $test_dir = $base_dir . '/uploads/test/';
    if (!is_dir($test_dir)) {
        mkdir($test_dir, 0755, true);
    }
    
    $test_file = $test_dir . 'test_fpdi.pdf';
    $pdf->Output('F', $test_file);
    
    if (file_exists($test_file)) {
        echo "✅ Berhasil membuat file test PDF: <a href='/fixpoint/dist/uploads/test/test_fpdi.pdf' target='_blank'>Download Test PDF</a><br>";
    } else {
        echo "❌ File test PDF gagal dibuat<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
echo "<br>";

// 6. CEK PERMISSION FOLDER UPLOADS
echo "<h3>6. Cek Permission Folder Uploads</h3>";
$upload_folders = [
    'uploads/',
    'uploads/documents/',
    'uploads/signed/',
    'uploads/qr_temp/'
];

foreach ($upload_folders as $folder) {
    $full_path = $base_dir . '/' . $folder;
    if (!is_dir($full_path)) {
        if (mkdir($full_path, 0755, true)) {
            echo "✅ Folder <strong>$folder</strong> berhasil dibuat<br>";
        } else {
            echo "❌ Folder <strong>$folder</strong> gagal dibuat<br>";
        }
    } else {
        echo "✅ Folder <strong>$folder</strong> sudah ada<br>";
    }
    
    if (is_writable($full_path)) {
        echo "   → Folder writable ✅<br>";
    } else {
        echo "   → Folder NOT writable ❌<br>";
    }
}
echo "<br>";

// 7. SIMULASI PROSES UPLOAD
echo "<h3>7. Test Simulasi Upload & Proses</h3>";
echo "<form method='POST' enctype='multipart/form-data'>";
echo "<input type='file' name='test_file' accept='.pdf'><br><br>";
echo "<button type='submit' name='test_upload'>Test Upload PDF</button>";
echo "</form><br>";

if (isset($_POST['test_upload']) && isset($_FILES['test_file'])) {
    echo "<strong>Testing upload...</strong><br>";
    $file = $_FILES['test_file'];
    
    echo "File name: " . $file['name'] . "<br>";
    echo "File type: " . $file['type'] . "<br>";
    echo "File size: " . ($file['size'] / 1024) . " KB<br>";
    echo "Error code: " . $file['error'] . "<br><br>";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $upload_dir = $base_dir . '/uploads/test/';
        $filename = 'uploaded_' . time() . '.pdf';
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            echo "✅ File berhasil diupload ke: $filepath<br><br>";
            
            // Test proses dengan FPDI
            echo "<strong>Mencoba proses dengan FPDI...</strong><br>";
            try {
                if (class_exists('Fpdi\Fpdi')) {
                    $pdf = new \Fpdi\Fpdi();
                    $class_used = 'Fpdi\Fpdi';
                } elseif (class_exists('setasign\Fpdi\Fpdi')) {
                    $pdf = new \setasign\Fpdi\Fpdi();
                    $class_used = 'setasign\Fpdi\Fpdi';
                } elseif (class_exists('FPDI')) {
                    $pdf = new FPDI();
                    $class_used = 'FPDI';
                }
                
                echo "Menggunakan class: $class_used<br>";
                
                $pageCount = $pdf->setSourceFile($filepath);
                echo "✅ Jumlah halaman: $pageCount<br>";
                
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $tplIdx = $pdf->importPage($pageNo);
                    $pdf->AddPage();
                    $pdf->useTemplate($tplIdx);
                    
                    if ($pageNo == $pageCount) {
                        // Tambah text di halaman terakhir
                        $pdf->SetFont('Arial', 'B', 12);
                        $pdf->SetXY(10, 10);
                        $pdf->Cell(0, 10, 'DOKUMEN SUDAH DIPROSES', 0, 0, 'C');
                    }
                }
                
                $output_file = $upload_dir . 'processed_' . time() . '.pdf';
                $pdf->Output('F', $output_file);
                
                if (file_exists($output_file)) {
                    echo "✅ File berhasil diproses!<br>";
                    echo "<a href='/fixpoint/dist/uploads/test/" . basename($output_file) . "' target='_blank'>Download Hasil Proses</a><br>";
                } else {
                    echo "❌ File hasil proses tidak ditemukan<br>";
                }
                
            } catch (Exception $e) {
                echo "❌ Error saat proses: " . $e->getMessage() . "<br>";
                echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre><br>";
            }
            
        } else {
            echo "❌ Gagal upload file<br>";
        }
    } else {
        echo "❌ Upload error: " . $file['error'] . "<br>";
    }
}

echo "<hr>";
echo "<h3>📋 Kesimpulan & Rekomendasi</h3>";
echo "<p>Berdasarkan hasil test di atas, pastikan:</p>";
echo "<ol>";
echo "<li>Class FPDI yang tersedia (lihat bagian 3)</li>";
echo "<li>Gunakan class yang sama di <strong>bubuhkan_tte.php</strong></li>";
echo "<li>Semua folder uploads writable</li>";
echo "<li>Test upload berfungsi dengan baik</li>";
echo "</ol>";
?>