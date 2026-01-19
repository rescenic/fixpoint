<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    header("Location: login.php");
    exit;
}


$current_file = basename(__FILE__);
$query = "SELECT 1 FROM akses_menu 
          JOIN menu ON akses_menu.menu_id = menu.id 
          WHERE akses_menu.user_id = ? AND menu.file_menu = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("is", $user_id, $current_file);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
    header("Location: dashboard.php");
    exit;
}


$autoload_file = __DIR__ . '/lib/autoload.php';
if (file_exists($autoload_file)) {
    require_once $autoload_file;
}

function scanQRWithAPI1($imagePath) {
    try {
        if (!file_exists($imagePath)) return [];
        
        $ch = curl_init();
        $cfile = new CURLFile($imagePath, mime_content_type($imagePath), basename($imagePath));
        
        curl_setopt($ch, CURLOPT_URL, "https://api.qrserver.com/v1/read-qr-code/");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $cfile]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0']);
        
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpcode == 200 && $response) {
            $result = json_decode($response, true);
            $qrCodes = [];
            
    
            if (isset($result[0]['symbol']) && is_array($result[0]['symbol'])) {
                foreach ($result[0]['symbol'] as $symbol) {
                    if (isset($symbol['data']) && !empty($symbol['data'])) {
                        $qrCodes[] = trim($symbol['data']);
                    }
                }
            }
            
            return $qrCodes;
        }
    } catch (Exception $e) {}
    return [];
}


function generateFileHash($filePath) {
    if (!file_exists($filePath)) return false;
    return hash_file('sha256', $filePath);
}


function extractTextFromPDF($pdfPath) {
    $tokens = [];
    
    // METHOD 1: Try pdftotext (best for content extraction)
    if (function_exists('shell_exec')) {
        $pdftotext = shell_exec('which pdftotext 2>/dev/null');
        if (!empty($pdftotext)) {
            try {
                $output = shell_exec("pdftotext " . escapeshellarg($pdfPath) . " - 2>&1");
                if ($output) {
                    // Cari pattern TTE-TOKEN: atau cek_tte.php?token=
                    preg_match_all('/(?:TTE-TOKEN:|token=)([a-f0-9]{32,64})/i', $output, $matches);
                    if (!empty($matches[1])) {
                        $tokens = array_merge($tokens, $matches[1]);
                    }
                }
            } catch (Exception $e) {}
        }
    }
    
    // METHOD 2: Try pdfinfo for metadata extraction (STANDAR)
    if (empty($tokens) && function_exists('shell_exec')) {
        $pdfinfo = shell_exec('which pdfinfo 2>/dev/null');
        if (!empty($pdfinfo)) {
            try {
                $output = shell_exec("pdfinfo " . escapeshellarg($pdfPath) . " 2>&1");
                if ($output) {
                    // Extract from Subject, Keywords, or other metadata
                    preg_match_all('/(?:Subject|Keywords|Description):\s*([^\n]+)/i', $output, $meta_lines);
                    if (!empty($meta_lines[1])) {
                        foreach ($meta_lines[1] as $line) {
                            preg_match_all('/(?:TTE-TOKEN:|TOKEN:)([a-f0-9]{32,64})/i', $line, $matches);
                            if (!empty($matches[1])) {
                                $tokens = array_merge($tokens, $matches[1]);
                            }
                        }
                    }
                }
            } catch (Exception $e) {}
        }
    }
    
    // METHOD 3: Raw file content scan (fallback)
    if (empty($tokens)) {
        $content = file_get_contents($pdfPath);
        if ($content) {
            preg_match_all('/cek_tte\.php\?token=([a-f0-9]{32,64})/i', $content, $matches);
            if (!empty($matches[1])) {
                $tokens = array_merge($tokens, $matches[1]);
            }
            
            preg_match_all('/TTE-TOKEN:([a-f0-9]{32,64})/i', $content, $matches);
            if (!empty($matches[1])) {
                $tokens = array_merge($tokens, $matches[1]);
            }
        }
    }

    return array_values(array_unique($tokens));
}


