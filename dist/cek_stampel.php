<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    header("Location: login.php");
    exit;
}

// Cek akses user
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

// Load library jika ada
$autoload_file = __DIR__ . '/lib/autoload.php';
if (file_exists($autoload_file)) {
    require_once $autoload_file;
}

// Fungsi untuk scan QR Code
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

// Fungsi generate hash file
function generateFileHash($filePath) {
    if (!file_exists($filePath)) return false;
    return hash_file('sha256', $filePath);
}

// Fungsi extract token dari PDF
function extractTextFromPDF($pdfPath) {
    $tokens = [];
    
    if (function_exists('shell_exec')) {
        $pdftotext = shell_exec('which pdftotext 2>/dev/null');
        if (!empty($pdftotext)) {
            try {
                $output = shell_exec("pdftotext " . escapeshellarg($pdfPath) . " - 2>&1");
                if ($output) {
                    preg_match_all('/(?:STAMPEL-TOKEN:|token=)([a-f0-9]{32,64})/i', $output, $matches);
                    if (!empty($matches[1])) {
                        $tokens = array_merge($tokens, $matches[1]);
                    }
                }
            } catch (Exception $e) {}
        }
    }
    
    if (empty($tokens)) {
        $content = file_get_contents($pdfPath);
        if ($content) {
            preg_match_all('/cek_stampel\.php\?token=([a-f0-9]{32,64})/i', $content, $matches);
            if (!empty($matches[1])) {
                $tokens = array_merge($tokens, $matches[1]);
            }
            
            preg_match_all('/STAMPEL-TOKEN:([a-f0-9]{32,64})/i', $content, $matches);
            if (!empty($matches[1])) {
                $tokens = array_merge($tokens, $matches[1]);
            }
        }
    }

    return array_values(array_unique($tokens));
}

// Fungsi extract token dari Word
function extractTextFromWord($docPath) {
    $tokens = [];
    
    if (!class_exists('PhpOffice\\PhpWord\\IOFactory')) {
        return $tokens;
    }
    
    try {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($docPath);

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
            preg_match_all('/cek_stampel\.php\?token=([a-f0-9]{32,64})/i', $text, $matches);
            if (!empty($matches[1])) {
                $tokens = array_merge($tokens, $matches[1]);
            }
            preg_match_all('/STAMPEL-TOKEN:([a-f0-9]{32,64})/i', $text, $matches);
            if (!empty($matches[1])) {
                $tokens = array_merge($tokens, $matches[1]);
            }
        }
        
    } catch (Exception $e) {}
    
    return array_values(array_unique($tokens));
}

