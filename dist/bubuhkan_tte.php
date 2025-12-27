<?php
session_start();
include 'koneksi.php';
require_once 'license_config.php';
require_once 'license_validator.php';
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

$qTte = $conn->prepare("SELECT * FROM tte_user WHERE user_id=? AND status='aktif' LIMIT 1");
$qTte->bind_param("i", $user_id);
$qTte->execute();
$dataTte = $qTte->get_result();
$tte = $dataTte->fetch_assoc();

if (!$tte) {
    header("Location: buat_tte.php");
    exit;
}


$required_folders = [
    __DIR__ . '/uploads/temp',
    __DIR__ . '/uploads/signed',
    __DIR__ . '/uploads/qr_temp',
    __DIR__ . '/uploads/phpword_temp'
];

foreach ($required_folders as $folder) {
    if (!is_dir($folder)) {
        @mkdir($folder, 0755, true);
    }
}

require_once __DIR__ . '/tte_hash_helper.php';

$autoload_file = __DIR__ . '/lib/autoload.php';
if (file_exists($autoload_file)) {
    require_once $autoload_file;
}

$success_data = null;
$error_message = null;

function processPDFCustomPosition($filepath, $qr_file, $position_x, $position_y, $tte, $filename, $target_page) {
    if (!class_exists('setasign\\Fpdi\\Fpdi')) {
        return false;
    }

    try {
        $pdf = new \setasign\Fpdi\Fpdi();
        $pageCount = $pdf->setSourceFile($filepath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplIdx = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplIdx);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplIdx);

        
            if ($pageNo == $target_page) {
                $qr_size = 20; // Ukuran QR diperkecil dari 30 ke 20
                $x = ($position_x / 100) * $size['width'];
                $y = ($position_y / 100) * $size['height'];

                if (file_exists($qr_file)) {
                    $pdf->Image($qr_file, $x, $y, $qr_size, $qr_size);
                }

                $pdf->SetFont('Arial', '', 0.1);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetXY(0, 0);
                $pdf->Cell(0, 0, 'TTE-TOKEN:' . $tte['token'], 0, 0, 'L');
            }
        }

        $output_dir = __DIR__ . '/uploads/signed/';
        $output_file = $output_dir . $filename;
        
        $pdf->SetCreator('FixPoint TTE System');
        $pdf->SetAuthor($tte['nama']);
        $pdf->SetSubject('TTE-TOKEN:' . $tte['token']);
        $pdf->Output('F', $output_file);

        if (file_exists($output_file)) {
            @unlink($filepath);
            return $output_file;
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

function processWordCustomPosition($filepath, $qr_file, $position_x, $position_y, $tte, $filename) {
    if (!class_exists('PhpOffice\\PhpWord\\IOFactory')) {
        return false;
    }

    try {
        $custom_temp = __DIR__ . '/uploads/phpword_temp/';
        \PhpOffice\PhpWord\Settings::setTempDir($custom_temp);
        
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($filepath);

        $section = $phpWord->addSection();
        
     
        if ($position_y < 50) {
            $section->addTextBreak(3);
        }
        
        $table = $section->addTable([
            'borderSize' => 0, 
            'cellMargin' => 80,
            'width' => 100 * 50,
            'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::PERCENT
        ]);
        
        $table->addRow();
        
        // Tentukan posisi berdasarkan X
        if ($position_x > 50) {
            $table->addCell(7000);
            $cell = $table->addCell(5000);
        } else {
            $cell = $table->addCell(5000);
            $table->addCell(7000);
        }
        
        if (file_exists($qr_file)) {
            $cell->addImage($qr_file, [
                'width' => 60,  
                'height' => 60, 
                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
            ]);
        }
        
        $cell->addText('Ditandatangani oleh:', 
            ['size' => 7, 'bold' => true], 
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 60]
        );
        
        $cell->addText($tte['nama'], 
            ['size' => 8, 'bold' => true], 
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 50]
        );
        
        if (!empty($tte['nik'])) {
            $cell->addText('NIK: ' . $tte['nik'], 
                ['size' => 6], 
                ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 50]
            );
        }
        
        if (!empty($tte['jabatan'])) {
            $cell->addText($tte['jabatan'], 
                ['size' => 7],  
                ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 50]
            );
        }
        
        $cell->addText(date('d F Y, H:i') . ' WIB', 
            ['size' => 6, 'italic' => true, 'color' => '666666'],  
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        
        // Token tersembunyi
        $section->addText('TTE-TOKEN:' . $tte['token'], ['size' => 1, 'color' => 'FFFFFF']);
        
        $output_dir = __DIR__ . '/uploads/signed/';
        $output_file = $output_dir . pathinfo($filename, PATHINFO_FILENAME) . '_signed.docx';
        
        $phpWord->getDocInfo()->setCreator('FixPoint TTE System');
        $phpWord->getDocInfo()->setTitle('Dokumen Bertanda Tangan');
        $phpWord->getDocInfo()->setDescription('TTE-TOKEN:' . $tte['token']);
        
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($output_file);
        
        if (file_exists($output_file)) {
            @unlink($filepath);
            return $output_file;
        }
        return false;
    } catch (Exception $e) {
        error_log("Word processing error: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bubuhkan_tte'])) {
    if (!isset($_FILES['dokumen']) || $_FILES['dokumen']['error'] !== UPLOAD_ERR_OK) {
        $error_message = "File tidak terdeteksi atau upload gagal.";
    } else {
        $file = $_FILES['dokumen'];
        $position_x = floatval($_POST['position_x'] ?? 70);
        $position_y = floatval($_POST['position_y'] ?? 80);
        $target_page = intval($_POST['target_page'] ?? 1);
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx'];
        
        if (!in_array($ext, $allowed)) {
            $error_message = "Format file tidak valid.";
        } elseif ($file['size'] > 10485760) {
            $error_message = "Ukuran file maksimal 10MB.";
        } else {
            $upload_dir = __DIR__ . '/uploads/temp/';
            $temp_filename = 'temp_' . time() . '_' . uniqid() . '.' . $ext;
            $temp_filepath = $upload_dir . $temp_filename;
            
            if (move_uploaded_file($file['tmp_name'], $temp_filepath)) {
                $qr_dir = __DIR__ . '/uploads/qr_temp/';
                $qr_file = $qr_dir . 'qr_' . time() . '.png';
                
                $qr_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/cek_tte.php?token=" . $tte['token'];
                $qr_image = @file_get_contents("https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_url));
                
                if ($qr_image !== false) {
                    file_put_contents($qr_file, $qr_image);
                    
                    $original_name = pathinfo($file['name'], PATHINFO_FILENAME);
                    $final_filename = $original_name . '_signed_' . time() . '.' . $ext;
                    
                    if ($ext === 'pdf') {
                        $hasil_file = processPDFCustomPosition($temp_filepath, $qr_file, $position_x, $position_y, $tte, $final_filename, $target_page);
                    } else {
                        $hasil_file = processWordCustomPosition($temp_filepath, $qr_file, $position_x, $position_y, $tte, $final_filename);
                    }
                    
                    @unlink($qr_file);
                    
                    if ($hasil_file && file_exists($hasil_file)) {
                        $file_hash = generateFileHash($hasil_file);
                        if ($file_hash) {
                            saveFileHashToDatabase($conn, $tte['token'], $file_hash);
                            // NEW: Simpan log penandatanganan dengan timestamp akurat
                            saveDocumentSigningLog($conn, $tte['token'], $user_id, basename($hasil_file), $file_hash);
                        }
                        
                        $success_data = [
                            'filename' => basename($hasil_file),
                            'web_path' => 'uploads/signed/' . basename($hasil_file),
                            'filesize' => filesize($hasil_file),
                            'original_name' => $file['name'],
                            'tte_nama' => $tte['nama'],
                            'tte_nik' => $tte['nik'],
                            'tte_jabatan' => $tte['jabatan'],
                            'tte_token' => $tte['token'],
                            'timestamp' => date('d F Y, H:i:s'),
                            'target_page' => $target_page,
                            'file_type' => strtoupper($ext)
                        ];
                    } else {
                        $error_message = "Gagal memproses dokumen.";
                    }
                } else {
                    $error_message = "Gagal generate QR Code.";
                    @unlink($temp_filepath);
                }
            } else {
                $error_message = "Gagal upload file.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bubuhkan TTE</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<style>
.card-modern { border: none; border-radius: 15px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58,59,69,0.15); }
.card-header-modern { background: linear-gradient(135deg, #6777ef 0%, #4834df 100%); color: white; padding: 1.5rem; }
.upload-area { border: 3px dashed #d1d3e0; border-radius: 12px; padding: 3rem 2rem; text-align: center; background: #f8f9fc; cursor: pointer; transition: all 0.3s; }
.upload-area:hover { border-color: #6777ef; background: #f0f3ff; }
.preview-container { position: relative; border: 2px solid #6777ef; border-radius: 12px; background: #f8f9fa; min-height: 600px; display: none; margin-top: 1.5rem; overflow: hidden; }
.preview-container.active { display: block; }
#pdfCanvas { max-width: 100%; background: white; display: block; margin: 0 auto; }
.tte-stamp { position: absolute; width: 70px; cursor: move; background: rgba(103,119,239,0.15); border: 3px dashed #6777ef; border-radius: 10px; padding: 5px; text-align: center; user-select: none; z-index: 50; }
.tte-stamp img { width: 55px; height: 55px; pointer-events: none; }
.position-info { position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.85); color: white; padding: 10px 15px; border-radius: 8px; font-size: 0.85rem; font-family: monospace; z-index: 100; }
.page-navigation { position: absolute; top: 10px; left: 10px; background: rgba(0,0,0,0.85); color: white; padding: 10px 15px; border-radius: 8px; z-index: 100; }
.page-navigation button { background: #6777ef; border: none; color: white; padding: 5px 12px; margin: 0 5px; border-radius: 5px; cursor: pointer; }
.page-navigation button:disabled { background: #ccc; cursor: not-allowed; }
.quick-position-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 1rem; }
.quick-pos-btn { padding: 1.2rem; border: 2px solid #e3e6f0; border-radius: 10px; background: white; cursor: pointer; transition: all 0.2s; text-align: center; font-weight: 600; }
.quick-pos-btn:hover { border-color: #6777ef; background: #f8f9ff; transform: translateY(-2px); }
.mode-tabs { display: flex; gap: 0; margin-bottom: 1.5rem; border-radius: 10px; overflow: hidden; border: 2px solid #6777ef; }
.mode-tab { flex: 1; padding: 1rem; background: white; border: none; cursor: pointer; font-weight: 600; color: #6777ef; transition: all 0.3s; }
.mode-tab.active { background: linear-gradient(135deg, #6777ef 0%, #4834df 100%); color: white; }
.btn-submit-tte { background: linear-gradient(135deg, #6777ef 0%, #4834df 100%); border: none; padding: 1rem 3rem; font-size: 1.1rem; font-weight: 600; border-radius: 10px; color: white; }
.word-preview { padding: 3rem; background: white; border: 2px solid #6777ef; border-radius: 12px; text-align: center; min-height: 400px; display: flex; align-items: center; justify-content: center; flex-direction: column; }
.info-badge { display: inline-block; padding: 0.5rem 1rem; background: #f0f3ff; border-radius: 8px; margin: 0.25rem; font-size: 0.9rem; }
.info-badge i { color: #6777ef; margin-right: 5px; }
.swal-wide { max-width: 700px !important; }
.swal-compact { padding: 0.5rem !important; }
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
    <h1><i class="fas fa-stamp"></i> Bubuhkan TTE - Drag & Drop</h1>
</div>

<div class="section-body">
<div class="row">
    <div class="col-lg-8">
        <div class="card card-modern">
            <div class="card-header card-header-modern">
                <h4><i class="fas fa-mouse-pointer"></i> Posisi TTE Fleksibel</h4>
            </div>
            <div class="card-body p-4">
                
                <div class="mode-tabs">
                    <button class="mode-tab active" id="tabDrag" onclick="switchMode('drag')">
                        <i class="fas fa-hand-paper"></i> Drag & Drop
                    </button>
                    <button class="mode-tab" id="tabQuick" onclick="switchMode('quick')">
                        <i class="fas fa-th"></i> Quick Position
                    </button>
                </div>
                
                <div class="upload-area" id="uploadArea">
                    <div style="font-size: 4rem; color: #6777ef; margin-bottom: 1rem;">
                        <i class="fas fa-file-upload"></i>
                    </div>
                    <div style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem;">
                        Upload Dokumen (PDF / Word)
                    </div>
                    <button type="button" class="btn btn-primary btn-lg" id="btnSelectFile">
                        <i class="fas fa-folder-open"></i> Pilih File
                    </button>
                    <input type="file" id="dokumenInput" accept=".pdf,.doc,.docx" style="display: none;">
                    <div class="mt-3" style="font-size: 0.9rem; color: #666;">
                        Format: PDF, DOC, DOCX | Maksimal 10MB
                    </div>
                </div>
                
                <div class="preview-container" id="previewContainer">
                    <div class="page-navigation" id="pageNavigation" style="display: none;">
                        <button onclick="prevPage()"><i class="fas fa-chevron-left"></i></button>
                        <span id="pageInfo">Page 1 / 1</span>
                        <button onclick="nextPage()"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <canvas id="pdfCanvas"></canvas>
                    <div class="word-preview" id="wordPreview" style="display: none;">
                        <div style="font-size: 3rem; color: #6777ef; margin-bottom: 1rem;">
                            <i class="fas fa-file-word"></i>
                        </div>
                        <h4>Dokumen Word Terdeteksi</h4>
                        <p class="text-muted">Gunakan Quick Position untuk menentukan posisi TTE</p>
                        <p class="mt-3"><strong id="wordFileName"></strong></p>
                    </div>
                    <div class="tte-stamp" id="tteStamp">
                        <img src="generate_qr.php?token=<?= $tte['token'] ?>" alt="TTE">
                        <div style="font-size: 0.7rem; color: #6777ef; font-weight: 700;">
                            <i class="fas fa-arrows-alt"></i> DRAG
                        </div>
                    </div>
                    <div class="position-info" id="positionInfo">X: 70% | Y: 80%</div>
                </div>
                
                <div id="quickPositionPanel" style="display: none;">
                    <label class="font-weight-bold mb-3" style="font-size: 1.1rem;">
                        <i class="fas fa-map-marker-alt"></i> Pilih Posisi TTE:
                    </label>
                    <div class="quick-position-grid">
                        <button type="button" class="quick-pos-btn" onclick="setQuickPosition(10, 10)">
                            <div style="font-size: 2rem;">↖️</div>
                            <div>Kiri Atas</div>
                        </button>
                        <button type="button" class="quick-pos-btn" onclick="setQuickPosition(70, 10)">
                            <div style="font-size: 2rem;">↗️</div>
                            <div>Kanan Atas</div>
                        </button>
                        <button type="button" class="quick-pos-btn" onclick="setQuickPosition(10, 80)">
                            <div style="font-size: 2rem;">↙️</div>
                            <div>Kiri Bawah</div>
                        </button>
                        <button type="button" class="quick-pos-btn" onclick="setQuickPosition(70, 80)">
                            <div style="font-size: 2rem;">↘️</div>
                            <div>Kanan Bawah</div>
                        </button>
                    </div>
                </div>
                
                <form method="POST" id="tteForm" enctype="multipart/form-data" style="display:none;">
                    <input type="hidden" name="position_x" id="positionX" value="70">
                    <input type="hidden" name="position_y" id="positionY" value="80">
                    <input type="hidden" name="target_page" id="targetPage" value="1">
                    <div class="text-center mt-4">
                        <button type="submit" name="bubuhkan_tte" class="btn btn-submit-tte">
                            <i class="fas fa-stamp"></i> Bubuhkan TTE Sekarang
                        </button>
                        <button type="button" class="btn btn-secondary btn-lg ml-2" onclick="location.reload()">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </form>
                
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card card-modern">
            <div class="card-header card-header-modern">
                <h4><i class="fas fa-id-card"></i> Informasi TTE</h4>
            </div>
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <img src="generate_qr.php?token=<?= $tte['token'] ?>" width="150" alt="QR" class="mb-3">
                </div>
                
                <div class="info-badge w-100 mb-2">
                    <i class="fas fa-user"></i>
                    <strong>Nama:</strong> <?= htmlspecialchars($tte['nama']) ?>
                </div>
                
                <div class="info-badge w-100 mb-2">
                    <i class="fas fa-id-card-alt"></i>
                    <strong>NIK:</strong> <?= htmlspecialchars($tte['nik']) ?>
                </div>
                
                <?php if (!empty($tte['jabatan'])): ?>
                <div class="info-badge w-100 mb-2">
                    <i class="fas fa-briefcase"></i>
                    <strong>Jabatan:</strong> <?= htmlspecialchars($tte['jabatan']) ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($tte['instansi'])): ?>
                <div class="info-badge w-100 mb-2">
                    <i class="fas fa-building"></i>
                    <strong>Instansi:</strong> <?= htmlspecialchars($tte['instansi']) ?>
                </div>
                <?php endif; ?>
                
                <div class="info-badge w-100 mb-2">
                    <i class="fas fa-key"></i>
                    <strong>Token:</strong> <small><?= substr($tte['token'], 0, 20) ?>...</small>
                </div>
                
                <div class="info-badge w-100 mb-2">
                    <i class="fas fa-calendar"></i>
                    <strong>Dibuat:</strong> <?= date('d/m/Y', strtotime($tte['created_at'])) ?>
                </div>
                
                <div class="info-badge w-100 mb-2">
                    <i class="fas fa-check-circle text-success"></i>
                    <strong>Status:</strong> <span class="text-success text-uppercase"><?= $tte['status'] ?></span>
                </div>
                
                <hr class="my-3">
                
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i>
                    <small><strong>Tips:</strong> Untuk PDF multi-halaman, gunakan navigasi halaman untuk memilih halaman yang akan ditandatangani.</small>
                </div>
            </div>
        </div>
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
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

let currentMode = 'drag';
let currentX = 70, currentY = 80;
let pdfDoc = null;
let currentPage = 1;
let totalPages = 1;
let currentFileType = '';


document.getElementById('btnSelectFile').onclick = function() {
    document.getElementById('dokumenInput').click();
};


document.getElementById('dokumenInput').onchange = function(e) {
    if (this.files && this.files[0]) {
        handleFileSelect(this.files[0]);
    }
};

function handleFileSelect(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    currentFileType = ext;
    
    if (!['pdf', 'doc', 'docx'].includes(ext)) {
        Swal.fire({
            icon: 'error',
            title: 'Format Tidak Valid',
            text: 'Hanya file PDF, DOC, dan DOCX yang diperbolehkan'
        });
        return;
    }
    
    if (file.size > 10485760) {
        Swal.fire({
            icon: 'error',
            title: 'File Terlalu Besar',
            text: 'Ukuran file maksimal 10MB'
        });
        return;
    }
    
    document.getElementById('uploadArea').style.display = 'none';
    document.getElementById('tteForm').style.display = 'block';
    
    // Add file to form
    const dt = new DataTransfer();
    dt.items.add(file);
    
    let input = document.querySelector('input[name="dokumen"]');
    if (!input) {
        input = document.createElement('input');
        input.type = 'file';
        input.name = 'dokumen';
        input.style.display = 'none';
        document.getElementById('tteForm').appendChild(input);
    }
    input.files = dt.files;
    
    if (ext === 'pdf') {
        const reader = new FileReader();
        reader.onload = function(e) {
            loadPDF(e.target.result);
        };
        reader.readAsArrayBuffer(file);
    } else {
 
        document.getElementById('wordFileName').textContent = file.name;
        document.getElementById('previewContainer').classList.add('active');
        document.getElementById('wordPreview').style.display = 'flex';
        document.getElementById('pdfCanvas').style.display = 'none';
        document.getElementById('tteStamp').style.display = 'none';
        document.getElementById('positionInfo').style.display = 'none';
        switchMode('quick');
    }
}

function loadPDF(data) {
    pdfjsLib.getDocument({data: data}).promise.then(function(pdf) {
        pdfDoc = pdf;
        totalPages = pdf.numPages;
        currentPage = 1;
        
        if (totalPages > 1) {
            document.getElementById('pageNavigation').style.display = 'block';
        }
        
        renderPage(currentPage);
    });
}

function renderPage(pageNum) {
    pdfDoc.getPage(pageNum).then(function(page) {
        const canvas = document.getElementById('pdfCanvas');
        const ctx = canvas.getContext('2d');
        const viewport = page.getViewport({scale: 1.5});
        
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        
        page.render({canvasContext: ctx, viewport: viewport}).promise.then(function() {
            document.getElementById('previewContainer').classList.add('active');
            document.getElementById('pdfCanvas').style.display = 'block';
            document.getElementById('wordPreview').style.display = 'none';
            document.getElementById('tteStamp').style.display = 'block';
            document.getElementById('positionInfo').style.display = 'block';
            updatePosition(currentX, currentY);
            updatePageInfo();
        });
    });
}

function updatePageInfo() {
    document.getElementById('pageInfo').textContent = `Page ${currentPage} / ${totalPages}`;
    document.getElementById('targetPage').value = currentPage;
}

function prevPage() {
    if (currentPage > 1) {
        currentPage--;
        renderPage(currentPage);
    }
}

function nextPage() {
    if (currentPage < totalPages) {
        currentPage++;
        renderPage(currentPage);
    }
}

// Drag functionality
const stamp = document.getElementById('tteStamp');
let dragging = false;

stamp.onmousedown = function(e) {
    dragging = true;
    e.preventDefault();
};

document.onmousemove = function(e) {
    if (!dragging) return;
    
    const container = document.getElementById('previewContainer');
    const rect = container.getBoundingClientRect();
    
    let x = e.clientX - rect.left - 35; // Adjusted from 50 to 35 (half of 70px)
    let y = e.clientY - rect.top - 35;  // Adjusted from 50 to 35
    
    x = Math.max(0, Math.min(x, rect.width - 70));  // Adjusted from 100 to 70
    y = Math.max(0, Math.min(y, rect.height - 70)); // Adjusted from 100 to 70
    
    const xp = (x / rect.width) * 100;
    const yp = (y / rect.height) * 100;
    
    updatePosition(xp, yp);
};

document.onmouseup = function() {
    dragging = false;
};

function updatePosition(x, y) {
    currentX = x;
    currentY = y;
    stamp.style.left = x + '%';
    stamp.style.top = y + '%';
    document.getElementById('positionX').value = x.toFixed(2);
    document.getElementById('positionY').value = y.toFixed(2);
    document.getElementById('positionInfo').textContent = `X: ${x.toFixed(0)}% | Y: ${y.toFixed(0)}%`;
}

function switchMode(mode) {
    currentMode = mode;

    document.getElementById('tabDrag').classList.remove('active');
    document.getElementById('tabQuick').classList.remove('active');
    
    if (mode === 'drag') {
        document.getElementById('tabDrag').classList.add('active');
        if (currentFileType === 'pdf' && pdfDoc) {
            document.getElementById('previewContainer').style.display = 'block';
            document.getElementById('quickPositionPanel').style.display = 'none';
        } else if (currentFileType !== 'pdf') {
            Swal.fire({
                icon: 'info',
                title: 'Mode Drag & Drop',
                text: 'Mode drag & drop hanya tersedia untuk file PDF. Gunakan Quick Position untuk file Word.'
            });
            switchMode('quick');
        }
    } else {
        document.getElementById('tabQuick').classList.add('active');
        if (currentFileType === 'pdf') {
            document.getElementById('previewContainer').style.display = 'none';
        }
        document.getElementById('quickPositionPanel').style.display = 'block';
    }
}

function setQuickPosition(x, y) {
    currentX = x;
    currentY = y;
    document.getElementById('positionX').value = x;
    document.getElementById('positionY').value = y;
    updatePosition(x, y);
    
    Swal.fire({
        icon: 'success',
        title: 'Posisi Dipilih',
        text: `X: ${x}%, Y: ${y}%`,
        timer: 1500,
        showConfirmButton: false
    });
}

<?php if ($success_data): ?>
Swal.fire({
    icon: 'success',
    title: 'TTE Berhasil Dibubuhkan! ✅',
    html: `
        <div style="text-align: left; padding: 0.5rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; margin-bottom: 0.8rem;">
                <div style="background: #f8f9fa; padding: 0.6rem; border-radius: 8px;">
                    <div style="font-size: 0.75rem; color: #666; margin-bottom: 0.3rem;">📄 Dokumen Asli</div>
                    <div style="font-size: 0.85rem; font-weight: 600;"><?= htmlspecialchars($success_data['original_name']) ?></div>
                </div>
                <div style="background: #f8f9fa; padding: 0.6rem; border-radius: 8px;">
                    <div style="font-size: 0.75rem; color: #666; margin-bottom: 0.3rem;">📝 File Output</div>
                    <div style="font-size: 0.85rem; font-weight: 600;"><?= htmlspecialchars($success_data['filename']) ?></div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.6rem; margin-bottom: 0.8rem;">
                <div style="background: #e3f2fd; padding: 0.5rem; border-radius: 6px; text-align: center;">
                    <div style="font-size: 0.7rem; color: #1976d2;">💾 Ukuran</div>
                    <div style="font-size: 0.8rem; font-weight: 600; color: #1565c0;"><?= number_format($success_data['filesize']/1024, 1) ?> KB</div>
                </div>
                <div style="background: #e8f5e9; padding: 0.5rem; border-radius: 6px; text-align: center;">
                    <div style="font-size: 0.7rem; color: #388e3c;">📄 Tipe</div>
                    <div style="font-size: 0.8rem; font-weight: 600; color: #2e7d32;"><?= $success_data['file_type'] ?></div>
                </div>
                <div style="background: #fff3e0; padding: 0.5rem; border-radius: 6px; text-align: center;">
                    <div style="font-size: 0.7rem; color: #f57c00;">⏰ Waktu</div>
                    <div style="font-size: 0.75rem; font-weight: 600; color: #ef6c00;"><?= date('H:i:s', strtotime($success_data['timestamp'])) ?></div>
                </div>
            </div>
            
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 0.8rem; border-radius: 8px; color: white; margin-bottom: 0.8rem;">
                <div style="font-size: 0.75rem; opacity: 0.9; margin-bottom: 0.4rem;">✍️ Ditandatangani oleh:</div>
                <div style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.3rem;"><?= htmlspecialchars($success_data['tte_nama']) ?></div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem;">
                    <div>🆔 <?= htmlspecialchars($success_data['tte_nik']) ?></div>
                    <?php if (!empty($success_data['tte_jabatan'])): ?>
                    <div>💼 <?= htmlspecialchars($success_data['tte_jabatan']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="background: #f5f5f5; padding: 0.6rem; border-radius: 6px; font-size: 0.75rem; color: #666;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span>🔑 Token: <code style="background: white; padding: 2px 6px; border-radius: 3px;"><?= substr($success_data['tte_token'], 0, 16) ?>...</code></span>
                    <?php if ($success_data['file_type'] === 'PDF'): ?>
                    <span>📑 Halaman: <strong><?= $success_data['target_page'] ?></strong></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    `,
    width: '700px',
    showDenyButton: true,
    showCancelButton: true,
    confirmButtonText: '<i class="fas fa-download"></i> Download',
    denyButtonText: '<i class="fas fa-plus"></i> Upload Lagi',
    cancelButtonText: '<i class="fas fa-times"></i> Tutup',
    confirmButtonColor: '#28a745',
    denyButtonColor: '#6777ef',
    cancelButtonColor: '#6c757d',
    customClass: {
        popup: 'swal-wide',
        htmlContainer: 'swal-compact'
    }
}).then((result) => {
    if (result.isConfirmed) {
        const link = document.createElement('a');
        link.href = '<?= $success_data['web_path'] ?>';
        link.download = '<?= $success_data['filename'] ?>';
        link.click();
        
        Swal.fire({
            icon: 'success',
            title: 'Download Dimulai',
            text: 'File sedang diunduh...',
            timer: 2000,
            showConfirmButton: false
        }).then(() => {
            location.reload();
        });
    } else if (result.isDenied) {
        location.reload();
    }
});
<?php endif; ?>

<?php if ($error_message): ?>
Swal.fire({
    icon: 'error',
    title: 'Terjadi Kesalahan',
    text: '<?= addslashes($error_message) ?>',
    confirmButtonText: 'OK'
});
<?php endif; ?>
</script>

</body>
</html>s