function extractTextFromWord($docPath) {
    $tokens = [];
    
    if (!class_exists('PhpOffice\\PhpWord\\IOFactory')) {
        return $tokens;
    }
    
    try {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($docPath);

        // METHOD 1: Extract from document content
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . ' ';
                } elseif (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $childElement) {
                        if (method_exists($childElement, 'getText')) {
                            $text .= $childElement->getText() . ' ';
                        }
                    }
                }
            }
        }

        if (!empty($text)) {
            preg_match_all('/cek_tte\.php\?token=([a-f0-9]{32,64})/i', $text, $matches);
            if (!empty($matches[1])) {
                $tokens = array_merge($tokens, $matches[1]);
            }
            preg_match_all('/TTE-TOKEN:([a-f0-9]{32,64})/i', $text, $matches);
            if (!empty($matches[1])) {
                $tokens = array_merge($tokens, $matches[1]);
            }
        }
        
        // METHOD 2: Extract from document metadata (STANDAR)
        if (empty($tokens)) {
            $docInfo = $phpWord->getDocInfo();
            
            // Try Description field
            $description = $docInfo->getDescription();
            if (!empty($description)) {
                preg_match_all('/(?:TTE-TOKEN:|TOKEN:)([a-f0-9]{32,64})/i', $description, $matches);
                if (!empty($matches[1])) {
                    $tokens = array_merge($tokens, $matches[1]);
                }
            }
            
            // Try Keywords field
            if (empty($tokens)) {
                $keywords = $docInfo->getKeywords();
                if (!empty($keywords)) {
                    preg_match_all('/(?:TTE-TOKEN:|TOKEN:)([a-f0-9]{32,64})/i', $keywords, $matches);
                    if (!empty($matches[1])) {
                        $tokens = array_merge($tokens, $matches[1]);
                    }
                }
            }
        }
        
    } catch (Exception $e) {}
    
    return array_values(array_unique($tokens));
}