// Handle AJAX request
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
            $response['message'] = "Format file tidak valid. Gunakan JPG, PNG, PDF, atau DOCX.";
            echo json_encode($response);
            exit;
        }
        
        if ($file['size'] > 10485760) {
            $response['message'] = "Ukuran file maksimal 10MB.";
            echo json_encode($response);
            exit;
        }
        
        // Upload file
        $upload_dir = 'uploads/temp_stampel/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $filename = time() . '_' . basename($file['name']);
        $filepath = $upload_dir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $response['message'] = "Gagal menyimpan file.";
            echo json_encode($response);
            exit;
        }
        
        // Extract tokens
        $tokens = [];
        
        if ($file['type'] === 'application/pdf') {
            $tokens = extractTextFromPDF($filepath);
        } elseif (in_array($file['type'], ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) {
            $tokens = extractTextFromWord($filepath);
        } else {
            $qr_data = scanQRWithAPI1($filepath);
            if (!empty($qr_data)) {
                foreach ($qr_data as $data) {
                    if (preg_match('/cek_stampel\.php\?token=([a-f0-9]{32,64})/i', $data, $matches)) {
                        $tokens[] = $matches[1];
                    }
                }
            }
        }
        
        $tokens = array_unique($tokens);
        
        if (empty($tokens)) {
            unlink($filepath);
            $response['message'] = "Tidak ditemukan E-Stampel pada file yang diupload.";
            echo json_encode($response);
            exit;
        }
        
        // Verify tokens
        $file_hash = generateFileHash($filepath);
        $stampel_list = [];
        
        foreach ($tokens as $index => $token) {
            $token_clean = mysqli_real_escape_string($conn, $token);
            
            $q = mysqli_query($conn, "
                SELECT e.*, p.nama_perusahaan as perusahaan_data, p.logo
                FROM e_stampel e
                LEFT JOIN perusahaan p ON 1=1
                WHERE e.token = '$token_clean'
                LIMIT 1
            ");
            
            if ($q && mysqli_num_rows($q) > 0) {
                $stampel = mysqli_fetch_assoc($q);
                
                $integrity_status = '';
                $integrity_message = '';
                
                if (!empty($stampel['file_hash'])) {
                    if ($stampel['file_hash'] === $file_hash) {
                        $integrity_status = 'original';
                        $integrity_message = 'File ini adalah dokumen asli yang distempel.';
                    } else {
                        $integrity_status = 'different_file';
                        $integrity_message = 'File ini berbeda dari dokumen asli yang distempel, namun E-Stampel tetap valid.';
                    }
                } else {
                    $integrity_status = 'no_log';
                    $integrity_message = 'Tidak ada log hash file untuk E-Stampel ini.';
                }
                
                $stampel_list[] = [
                    'no' => $index + 1,
                    'valid' => true,
                    'token' => $token,
                    'nama_perusahaan' => $stampel['nama_perusahaan'],
                    'alamat' => $stampel['alamat'],
                    'kota' => $stampel['kota'],
                    'provinsi' => $stampel['provinsi'],
                    'kontak' => $stampel['kontak'],
                    'email' => $stampel['email'],
                    'status' => $stampel['status'],
                    'created_at' => date('d F Y, H:i', strtotime($stampel['created_at'])),
                    'integrity_status' => $integrity_status,
                    'integrity_message' => $integrity_message,
                    'logo' => $stampel['logo'] ?? ''
                ];
            } else {
                $stampel_list[] = [
                    'no' => $index + 1,
                    'valid' => false,
                    'token' => $token,
                    'message' => 'Token tidak ditemukan di database atau E-Stampel sudah nonaktif.'
                ];
            }
        }
        
        // Hapus file temporary
        unlink($filepath);
        
        $response['success'] = true;
        $response['message'] = 'Verifikasi berhasil!';
        $response['data'] = [
            'total_stampel' => count($stampel_list),
            'stampel_list' => $stampel_list
        ];
        
    } catch (Exception $e) {
        $response['message'] = 'Terjadi kesalahan: ' . $e->getMessage();
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
<title>Cek E-Stampel - Sistem Rumah Sakit</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<style>
.upload-area {
    border: 3px dashed #28a745;
    border-radius: 15px;
    padding: 50px 20px;
    text-align: center;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    cursor: pointer;
    transition: all 0.3s ease;
}

.upload-area:hover {
    border-color: #20c997;
    background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
    transform: scale(1.02);
}

.upload-area i {
    font-size: 60px;
    color: #28a745;
    margin-bottom: 20px;
}

.preview-box {
    border: 2px solid #dee2e6;
    border-radius: 10px;
    padding: 15px;
    background: white;
}

#loadingOverlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 9999;
    justify-content: center;
    align-items: center;
}

.spinner-custom {
    width: 60px;
    height: 60px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid #28a745;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.info-card {
    border-left: 4px solid #28a745;
    background: #f8f9fa;
}
</style>
</head>
<body>
<div id="app">
<div class="main-wrapper main-wrapper-1">
<?php include 'navbar.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="main-content">
<section class="section">
<div class="section-header">
    <h1><i class="fas fa-search"></i> Cek Keabsahan E-Stampel</h1>
</div>

<div class="section-body">

<!-- Info Card -->
<div class="card info-card mb-4">
<div class="card-body">
    <h5><i class="fas fa-info-circle text-success"></i> Tentang Verifikasi E-Stampel</h5>
    <p class="mb-0">
        Upload dokumen yang memiliki QR Code E-Stampel atau scan langsung QR Code untuk memverifikasi keabsahan 
        stempel elektronik. Sistem akan menampilkan informasi lengkap perusahaan/instansi yang membubuhkan stempel.
    </p>
</div>
</div>

<!-- Upload Form -->
<div class="card">
<div class="card-header bg-success text-white">
    <h4 class="text-white"><i class="fas fa-upload"></i> Upload File untuk Verifikasi</h4>
</div>
<div class="card-body">
    
    <form id="formUpload" enctype="multipart/form-data">
        <input type="hidden" name="ajax" value="1">
        
        <div class="upload-area" onclick="document.getElementById('file_qr').click()">
            <i class="fas fa-cloud-upload-alt"></i>
            <h5>Klik untuk Upload File</h5>
            <p class="text-muted mb-0">
                Format: JPG, PNG, PDF, DOCX | Maksimal 10MB
            </p>
            <input type="file" 
                   id="file_qr" 
                   name="file_qr" 
                   accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" 
                   style="display: none;"
                   onchange="showPreview(this.files[0])">
        </div>
        
        <!-- Preview Container -->
        <div id="previewContainer" style="display: none;" class="mt-4">
            <h5><i class="fas fa-eye"></i> Preview File</h5>
            <div class="preview-box">
                <div class="row">
                    <div class="col-md-4">
                        <div id="imagePreview" style="display: none;">
                            <img id="previewImg" src="" alt="Preview" class="img-fluid rounded">
                        </div>
                        <div id="pdfPreview" style="display: none;" class="text-center">
                            <i class="fas fa-file-pdf fa-5x text-danger"></i>
                            <p class="mt-2">File PDF</p>
                        </div>
                        <div id="wordPreview" style="display: none;" class="text-center">
                            <i class="fas fa-file-word fa-5x text-primary"></i>
                            <p class="mt-2">File Word</p>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th width="30%">Nama File:</th>
                                <td id="info_filename"></td>
                            </tr>
                            <tr>
                                <th>Ukuran:</th>
                                <td id="info_filesize"></td>
                            </tr>
                            <tr>
                                <th>Tipe:</th>
                                <td id="info_filetype"></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="mt-3 text-center">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-search"></i> Verifikasi E-Stampel
                </button>
                <button type="button" class="btn btn-secondary btn-lg" onclick="resetForm()">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>
        </div>
        
    </form>
    
</div>
</div>

<!-- Panduan -->
<div class="card">
<div class="card-header bg-info text-white">
    <h4 class="text-white"><i class="fas fa-question-circle"></i> Cara Menggunakan</h4>
</div>
<div class="card-body">
    <div class="row">
        <div class="col-md-6">
            <h6><i class="fas fa-check-circle text-success"></i> Langkah Verifikasi:</h6>
            <ol>
                <li>Upload file yang berisi QR Code E-Stampel</li>
                <li>Klik tombol "Verifikasi E-Stampel"</li>
                <li>Tunggu proses verifikasi selesai</li>
                <li>Lihat hasil verifikasi yang ditampilkan</li>
            </ol>
        </div>
        <div class="col-md-6">
            <h6><i class="fas fa-info-circle text-info"></i> Informasi yang Ditampilkan:</h6>
            <ul>
                <li><strong>Nama Perusahaan/Instansi</strong></li>
                <li><strong>Alamat Lengkap</strong></li>
                <li><strong>Kontak & Email</strong></li>
                <li><strong>Waktu Pembuatan E-Stampel</strong></li>
                <li><strong>Status Keabsahan Dokumen</strong></li>
            </ul>
        </div>
    </div>
</div>
</div>

</div>
</section>
</div>

</div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay">
    <div class="text-center">
        <div class="spinner-custom mx-auto"></div>
        <h4 class="text-white mt-3">Memverifikasi E-Stampel...</h4>
        <p class="text-white">Mohon tunggu sebentar</p>
    </div>
</div>

<!-- Modal Success -->
<div class="modal fade" id="modalSuccess" tabindex="-1" role="dialog">
<div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
<div class="modal-content">
    <div class="modal-header bg-success text-white">
        <h5 class="modal-title text-white">
            <i class="fas fa-check-circle"></i> Hasil Verifikasi E-Stampel
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
        </button>
    </div>
    <div class="modal-body">
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 
            <strong>Verifikasi Berhasil!</strong> Ditemukan <span id="total_stampel"></span> E-Stampel pada dokumen.
        </div>
        
        <div id="security_note" class="alert alert-warning" style="display: none;">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Perhatian:</strong> <span id="security_message"></span>
        </div>
        
        <div id="stampel_list_container"></div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
    </div>
</div>
</div>
</div>

<!-- Modal Failed -->
<div class="modal fade" id="modalFailed" tabindex="-1" role="dialog">
<div class="modal-dialog modal-dialog-centered" role="document">
<div class="modal-content">
    <div class="modal-header bg-danger text-white">
        <h5 class="modal-title text-white">
            <i class="fas fa-times-circle"></i> Verifikasi Gagal
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
        </button>
    </div>
    <div class="modal-body">
        <div class="alert alert-danger mb-0">
            <i class="fas fa-exclamation-triangle"></i> 
            <span id="error_message"></span>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
    </div>
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
    $('#formUpload').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        
        $('#loadingOverlay').show();
        
        $.ajax({
            url: 'cek_stampel.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#loadingOverlay').hide();
                
                if (response.success) {
                    $('#total_stampel').text(response.data.total_stampel);
                    
                    var stampelListHtml = '';
                    var hasModified = false;
                    
                    $.each(response.data.stampel_list, function(index, stampel) {
                        if (stampel.valid) {
                            var integrityBadge = '';
                            var integrityClass = '';
                            
                            if (stampel.integrity_status === 'original') {
                                integrityBadge = '<span class="badge badge-success"><i class="fas fa-check"></i> Dokumen Asli</span>';
                            } else if (stampel.integrity_status === 'different_file') {
                                integrityBadge = '<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> File Berbeda</span>';
                                integrityClass = 'border-warning';
                                hasModified = true;
                            } else {
                                integrityBadge = '<span class="badge badge-secondary"><i class="fas fa-question"></i> Tidak Ada Log</span>';
                            }
                            
                            stampelListHtml += '<div class="card mb-3 ' + integrityClass + '">';
                            stampelListHtml += '  <div class="card-header bg-success text-white">';
                            stampelListHtml += '    <strong><i class="fas fa-stamp"></i> E-Stampel #' + stampel.no + ' - VALID</strong>';
                            stampelListHtml += '    <span class="float-right">' + integrityBadge + '</span>';
                            stampelListHtml += '  </div>';
                            stampelListHtml += '  <div class="card-body">';
                            
                            stampelListHtml += '    <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-building text-primary"></i> <strong>Informasi Perusahaan/Instansi</strong></h6>';
                            stampelListHtml += '    <table class="table table-sm table-borderless mb-3">';
                            stampelListHtml += '      <tr><td width="180"><strong><i class="fas fa-building"></i> Nama Perusahaan</strong></td><td>' + stampel.nama_perusahaan + '</td></tr>';
                            stampelListHtml += '      <tr><td><strong><i class="fas fa-map-marker-alt"></i> Alamat</strong></td><td>' + stampel.alamat + '</td></tr>';
                            stampelListHtml += '      <tr><td><strong><i class="fas fa-city"></i> Kota/Provinsi</strong></td><td>' + stampel.kota + ', ' + stampel.provinsi + '</td></tr>';
                            stampelListHtml += '      <tr><td><strong><i class="fas fa-phone"></i> Kontak</strong></td><td>' + stampel.kontak + '</td></tr>';
                            stampelListHtml += '      <tr><td><strong><i class="fas fa-envelope"></i> Email</strong></td><td>' + stampel.email + '</td></tr>';
                            stampelListHtml += '    </table>';
                            
                            stampelListHtml += '    <h6 class="border-bottom pb-2 mb-3 mt-4"><i class="fas fa-calendar-check text-info"></i> <strong>Waktu Pembuatan E-Stampel</strong></h6>';
                            stampelListHtml += '    <table class="table table-sm table-borderless mb-3">';
                            stampelListHtml += '      <tr><td width="180"><strong><i class="fas fa-calendar-alt"></i> Dibuat Pada</strong></td><td>' + stampel.created_at + ' WIB</td></tr>';
                            stampelListHtml += '      <tr><td><strong><i class="fas fa-toggle-on"></i> Status</strong></td><td><span class="badge badge-success">AKTIF</span></td></tr>';
                            stampelListHtml += '    </table>';
                            
                            if (stampel.integrity_message) {
                                var msgClass = stampel.integrity_status === 'different_file' ? 'alert-warning' : 'alert-info';
                                stampelListHtml += '    <div class="alert ' + msgClass + ' mt-3 mb-0">';
                                stampelListHtml += '      <i class="fas fa-info-circle"></i> <strong>Status Integritas:</strong> ' + stampel.integrity_message;
                                stampelListHtml += '    </div>';
                            }
                            
                            stampelListHtml += '  </div>';
                            stampelListHtml += '</div>';
                        } else {
                            stampelListHtml += '<div class="card mb-3 border-danger">';
                            stampelListHtml += '  <div class="card-header bg-danger text-white">';
                            stampelListHtml += '    <strong><i class="fas fa-times-circle"></i> E-Stampel #' + stampel.no + ' - TIDAK VALID</strong>';
                            stampelListHtml += '  </div>';
                            stampelListHtml += '  <div class="card-body">';
                            stampelListHtml += '    <p class="text-danger mb-0"><i class="fas fa-exclamation-triangle"></i> ' + stampel.message + '</p>';
                            stampelListHtml += '  </div>';
                            stampelListHtml += '</div>';
                        }
                    });
                    
                    $('#stampel_list_container').html(stampelListHtml);
                    
                    if (hasModified) {
                        $('#security_message').html(
                            'File yang Anda upload berbeda dari file asli yang distempel. ' +
                            'E-Stampel tetap valid karena token terdaftar, namun <strong>ini bukan dokumen asli</strong> yang distempel.'
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