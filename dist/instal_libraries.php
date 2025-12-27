<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Installer TTE Libraries</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { color: #333; margin-bottom: 30px; }
        .alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-info { background: #d1ecf1; border-left: 4px solid #0c5460; }
        .alert-success { background: #d4edda; border-left: 4px solid #28a745; }
        .alert-danger { background: #f8d7da; border-left: 4px solid #dc3545; }
        .alert-warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        .progress-box {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin: 20px 0;
        }
        .step {
            padding: 15px;
            margin: 10px 0;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
        }
        .step-pending { background: #e9ecef; color: #6c757d; }
        .step-running { background: #ffc107; color: white; }
        .step-success { background: #28a745; color: white; }
        .step-error { background: #dc3545; color: white; }
        .step-content { flex-grow: 1; }
        .btn {
            display: inline-block;
            padding: 15px 40px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        .btn:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .log-box {
            background: #1e1e1e;
            color: #00ff00;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
            margin: 20px 0;
        }
        .log-line { margin-bottom: 5px; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Auto Installer TTE Libraries</h1>

        <?php
        $action = $_GET['action'] ?? '';
        $log_messages = [];
        
        function addLog($message, $type = 'info') {
            global $log_messages;
            $log_messages[] = [
                'time' => date('H:i:s'),
                'type' => $type,
                'message' => $message
            ];
        }

        function downloadFile($url, $destination) {
            addLog("Downloading from: $url");
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            
            $data = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($error) {
                addLog("CURL Error: $error", 'error');
                return false;
            }
            
            if ($httpCode !== 200) {
                addLog("HTTP Error: $httpCode", 'error');
                return false;
            }
            
            if (file_put_contents($destination, $data) === false) {
                addLog("Failed to save file: $destination", 'error');
                return false;
            }
            
            addLog("Downloaded: " . basename($destination) . " (" . number_format(strlen($data)) . " bytes)", 'success');
            return true;
        }

        function extractZip($zipFile, $extractTo) {
            addLog("Extracting: $zipFile");
            
            $zip = new ZipArchive;
            if ($zip->open($zipFile) === TRUE) {
                $zip->extractTo($extractTo);
                $zip->close();
                addLog("Extracted to: $extractTo", 'success');
                return true;
            } else {
                addLog("Failed to extract: $zipFile", 'error');
                return false;
            }
        }

        if ($action === 'install') {
            addLog("Starting installation process...", 'info');
            
            // Cek ZIP extension
            if (!extension_loaded('zip')) {
                addLog("ERROR: ZIP extension not available!", 'error');
                echo '<div class="alert alert-danger">
                    <strong>Error!</strong> ZIP extension tidak tersedia. Install terlebih dahulu atau hubungi hosting provider.
                </div>';
            } else {
                $success = true;
                
                // 1. Create lib folder
                addLog("Creating lib directories...");
                $lib_dir = __DIR__ . '/lib';
                if (!is_dir($lib_dir)) {
                    mkdir($lib_dir, 0755, true);
                    addLog("Created: lib/", 'success');
                }
                
                // 2. Download FPDF
                addLog("=== INSTALLING FPDF ===");
                $fpdf_url = "http://www.fpdf.org/en/dl.php?v=185&f=zip";
                $fpdf_zip = $lib_dir . '/fpdf.zip';
                $fpdf_dir = $lib_dir . '/fpdf';
                
                if (downloadFile($fpdf_url, $fpdf_zip)) {
                    if (!is_dir($fpdf_dir)) mkdir($fpdf_dir, 0755, true);
                    if (extractZip($fpdf_zip, $fpdf_dir)) {
                        unlink($fpdf_zip);
                        addLog("FPDF installed successfully!", 'success');
                    } else {
                        $success = false;
                    }
                } else {
                    addLog("Failed to download FPDF", 'error');
                    $success = false;
                }
                
                // 3. Download FPDI
                addLog("=== INSTALLING FPDI ===");
                $fpdi_url = "https://github.com/Setasign/FPDI/archive/refs/tags/v2.3.7.zip";
                $fpdi_zip = $lib_dir . '/fpdi.zip';
                $fpdi_dir = $lib_dir . '/fpdi';
                
                if (downloadFile($fpdi_url, $fpdi_zip)) {
                    $temp_extract = $lib_dir . '/fpdi_temp';
                    if (!is_dir($temp_extract)) mkdir($temp_extract, 0755, true);
                    
                    if (extractZip($fpdi_zip, $temp_extract)) {
                        // Move dari fpdi_temp/FPDI-2.3.7/ ke fpdi/
                        $source = $temp_extract . '/FPDI-2.3.7';
                        if (is_dir($source)) {
                            rename($source, $fpdi_dir);
                            unlink($fpdi_zip);
                            @rmdir($temp_extract);
                            addLog("FPDI installed successfully!", 'success');
                        }
                    } else {
                        $success = false;
                    }
                } else {
                    addLog("Failed to download FPDI", 'error');
                    $success = false;
                }
                
                // 4. Download PHPWord
                addLog("=== INSTALLING PHPWord ===");
                $phpword_url = "https://github.com/PHPOffice/PHPWord/archive/refs/tags/1.1.0.zip";
                $phpword_zip = $lib_dir . '/phpword.zip';
                $phpword_dir = $lib_dir . '/phpword';
                
                if (downloadFile($phpword_url, $phpword_zip)) {
                    $temp_extract = $lib_dir . '/phpword_temp';
                    if (!is_dir($temp_extract)) mkdir($temp_extract, 0755, true);
                    
                    if (extractZip($phpword_zip, $temp_extract)) {
                        // Move dari phpword_temp/PHPWord-1.1.0/ ke phpword/
                        $source = $temp_extract . '/PHPWord-1.1.0';
                        if (is_dir($source)) {
                            rename($source, $phpword_dir);
                            unlink($phpword_zip);
                            @rmdir($temp_extract);
                            addLog("PHPWord installed successfully!", 'success');
                        }
                    } else {
                        $success = false;
                    }
                } else {
                    addLog("Failed to download PHPWord", 'error');
                    $success = false;
                }
                
                // 5. Create autoload.php
                addLog("Creating autoload.php...");
                $autoload_content = <<<'PHP'
<?php
// lib/autoload.php - Auto-generated

// Load FPDF
if (file_exists(__DIR__ . '/fpdf/fpdf.php')) {
    require_once __DIR__ . '/fpdf/fpdf.php';
}

// Load FPDI
if (file_exists(__DIR__ . '/fpdi/src/autoload.php')) {
    require_once __DIR__ . '/fpdi/src/autoload.php';
}

// Load PHPWord
if (file_exists(__DIR__ . '/phpword/src/PhpWord/Autoloader.php')) {
    require_once __DIR__ . '/phpword/src/PhpWord/Autoloader.php';
    \PhpOffice\PhpWord\Autoloader::register();
}
PHP;
                
                file_put_contents($lib_dir . '/autoload.php', $autoload_content);
                addLog("autoload.php created!", 'success');
                
                // 6. Create upload folders
                addLog("Creating upload folders...");
                $folders = ['uploads/documents', 'uploads/qr_temp'];
                foreach ($folders as $folder) {
                    if (!is_dir($folder)) {
                        mkdir($folder, 0755, true);
                        addLog("Created: $folder/", 'success');
                    }
                }
                
                addLog("=== INSTALLATION COMPLETE ===", 'success');
                
                if ($success) {
                    echo '<div class="alert alert-success">
                        <h3>✅ Instalasi Berhasil!</h3>
                        <p>Semua library telah terinstall. Silakan test dengan mengklik tombol di bawah.</p>
                    </div>';
                    echo '<div class="text-center">
                        <a href="test_libraries.php" class="btn">Test Libraries →</a>
                    </div>';
                } else {
                    echo '<div class="alert alert-warning">
                        <h3>⚠️ Instalasi Selesai dengan Warning</h3>
                        <p>Beberapa library mungkin gagal diinstall. Silakan cek log di bawah.</p>
                    </div>';
                }
                
                // Show log
                echo '<div class="log-box">';
                foreach ($log_messages as $log) {
                    $color = $log['type'] === 'error' ? '#ff6b6b' : ($log['type'] === 'success' ? '#51cf66' : '#00ff00');
                    echo '<div class="log-line" style="color: ' . $color . '">[' . $log['time'] . '] ' . htmlspecialchars($log['message']) . '</div>';
                }
                echo '</div>';
            }
            
        } else {
            // Show intro page
            ?>
            <div class="alert alert-info">
                <h3>ℹ️ Tentang Installer Ini</h3>
                <p>Installer ini akan otomatis mendownload dan menginstall library yang diperlukan untuk sistem TTE:</p>
                <ul style="margin: 15px 0 0 20px;">
                    <li><strong>FPDF</strong> - Library dasar untuk PDF (≈300 KB)</li>
                    <li><strong>FPDI</strong> - Library untuk edit PDF existing (≈500 KB)</li>
                    <li><strong>PHPWord</strong> - Library untuk Word documents (≈5 MB)</li>
                </ul>
            </div>

            <div class="alert alert-warning">
                <h3>⚠️ Persyaratan</h3>
                <ul style="margin: 10px 0 0 20px;">
                    <li>PHP >= 7.4</li>
                    <li>ZIP Extension harus aktif</li>
                    <li>cURL Extension harus aktif</li>
                    <li>Koneksi internet</li>
                    <li>Write permission di folder project</li>
                </ul>
            </div>

            <?php
            // Cek requirements
            $can_install = true;
            $issues = [];
            
            if (version_compare(phpversion(), '7.4.0', '<')) {
                $can_install = false;
                $issues[] = "PHP version terlalu lama (current: " . phpversion() . ", required: >= 7.4)";
            }
            
            if (!extension_loaded('zip')) {
                $can_install = false;
                $issues[] = "ZIP extension tidak tersedia";
            }
            
            if (!extension_loaded('curl')) {
                $can_install = false;
                $issues[] = "cURL extension tidak tersedia";
            }
            
            if (!is_writable(__DIR__)) {
                $can_install = false;
                $issues[] = "Folder tidak writable - run: chmod 755 " . __DIR__;
            }
            
            if (!$can_install) {
                echo '<div class="alert alert-danger">
                    <h3>❌ Tidak Bisa Install</h3>
                    <p>Ada beberapa requirement yang belum terpenuhi:</p>
                    <ul style="margin: 10px 0 0 20px;">';
                foreach ($issues as $issue) {
                    echo '<li>' . htmlspecialchars($issue) . '</li>';
                }
                echo '</ul>
                    <p style="margin-top: 15px;">Silakan perbaiki issue di atas terlebih dahulu.</p>
                </div>';
            } else {
                echo '<div class="alert alert-success">
                    <h3>✅ Semua Requirement Terpenuhi</h3>
                    <p>Siap untuk instalasi! Klik tombol di bawah untuk memulai.</p>
                </div>';
                
                echo '<div class="text-center">
                    <a href="?action=install" class="btn">🚀 Mulai Instalasi</a>
                </div>';
            }
            ?>

            <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                <h4>ℹ️ Informasi Server</h4>
                <div style="font-size: 13px; color: #666; margin-top: 10px;">
                    <strong>PHP Version:</strong> <?= phpversion() ?><br>
                    <strong>ZIP Extension:</strong> <?= extension_loaded('zip') ? '✓ Aktif' : '✗ Tidak Aktif' ?><br>
                    <strong>cURL Extension:</strong> <?= extension_loaded('curl') ? '✓ Aktif' : '✗ Tidak Aktif' ?><br>
                    <strong>Folder Writable:</strong> <?= is_writable(__DIR__) ? '✓ Yes' : '✗ No' ?><br>
                </div>
            </div>

            <div class="alert alert-info" style="margin-top: 20px;">
                <h4>💡 Opsi Manual</h4>
                <p>Jika auto installer tidak berhasil, Anda bisa install secara manual:</p>
                <ol style="margin: 10px 0 0 20px;">
                    <li>Install via Composer (jika tersedia):
                        <pre style="background: #1e1e1e; color: #00ff00; padding: 10px; border-radius: 5px; margin-top: 5px;">composer require setasign/fpdf
composer require setasign/fpdi
composer require phpoffice/phpword</pre>
                    </li>
                    <li>Atau lihat panduan manual di <a href="INSTALASI_LIBRARY.md" target="_blank">INSTALASI_LIBRARY.md</a></li>
                </ol>
            </div>
            <?php
        }
        ?>
    </div>
</body>
</html>