if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
    header('Content-Type: application/json');
    
    $response = [
        'success' => false,
        'message' => '',
        'data' => null
    ];
    
    try {
        if (!isset($_FILES['file_qr']) || $_FILES['file_qr']['error'] !== UPLOAD_ERR_OK) {
            $response['message'] = "Upload gagal. Silakan coba lagi.";
            echo json_encode($response);
            exit;
        }
        
        $file = $_FILES['file_qr'];
        $allowed = [
            'image/jpeg', 
            'image/jpg', 
            'image/png', 
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (!in_array($file['type'], $allowed)) {
            $response['message'] = "Format tidak valid. Gunakan JPG, PNG, PDF, atau DOCX.";
            echo json_encode($response);
            exit;
        }
        
        if ($file['size'] > 10485760) {
            $response['message'] = "Ukuran file maksimal 10MB.";
            echo json_encode($response);
            exit;
        }
        
        // Upload file
        $upload_dir = 'uploads/temp/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('doc_', true) . '.' . $ext;
        $filepath = $upload_dir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $response['message'] = "Gagal menyimpan file. Periksa permission folder uploads/temp/";
            echo json_encode($response);
            exit;
        }
        
        $tokens = [];
        $file_hash = generateFileHash($filepath);
        
     
        if ($file['type'] === 'application/pdf') {
   
            $tokens = extractTextFromPDF($filepath);
            
           
            if (empty($tokens)) {
       
                if (extension_loaded('imagick')) {
                    try {
                        $imagePath = $upload_dir . uniqid('pdf_img_', true) . '.jpg';
                        $im = new Imagick();
                        $im->setResolution(300, 300);
                        $im->readImage($filepath . '[0]');
                        $im->setImageFormat('jpg');
                        $im->setImageCompressionQuality(90);
                        $im->writeImage($imagePath);
                        $im->clear();
                        $im->destroy();
                        
                        if (file_exists($imagePath)) {
                            $qr_codes = scanQRWithAPI1($imagePath);
                            foreach ($qr_codes as $qr_data) {
                                if (preg_match('/token=([a-f0-9]{32,64})/i', $qr_data, $matches)) {
                                    $tokens[] = $matches[1];
                                }
                            }
                            @unlink($imagePath);
                        }
                    } catch (Exception $e) {}
                }
            }
            
        } elseif ($file['type'] === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' 
                  || $file['type'] === 'application/msword') {
            // Untuk Word: Extract text untuk mencari token
            $tokens = extractTextFromWord($filepath);
            
        } else {
            $qr_codes = scanQRWithAPI1($filepath);
            foreach ($qr_codes as $qr_data) {
                if (preg_match('/token=([a-f0-9]{32,64})/i', $qr_data, $matches)) {
                    $tokens[] = $matches[1];
                }
            }
        }
        
        $tokens = array_unique($tokens);
        $tokens = array_values($tokens);

        @unlink($filepath);
        
        // Validasi hasil
        if (empty($tokens)) {
            $response['message'] = "TTE tidak terdeteksi di dokumen.<br><br>" .
                                 "<b>Tips:</b><br>" .
                                 "• Pastikan dokumen sudah ditandatangani dengan TTE FixPoint<br>" .
                                 "• Untuk gambar: QR Code harus jelas dan tidak blur<br>" .
                                 "• Untuk PDF/Word: Pastikan dokumen asli dari sistem (bukan hasil scan)<br>";
            echo json_encode($response);
            exit;
        }
        
        $tte_results = [];
        $valid_count = 0;
        $invalid_count = 0;
        $processed_tokens = []; 
        
        foreach ($tokens as $index => $token) {
            if (in_array($token, $processed_tokens)) {
                continue;
            }
            $processed_tokens[] = $token;
            $stmt = $conn->prepare("
                SELECT 
                    tu.id, tu.user_id, tu.nama, tu.nik, tu.no_ktp, tu.jabatan, tu.unit, 
                    tu.created_at, tu.status, tu.file_hash,
                    p.nama_perusahaan, p.alamat, p.kota, p.provinsi, p.kontak, p.email,
                    dl.document_hash, dl.signed_at, dl.document_name
                FROM tte_user tu
                LEFT JOIN perusahaan p ON p.id = 1
                LEFT JOIN tte_document_log dl ON dl.tte_token = tu.token AND dl.document_hash = ?
                WHERE tu.token = ? 
                ORDER BY dl.signed_at DESC
                LIMIT 1
            ");
            $stmt->bind_param("ss", $file_hash, $token);
            $stmt->execute();
            $hasil = $stmt->get_result()->fetch_assoc();
            
            if (!$hasil) {
                $tte_results[] = [
                    'no' => count($tte_results) + 1,
                    'valid' => false,
                    'message' => 'Token TTE tidak ditemukan di database'
                ];
                $invalid_count++;
                continue;
            }
            
            if ($hasil['status'] !== 'aktif') {
                $tte_results[] = [
                    'no' => count($tte_results) + 1,
                    'valid' => false,
                    'message' => 'TTE tidak aktif (Status: ' . strtoupper($hasil['status']) . ')'
                ];
                $invalid_count++;
                continue;
            }
            
         
            $integrity_status = 'unknown';
            $integrity_message = '';
            $actual_signed_at = null;
            

            if (!empty($hasil['document_hash'])) {
                $integrity_status = 'original';
                $integrity_message = '✅ File asli, belum dimodifikasi sejak ditandatangani';
                $actual_signed_at = $hasil['signed_at'];
            } else {
              
                $check_stmt = $conn->prepare("
                    SELECT signed_at, document_name 
                    FROM tte_document_log 
                    WHERE tte_token = ? 
                    ORDER BY signed_at DESC 
                    LIMIT 1
                ");
                $check_stmt->bind_param("s", $token);
                $check_stmt->execute();
                $other_doc = $check_stmt->get_result()->fetch_assoc();
                
                if ($other_doc) {
                    $integrity_status = 'different_file';
                    $integrity_message = '⚠️ Ini bukan file yang ditandatangani. File asli: ' . htmlspecialchars($other_doc['document_name']);
                } else {
                    $integrity_status = 'no_log';
                    $integrity_message = 'TTE valid, namun tidak ada riwayat penandatanganan dokumen';
                }
            }
            
            if ($actual_signed_at) {
                $signed_datetime = $actual_signed_at;
            } else {
                $signed_datetime = $hasil['created_at'];
            }
            
            $tte_results[] = [
                'no' => count($tte_results) + 1,
                'valid' => true,
                'nama' => $hasil['nama'],
                'nik' => $hasil['nik'],
                'no_ktp' => $hasil['no_ktp'],
                'jabatan' => $hasil['jabatan'],
                'unit' => $hasil['unit'],
                'created_at' => date('d F Y, H:i', strtotime($signed_datetime)),
                'signed_date' => date('d F Y', strtotime($signed_datetime)),
                'signed_time' => date('H:i', strtotime($signed_datetime)),
                'status' => $hasil['status'],
                'integrity_status' => $integrity_status,
                'integrity_message' => $integrity_message,
                // Data Perusahaan
                'perusahaan' => $hasil['nama_perusahaan'],
                'alamat_perusahaan' => $hasil['alamat'],
                'kota' => $hasil['kota'],
                'provinsi' => $hasil['provinsi'],
                'kontak_perusahaan' => $hasil['kontak'],
                'email_perusahaan' => $hasil['email']
            ];
            $valid_count++;
        }
        
        if ($valid_count === 0) {
            $response['message'] = "Tidak ada TTE yang valid ditemukan di dokumen.<br><br>";
            if ($invalid_count > 0) {
                $response['message'] .= "<b>Detail:</b><br>";
                foreach ($tte_results as $result) {
                    $response['message'] .= "• TTE #" . $result['no'] . ": " . $result['message'] . "<br>";
                }
            }
            echo json_encode($response);
            exit;
        }
        
        $response['success'] = true;
        $response['message'] = "Ditemukan " . $valid_count . " TTE Valid" . ($invalid_count > 0 ? " dan " . $invalid_count . " TTE Tidak Valid" : "");
        $response['data'] = [
            'filename' => $file['name'],
            'filesize' => number_format($file['size'] / 1024 / 1024, 2) . ' MB',
            'verified_at' => date('d F Y, H:i:s'),
            'total_tte' => count($tte_results), 
            'valid_tte' => $valid_count,
            'invalid_tte' => $invalid_count,
            'file_hash' => $file_hash,
            'tte_list' => $tte_results
        ];
        
    } catch (Exception $e) {
        $response['message'] = "Terjadi kesalahan: " . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verifikasi TTE - FixPoint</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<style>
/* Professional Design Styles */
:root {
    --primary-color: #6777ef;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
}

.upload-container {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    overflow: hidden;
}

.upload-area {
    border: 3px dashed var(--primary-color);
    border-radius: 15px;
    padding: 60px 30px;
    text-align: center;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    cursor: pointer !important;
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
    user-select: none;
}

.upload-area * {
    pointer-events: none;
}

.upload-area:hover {
    background: linear-gradient(135deg, #e8eeff 0%, #b8c9e8 100%);
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(103, 119, 239, 0.2);
}

.upload-area.dragover {
    background: linear-gradient(135deg, #d4e6ff 0%, #a8c9ff 100%);
    border-color: #4169e1;
    transform: scale(1.02);
}

.scan-icon {
    font-size: 90px;
    color: var(--primary-color);
    margin-bottom: 20px;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.upload-text h4 {
    font-size: 24px;
    font-weight: 600;
    color: #333;
    margin-bottom: 10px;
}

.upload-text p {
    font-size: 16px;
    color: #666;
}

.file-types {
    display: inline-flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
    justify-content: center;
}

.file-type-badge {
    background: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    color: var(--primary-color);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.preview-container {
    margin-top: 30px;
    padding: 30px;
    background: #f8f9fa;
    border-radius: 15px;
    display: none;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

.preview-file-info {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.preview-img {
    max-width: 100%;
    max-height: 400px;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.pdf-preview, .word-preview {
    text-align: center;
    padding: 40px;
}

.pdf-icon {
    font-size: 100px;
    color: #dc3545;
    margin-bottom: 20px;
}

.word-icon {
    font-size: 100px;
    color: #2b579a;
    margin-bottom: 20px;
}

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.85);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    backdrop-filter: blur(5px);
}

.loading-content {
    background: white;
    padding: 40px 50px;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    max-width: 400px;
}

.spinner-wrapper {
    margin: 0 auto 25px;
}

.spinner {
    border: 5px solid #f3f3f3;
    border-top: 5px solid var(--primary-color);
    border-radius: 50%;
    width: 60px;
    height: 60px;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-text h4 {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 10px;
    color: #333;
}

.loading-text p {
    color: #666;
    margin-bottom: 5px;
}

/* Modal Styles */
.modal-header.bg-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
}

.modal-header.bg-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    border: none;
}

.modal-content {
    border: none;
    border-radius: 15px;
    overflow: hidden;
}

.result-icon {
    font-size: 90px;
    margin: 20px 0;
    animation: scaleIn 0.5s ease;
}

@keyframes scaleIn {
    from { transform: scale(0); }
    to { transform: scale(1); }
}

.result-title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 10px;
}

.result-subtitle {
    font-size: 16px;
    color: #666;
}

.result-table {
    margin-top: 20px;
}

.result-table th {
    background: #f8f9fa;
    font-weight: 600;
    width: 35%;
    padding: 15px;
    border: 1px solid #dee2e6;
}

.result-table td {
    padding: 15px;
    border: 1px solid #dee2e6;
}

.info-card {
    border-left: 4px solid var(--primary-color);
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.info-card h6 {
    font-weight: 700;
    color: #333;
    margin-bottom: 15px;
}

.btn-custom {
    padding: 12px 30px;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

/* TTE Card Styles */
.card.border-warning {
    border-width: 3px !important;
    box-shadow: 0 0 15px rgba(255, 193, 7, 0.3);
}

.card.border-danger {
    border-width: 3px !important;
}

#tte_list_container .card {
    transition: all 0.3s ease;
}

#tte_list_container .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

@media print {
    .no-print { display: none !important; }
    .modal { position: absolute !important; }
    body { background: white; }
}

/* Responsive */
@media (max-width: 768px) {
    .upload-area {
        padding: 40px 20px;
    }
    
    .scan-icon {
        font-size: 60px;
    }
    
    .upload-text h4 {
        font-size: 20px;
    }
    
    .result-title {
        font-size: 22px;
    }
}
</style>
</head>
<body>
<div id="app">
<div class="main-wrapper main-wrapper-1">
<?php include 'navbar.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-content">
        <div class="spinner-wrapper">
            <div class="spinner"></div>
        </div>
        <div class="loading-text">
            <h4>Memproses Dokumen...</h4>
            <p>Sedang membaca TTE dari dokumen</p>
            <small class="text-muted">Proses ini memerlukan 5-15 detik</small>
        </div>
    </div>
</div>

<!-- Modal Success -->
<div class="modal fade" id="modalSuccess" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fas fa-check-circle"></i> Hasil Verifikasi TTE</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="text-center">
          <i class="fas fa-check-circle text-success result-icon"></i>
          <h3 class="result-title text-success" id="modal_title">✅ TTE VALID & TERDAFTAR</h3>
          <p class="result-subtitle">Tanda Tangan Elektronik sah dan terdaftar di sistem FixPoint</p>
        </div>
        
        <hr class="my-4">
        
        <div class="alert alert-info">
          <i class="fas fa-info-circle mr-2"></i>
          <strong>Ringkasan:</strong> <span id="summary_text">-</span>
        </div>
        
        <table class="table table-bordered result-table">
          <tr>
            <th><i class="fas fa-file-alt text-primary mr-2"></i>Dokumen</th>
            <td id="res_filename">-</td>
          </tr>
          <tr>
            <th><i class="fas fa-hdd text-info mr-2"></i>Ukuran File</th>
            <td id="res_filesize">-</td>
          </tr>
          <tr>
            <th><i class="fas fa-clock text-warning mr-2"></i>Waktu Verifikasi</th>
            <td id="res_verified_at">-</td>
          </tr>
          <tr>
            <th><i class="fas fa-qrcode text-primary mr-2"></i>Total TTE</th>
            <td>
              <span id="total_tte" class="badge badge-primary px-3 py-2">0</span>
              <span id="valid_tte" class="badge badge-success px-3 py-2 ml-2">0 Valid</span>
              <span id="invalid_tte" class="badge badge-danger px-3 py-2 ml-2" style="display:none;">0 Tidak Valid</span>
            </td>
          </tr>
        </table>
        
        <div id="tte_list_container">
   
        </div>
        
        <div class="alert alert-info mt-3">
          <i class="fas fa-info-circle mr-2"></i>
          <strong>Dasar Hukum:</strong> TTE Non-Tersertifikasi sesuai UU No. 11/2008 tentang ITE dan PP No. 71/2019.
        </div>
        
        <div class="alert alert-warning" id="security_note" style="display:none;">
          <i class="fas fa-shield-alt mr-2"></i>
          <strong>Catatan Keamanan:</strong> <span id="security_message"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-info btn-custom" onclick="window.print()">
          <i class="fas fa-print"></i> Cetak Hasil
        </button>
        <button type="button" class="btn btn-primary btn-custom" onclick="location.reload()">
          <i class="fas fa-redo"></i> Verifikasi Lagi
        </button>
        <button type="button" class="btn btn-secondary btn-custom" data-dismiss="modal">
          <i class="fas fa-times"></i> Tutup
        </button>
      </div>
    </div>
  </div>
</div>


<div class="modal fade" id="modalFailed" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="fas fa-times-circle"></i> Verifikasi Gagal</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="text-center">
          <i class="fas fa-times-circle text-danger result-icon"></i>
          <h3 class="result-title text-danger">❌ TTE TIDAK VALID</h3>
          <p class="result-subtitle">Tanda Tangan Elektronik tidak dapat diverifikasi</p>
        </div>
        
        <hr class="my-4">
        
        <div class="alert alert-danger" id="error_message">
          Error message
        </div>
        
        <div class="alert alert-warning">
          <h6><i class="fas fa-lightbulb mr-2"></i>Saran Perbaikan:</h6>
          <ul class="mb-0">
            <li>Pastikan dokumen sudah ditandatangani dengan TTE FixPoint</li>
            <li>Untuk gambar: QR Code harus jelas dan tidak blur</li>
            <li>Untuk PDF/Word: Upload file asli (bukan hasil scan)</li>
            <li>QR Code minimal berukuran 150x150 pixel</li>
          </ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary btn-custom" onclick="location.reload()">
          <i class="fas fa-redo"></i> Coba Lagi
        </button>
        <button type="button" class="btn btn-secondary btn-custom" data-dismiss="modal">
          <i class="fas fa-times"></i> Tutup
        </button>
      </div>
    </div>
  </div>
</div>

<div class="main-content">
<section class="section">
<div class="section-header">
    <h1><i class="fas fa-shield-check"></i> Verifikasi Tanda Tangan Elektronik (TTE)</h1>
   
</div>

<div class="section-body">

<div class="alert alert-info alert-has-icon">
    <div class="alert-icon"><i class="fas fa-info-circle"></i></div>
    <div class="alert-body">
        <div class="alert-title">Informasi</div>
        Upload <strong>file PDF, Word, atau gambar (JPG/PNG)</strong> yang sudah memiliki TTE untuk memverifikasi keabsahan tanda tangan elektronik.
    </div>
</div>

<div class="card upload-container">
<div class="card-header">
    <h4><i class="fas fa-cloud-upload-alt"></i> Upload Dokumen untuk Verifikasi</h4>
</div>
<div class="card-body">
    <form id="formUpload" enctype="multipart/form-data">
        
        <div class="upload-area" id="uploadArea">
            <div class="scan-icon">
                <i class="fas fa-cloud-upload-alt"></i>
            </div>
            <div class="upload-text">
                <h4>Klik atau Drag & Drop Dokumen</h4>
                <p>Mendukung format PDF, Word, JPG, dan PNG</p>
            </div>
            <div class="file-types">
                <span class="file-type-badge"><i class="fas fa-file-pdf"></i> PDF</span>
                <span class="file-type-badge"><i class="fas fa-file-word"></i> DOCX</span>
                <span class="file-type-badge"><i class="fas fa-file-image"></i> JPG</span>
                <span class="file-type-badge"><i class="fas fa-file-image"></i> PNG</span>
            </div>
            <p class="text-muted mt-3 mb-0"><small>Maksimal ukuran file: 10 MB</small></p>
            <input type="file" name="file_qr" id="file_qr" accept="image/*,.pdf,.doc,.docx" style="display:none" required>
        </div>
        
        <div class="preview-container" id="previewContainer">
            <div class="row">
                <div class="col-md-6">
                    <div class="preview-file-info">
                        <h6><i class="fas fa-file mr-2"></i>Informasi File</h6>
                        <table class="table table-sm mb-0">
                            <tr>
                                <td width="100"><strong>Nama File</strong></td>
                                <td id="info_filename">-</td>
                            </tr>
                            <tr>
                                <td><strong>Ukuran</strong></td>
                                <td id="info_filesize">-</td>
                            </tr>
                            <tr>
                                <td><strong>Tipe</strong></td>
                                <td id="info_filetype">-</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="col-md-6 text-center">
                    <div id="imagePreview" style="display:none;">
                        <p class="mb-2"><strong>Preview Dokumen</strong></p>
                        <img id="previewImg" class="preview-img" alt="Preview">
                    </div>
                    <div id="pdfPreview" class="pdf-preview" style="display:none;">
                        <i class="fas fa-file-pdf pdf-icon"></i>
                        <p class="mb-0"><strong>Dokumen PDF</strong></p>
                        <small class="text-muted">Siap untuk diverifikasi</small>
                    </div>
                    <div id="wordPreview" class="word-preview" style="display:none;">
                        <i class="fas fa-file-word word-icon"></i>
                        <p class="mb-0"><strong>Dokumen Word</strong></p>
                        <small class="text-muted">Siap untuk diverifikasi</small>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-success btn-lg btn-custom">
                    <i class="fas fa-search"></i> Verifikasi TTE Sekarang
                </button>
                <button type="button" class="btn btn-secondary btn-lg btn-custom ml-2" onclick="resetForm()">
                    <i class="fas fa-times"></i> Batal
                </button>
            </div>
        </div>
    </form>
</div>
</div>

</div>
</section>
</div>

</div>
</div>

<script src="assets/modules/jquery.min.js"></script>
<script src="assets/modules/popper.js"></script>
<script src="assets/modules/bootstrap/js/bootstrap.min.js"></script>
<script src="assets/modules/nicescroll/jquery.nicescroll.min.js"></script>
<script src="assets/modules/moment.min.js"></script>
<script src="assets/js/stisla.js"></script>
<script src="assets/js/scripts.js"></script>
<script src="assets/js/custom.js"></script>

<script>
$(document).ready(function() {
  
    document.getElementById('uploadArea').addEventListener('click', function(e) {
        document.getElementById('file_qr').click();
    });
    
    $(document).on('dragover drop', function(e) {
        e.preventDefault();
    });
    
    $('#uploadArea').on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    });
    
    $('#uploadArea').on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    });
    
    $('#uploadArea').on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
        
        var files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            $('#file_qr')[0].files = files;
            showPreview(files[0]);
        }
    });
    $('#file_qr').on('change', function() {
        if (this.files.length > 0) {
            showPreview(this.files[0]);
        }
    });
    
    $('#formUpload').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('ajax', '1');
        
        $('#loadingOverlay').css('display', 'flex');
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                $('#loadingOverlay').hide();
                
                if (response.success) {
                    $('#res_filename').text(response.data.filename);
                    $('#res_filesize').text(response.data.filesize);
                    $('#res_verified_at').text(response.data.verified_at + ' WIB');
                    
                    $('#modal_title').text('✅ Ditemukan ' + response.data.valid_tte + ' TTE Valid');
                    $('#summary_text').html(
                        'Total <strong>' + response.data.total_tte + '</strong> TTE ditemukan: ' +
                        '<strong class="text-success">' + response.data.valid_tte + ' Valid</strong>' +
                        (response.data.invalid_tte > 0 ? ', <strong class="text-danger">' + response.data.invalid_tte + ' Tidak Valid</strong>' : '')
                    );
                    $('#total_tte').text(response.data.total_tte);
                    $('#valid_tte').text(response.data.valid_tte + ' Valid');
                    
                    if (response.data.invalid_tte > 0) {
                        $('#invalid_tte').text(response.data.invalid_tte + ' Tidak Valid').show();
                    } else {
                        $('#invalid_tte').hide();
                    }
                    var tteListHtml = '';
                    var hasModified = false;
                    
                    $.each(response.data.tte_list, function(index, tte) {
                        if (tte.valid) {
                            // Valid TTE
                            var integrityBadge = '';
                            var integrityClass = '';
                            
                            if (tte.integrity_status === 'original') {
                                integrityBadge = '<span class="badge badge-success"><i class="fas fa-check"></i> File Asli</span>';
                            } else if (tte.integrity_status === 'different_file') {
                                integrityBadge = '<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> File Berbeda</span>';
                                integrityClass = 'border-warning';
                                hasModified = true;
                            } else if (tte.integrity_status === 'no_match') {
                                integrityBadge = '<span class="badge badge-danger"><i class="fas fa-times"></i> Hash Tidak Cocok</span>';
                                integrityClass = 'border-danger';
                                hasModified = true;
                            } else {
                                integrityBadge = '<span class="badge badge-secondary"><i class="fas fa-question"></i> Tidak Ada Log</span>';
                            }
                            
                            tteListHtml += '<div class="card mb-3 ' + integrityClass + '">';
                            tteListHtml += '  <div class="card-header bg-success text-white">';
                            tteListHtml += '    <strong><i class="fas fa-signature"></i> TTE #' + tte.no + ' - VALID</strong>';
                            tteListHtml += '    <span class="float-right">' + integrityBadge + '</span>';
                            tteListHtml += '  </div>';
                            tteListHtml += '  <div class="card-body">';
                            
                            tteListHtml += '    <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-user-check text-primary"></i> <strong>Informasi Penandatangan</strong></h6>';
                            tteListHtml += '    <table class="table table-sm table-borderless mb-3">';
                            tteListHtml += '      <tr><td width="180"><strong><i class="fas fa-user"></i> Nama Lengkap</strong></td><td>' + tte.nama + '</td></tr>';
                            tteListHtml += '      <tr><td><strong><i class="fas fa-id-badge"></i> NIK/NIP</strong></td><td>' + tte.nik + '</td></tr>';
                            
                            if (tte.no_ktp) {
                                tteListHtml += '      <tr><td><strong><i class="fas fa-address-card"></i> No. KTP</strong></td><td>' + tte.no_ktp + '</td></tr>';
                            }
                            
                            tteListHtml += '      <tr><td><strong><i class="fas fa-briefcase"></i> Jabatan</strong></td><td>' + tte.jabatan + '</td></tr>';
                            tteListHtml += '      <tr><td><strong><i class="fas fa-building"></i> Unit/Bagian</strong></td><td>' + tte.unit + '</td></tr>';
                            tteListHtml += '    </table>';
                            
                            tteListHtml += '    <h6 class="border-bottom pb-2 mb-3 mt-4"><i class="fas fa-calendar-check text-info"></i> <strong>Waktu Penandatanganan</strong></h6>';
                            tteListHtml += '    <table class="table table-sm table-borderless mb-3">';
                            tteListHtml += '      <tr><td width="180"><strong><i class="fas fa-calendar-alt"></i> Tanggal</strong></td><td>' + tte.signed_date + '</td></tr>';
                            tteListHtml += '      <tr><td><strong><i class="fas fa-clock"></i> Jam</strong></td><td>' + tte.signed_time + ' WIB</td></tr>';
                            tteListHtml += '      <tr><td><strong><i class="fas fa-stamp"></i> Dibuat Pada</strong></td><td>' + tte.created_at + ' WIB</td></tr>';
                            tteListHtml += '    </table>';
                            
                            if (tte.perusahaan) {
                                tteListHtml += '    <h6 class="border-bottom pb-2 mb-3 mt-4"><i class="fas fa-building text-warning"></i> <strong>Informasi Perusahaan/Instansi</strong></h6>';
                                tteListHtml += '    <table class="table table-sm table-borderless mb-3">';
                                tteListHtml += '      <tr><td width="180"><strong><i class="fas fa-building"></i> Nama Perusahaan</strong></td><td>' + tte.perusahaan + '</td></tr>';
                                
                                if (tte.alamat_perusahaan) {
                                    tteListHtml += '      <tr><td><strong><i class="fas fa-map-marker-alt"></i> Alamat</strong></td><td>' + tte.alamat_perusahaan + '</td></tr>';
                                }
                                
                                if (tte.kota && tte.provinsi) {
                                    tteListHtml += '      <tr><td><strong><i class="fas fa-city"></i> Kota/Provinsi</strong></td><td>' + tte.kota + ', ' + tte.provinsi + '</td></tr>';
                                }
                                
                                if (tte.kontak_perusahaan) {
                                    tteListHtml += '      <tr><td><strong><i class="fas fa-phone"></i> Kontak</strong></td><td>' + tte.kontak_perusahaan + '</td></tr>';
                                }
                                
                                if (tte.email_perusahaan) {
                                    tteListHtml += '      <tr><td><strong><i class="fas fa-envelope"></i> Email</strong></td><td>' + tte.email_perusahaan + '</td></tr>';
                                }
                                
                                tteListHtml += '    </table>';
                            }
                            
                            if (tte.integrity_message) {
                                var msgClass = tte.integrity_status === 'modified' ? 'alert-warning' : 'alert-info';
                                tteListHtml += '    <div class="alert ' + msgClass + ' mt-3 mb-0">';
                                tteListHtml += '      <i class="fas fa-info-circle"></i> <strong>Status Integritas:</strong> ' + tte.integrity_message;
                                tteListHtml += '    </div>';
                            }
                            
                            tteListHtml += '  </div>';
                            tteListHtml += '</div>';
                        } else {
                            tteListHtml += '<div class="card mb-3 border-danger">';
                            tteListHtml += '  <div class="card-header bg-danger text-white">';
                            tteListHtml += '    <strong><i class="fas fa-times-circle"></i> TTE #' + tte.no + ' - TIDAK VALID</strong>';
                            tteListHtml += '  </div>';
                            tteListHtml += '  <div class="card-body">';
                            tteListHtml += '    <p class="text-danger mb-0"><i class="fas fa-exclamation-triangle"></i> ' + tte.message + '</p>';
                            tteListHtml += '  </div>';
                            tteListHtml += '</div>';
                        }
                    });
                    
                    $('#tte_list_container').html(tteListHtml);
                    
                    if (hasModified) {
                        $('#security_message').html(
                            'File yang Anda upload berbeda dari file asli yang ditandatangani. ' +
                            'TTE tetap valid karena token terdaftar, namun <strong>ini bukan dokumen asli</strong> yang ditandatangani dengan TTE tersebut.'
                        );
                        $('#security_note').show();
                    } else {
                        $('#security_note').hide();
                    }
                    
                    $('#modalSuccess').modal('show');
                } else {
          
                    $('#error_message').html(response.message);
                    $('#modalFailed').modal('show');
                }
            },
            error: function(xhr, status, error) {
                $('#loadingOverlay').hide();
                
                var errorMsg = 'Terjadi kesalahan pada server. Silakan coba lagi.';
                
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMsg = response.message;
                    }
                } catch(e) {}
                
                $('#error_message').html(errorMsg);
                $('#modalFailed').modal('show');
            }
        });
    });
});

function showPreview(file) {
    if (!file) return;
    
    var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf', 
                        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if (allowedTypes.indexOf(file.type) === -1) {
        alert('Format file tidak valid. Gunakan JPG, PNG, PDF, atau DOCX.');
        return;
    }
    
    if (file.size > 10485760) {
        alert('Ukuran file maksimal 10MB.');
        return;
    }
    
    $('#previewContainer').slideDown();
    
    // File info
    $('#info_filename').text(file.name);
    $('#info_filesize').text((file.size / 1024 / 1024).toFixed(2) + ' MB');
    $('#info_filetype').text(file.type);
    
    $('#imagePreview, #pdfPreview, #wordPreview').hide();
    
    if (file.type === 'application/pdf') {
        $('#pdfPreview').show();
    } else if (file.type.includes('word')) {
        $('#wordPreview').show();
    } else {
        var reader = new FileReader();
        reader.onload = function(e) {
            $('#previewImg').attr('src', e.target.result);
            $('#imagePreview').show();
        };
        reader.readAsDataURL(file);
    }
}

function resetForm() {
    $('#formUpload')[0].reset();
    $('#previewContainer').slideUp();
    $('#imagePreview, #pdfPreview, #wordPreview').hide();
}
</script>

</body>
</html>