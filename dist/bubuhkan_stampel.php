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

// Ambil E-Stampel aktif (bukan per user, tapi global untuk perusahaan)
$qStampel = $conn->prepare("SELECT * FROM e_stampel WHERE status='aktif' LIMIT 1");
$qStampel->execute();
$dataStampel = $qStampel->get_result();
$stampel = $dataStampel->fetch_assoc();

if (!$stampel) {
    header("Location: buat_stampel.php");
    exit;
}

$required_folders = [
    __DIR__ . '/uploads/temp',
    __DIR__ . '/uploads/stamped',
    __DIR__ . '/uploads/stampel_temp',
    __DIR__ . '/uploads/phpword_temp'
];

foreach ($required_folders as $folder) {
    if (!is_dir($folder)) {
        @mkdir($folder, 0755, true);
    }
}

$autoload_file = __DIR__ . '/lib/autoload.php';
if (file_exists($autoload_file)) {
    require_once $autoload_file;
}

$success_data = null;
$error_message = null;

function processPDFCustomPosition($filepath, $stampel_file, $position_x, $position_y, $stampel, $filename, $target_page) {
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
                $stampel_size = 30; // Ukuran stempel 30mm (lebih besar dari TTE 20mm)
                $x = ($position_x / 100) * $size['width'];
                $y = ($position_y / 100) * $size['height'];

                if (file_exists($stampel_file)) {
                    $pdf->Image($stampel_file, $x, $y, $stampel_size, $stampel_size);
                }

                $pdf->SetFont('Arial', '', 0.1);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetXY(0, 0);
                $pdf->Cell(0, 0, 'STAMPEL-TOKEN:' . $stampel['token'], 0, 0, 'L');
            }
        }

        $output_dir = __DIR__ . '/uploads/stamped/';
        $output_file = $output_dir . $filename;
        
        $pdf->SetCreator('FixPoint E-Stampel System');
        $pdf->SetAuthor($stampel['nama_perusahaan']);
        $pdf->SetSubject('STAMPEL-TOKEN:' . $stampel['token']);
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

function processWordCustomPosition($filepath, $stampel_file, $position_x, $position_y, $stampel, $filename) {
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
        
        if ($position_x > 50) {
            $table->addCell(7000);
            $cell = $table->addCell(5000);
        } else {
            $cell = $table->addCell(5000);
            $table->addCell(7000);
        }
        
        if (file_exists($stampel_file)) {
            $cell->addImage($stampel_file, [
                'width' => 80,  
                'height' => 80, 
                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
            ]);
        }
        
        $cell->addText('E-Stampel Resmi', 
            ['size' => 8, 'bold' => true], 
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 60]
        );
        
        $cell->addText($stampel['nama_perusahaan'], 
            ['size' => 9, 'bold' => true], 
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 50]
        );
        
        $cell->addText($stampel['kota'] . ', ' . $stampel['provinsi'], 
            ['size' => 7], 
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 50]
        );
        
        $cell->addText(date('d F Y, H:i') . ' WIB', 
            ['size' => 6, 'italic' => true, 'color' => '666666'],  
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        
        $section->addText('STAMPEL-TOKEN:' . $stampel['token'], ['size' => 1, 'color' => 'FFFFFF']);
        
        $output_dir = __DIR__ . '/uploads/stamped/';
        $output_file = $output_dir . pathinfo($filename, PATHINFO_FILENAME) . '_stamped.docx';
        
        $phpWord->getDocInfo()->setCreator('FixPoint E-Stampel System');
        $phpWord->getDocInfo()->setTitle('Dokumen Berstempel');
        $phpWord->getDocInfo()->setDescription('STAMPEL-TOKEN:' . $stampel['token']);
        
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bubuhkan_stampel'])) {
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
            $error_message = "Format file tidak didukung. Gunakan PDF, DOC, atau DOCX.";
        } elseif ($file['size'] > 10485760) {
            $error_message = "Ukuran file maksimal 10 MB.";
        } else {
            $temp_dir = __DIR__ . '/uploads/temp/';
            $temp_file = $temp_dir . time() . '_' . basename($file['name']);
            
            if (move_uploaded_file($file['tmp_name'], $temp_file)) {
                // Generate stempel image dari generate_qr_stampel.php
                $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
                $current_dir = dirname($_SERVER['PHP_SELF']);
                $stampel_url = $base_url . $current_dir . "/generate_qr_stampel.php?token=" . urlencode($stampel['token']);
                
                $stampel_img_data = @file_get_contents($stampel_url);
                
                if (!$stampel_img_data) {
                    $error_message = "Gagal generate stempel. Periksa file generate_qr_stampel.php";
                } else {
                    $stampel_file = __DIR__ . '/uploads/stampel_temp/stampel_' . time() . '.png';
                    file_put_contents($stampel_file, $stampel_img_data);
                    
                    $output_file = null;
                    $new_filename = pathinfo($file['name'], PATHINFO_FILENAME) . '_stamped_' . time();
                    
                    if ($ext === 'pdf') {
                        $new_filename .= '.pdf';
                        $output_file = processPDFCustomPosition($temp_file, $stampel_file, $position_x, $position_y, $stampel, $new_filename, $target_page);
                    } else {
                        $new_filename .= '.docx';
                        $output_file = processWordCustomPosition($temp_file, $stampel_file, $position_x, $position_y, $stampel, $new_filename);
                    }
                    
                    @unlink($stampel_file);
                    
                    if ($output_file && file_exists($output_file)) {
                        $file_hash = hash_file('sha256', $output_file);
                        
                        $success_data = [
                            'filename' => basename($output_file),
                            'original_name' => $file['name'],
                            'file_path' => $output_file,
                            'web_path' => 'uploads/stamped/' . basename($output_file),
                            'filesize' => filesize($output_file),
                            'file_type' => $ext === 'pdf' ? 'PDF' : 'DOCX',
                            'timestamp' => date('Y-m-d H:i:s'),
                            'stampel_nama' => $stampel['nama_perusahaan'],
                            'stampel_kota' => $stampel['kota'],
                            'stampel_token' => $stampel['token'],
                            'target_page' => $target_page
                        ];
                    } else {
                        $error_message = "Gagal memproses dokumen. Pastikan library PDF/Word tersedia.";
                    }
                }
            } else {
                $error_message = "Gagal menyimpan file sementara.";
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
<title>Bubuhkan E-Stampel</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css">
<style>
.upload-container {
    max-width: 900px;
    margin: 0 auto;
}

.upload-box {
    border: 3px dashed #28a745;
    border-radius: 15px;
    padding: 50px 20px;
    text-align: center;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    cursor: pointer;
    transition: all 0.3s ease;
}

.upload-box:hover {
    border-color: #20c997;
    transform: scale(1.02);
}

.upload-box i {
    font-size: 60px;
    color: #28a745;
    margin-bottom: 15px;
}

#previewContainer {
    position: relative;
    border: 2px solid #dee2e6;
    border-radius: 10px;
    overflow: hidden;
    background: #f8f9fa;
    min-height: 500px;
    display: none;
}

#previewContainer.active {
    display: block;
}

#pdfCanvas {
    width: 100%;
    height: auto;
}

#stampelStamp {
    position: absolute;
    width: 100px;
    height: 100px;
    cursor: move;
    opacity: 0.9;
    transition: opacity 0.2s;
    z-index: 10;
}

#stampelStamp:hover {
    opacity: 1;
}

#stampelStamp img {
    width: 100%;
    height: 100%;
    filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
}

.page-nav {
    background: #28a745;
    color: white;
    padding: 10px;
    border-radius: 8px;
    text-align: center;
    margin-top: 15px;
}

.page-nav button {
    background: white;
    color: #28a745;
    border: none;
    padding: 5px 15px;
    border-radius: 5px;
    margin: 0 5px;
    cursor: pointer;
}

.page-nav button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.quick-btn {
    width: 100%;
    margin: 5px 0;
    padding: 12px;
}

#wordPreview {
    padding: 40px;
    text-align: center;
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
    <h1><i class="fas fa-stamp"></i> Bubuhkan E-Stampel</h1>
</div>

<div class="section-body">
<div class="upload-container">

<!-- E-Stampel Info -->
<div class="card mb-4">
<div class="card-header bg-success text-white">
    <h4 class="text-white"><i class="fas fa-stamp"></i> E-Stampel Aktif</h4>
</div>
<div class="card-body">
    <div class="row align-items-center">
        <div class="col-md-2 text-center">
            <img src="generate_qr_stampel.php?token=<?= $stampel['token'] ?>" 
                 style="width: 100px; height: 100px; border: 2px solid #28a745; border-radius: 10px;">
        </div>
        <div class="col-md-10">
            <h5><?= htmlspecialchars($stampel['nama_perusahaan']) ?></h5>
            <p class="mb-0">
                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($stampel['kota']) ?>, <?= htmlspecialchars($stampel['provinsi']) ?><br>
                <i class="fas fa-calendar"></i> Dibuat: <?= date('d F Y', strtotime($stampel['created_at'])) ?>
            </p>
        </div>
    </div>
</div>
</div>

<!-- Upload Form -->
<div class="card">
<div class="card-header bg-primary text-white">
    <h4 class="text-white"><i class="fas fa-upload"></i> Upload Dokumen</h4>
</div>
<div class="card-body">
    
    <form method="POST" enctype="multipart/form-data" id="formStampel">
        
        <div class="upload-box" onclick="document.getElementById('dokumen').click()">
            <i class="fas fa-cloud-upload-alt"></i>
            <h5>Klik untuk Upload Dokumen</h5>
            <p class="text-muted mb-0">PDF, DOC, DOCX (Max 10MB)</p>
            <input type="file" 
                   id="dokumen" 
                   name="dokumen" 
                   accept=".pdf,.doc,.docx" 
                   style="display: none;"
                   onchange="handleFileSelect(this.files[0])">
        </div>
        
        <div id="fileInfo" class="mt-3" style="display: none;">
            <div class="alert alert-info">
                <strong>File:</strong> <span id="fileName"></span> | 
                <strong>Ukuran:</strong> <span id="fileSize"></span>
            </div>
        </div>
        
        <div class="mt-3" id="modeSelection" style="display: none;">
            <button type="button" class="btn btn-primary" id="btnDrag" onclick="switchMode('drag')">
                <i class="fas fa-mouse-pointer"></i> Drag & Drop
            </button>
            <button type="button" class="btn btn-outline-primary" id="btnQuick" onclick="switchMode('quick')">
                <i class="fas fa-th"></i> Quick Position
            </button>
        </div>
        
        <div id="previewContainer" class="mt-3">
            <canvas id="pdfCanvas"></canvas>
            <div id="wordPreview" style="display: none;">
                <i class="fas fa-file-word fa-5x text-primary mb-3"></i>
                <h5>File Word Detected</h5>
                <p>Use Quick Position to set stamp location</p>
            </div>
            
            <div id="stampelStamp">
                <img src="generate_qr_stampel.php?token=<?= $stampel['token'] ?>">
            </div>
        </div>
        
        <div id="pageNavigation" class="page-nav" style="display: none;">
            <button type="button" onclick="prevPage()" id="prevBtn">◀ Prev</button>
            <span id="pageInfo">Page 1 / 1</span>
            <button type="button" onclick="nextPage()" id="nextBtn">Next ▶</button>
        </div>
        
        <div id="quickPanel" style="display: none;" class="mt-3">
            <div class="row">
                <div class="col-6 col-md-3">
                    <button type="button" class="btn btn-outline-success quick-btn" onclick="setQuickPosition(10, 10)">
                        ↖ Kiri Atas
                    </button>
                </div>
                <div class="col-6 col-md-3">
                    <button type="button" class="btn btn-outline-success quick-btn" onclick="setQuickPosition(85, 10)">
                        ↗ Kanan Atas
                    </button>
                </div>
                <div class="col-6 col-md-3">
                    <button type="button" class="btn btn-outline-success quick-btn" onclick="setQuickPosition(10, 75)">
                        ↙ Kiri Bawah
                    </button>
                </div>
                <div class="col-6 col-md-3">
                    <button type="button" class="btn btn-outline-success quick-btn" onclick="setQuickPosition(85, 75)">
                        ↘ Kanan Bawah
                    </button>
                </div>
            </div>
        </div>
        
        <div id="posInfo" class="alert alert-success mt-3" style="display: none;">
            <strong>Posisi:</strong> <span id="posText">X: 70%, Y: 80%</span>
        </div>
        
        <input type="hidden" name="position_x" id="positionX" value="70">
        <input type="hidden" name="position_y" id="positionY" value="80">
        <input type="hidden" name="target_page" id="targetPage" value="1">
        
        <div class="text-center mt-4" id="submitBtn" style="display: none;">
            <button type="submit" name="bubuhkan_stampel" class="btn btn-success btn-lg">
                <i class="fas fa-stamp"></i> Bubuhkan E-Stampel
            </button>
            <button type="button" class="btn btn-secondary btn-lg" onclick="location.reload()">
                <i class="fas fa-redo"></i> Reset
            </button>
        </div>
        
    </form>
    
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
<script src="assets/js/stisla.js"></script>
<script src="assets/js/scripts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

let pdfDoc = null;
let currentPage = 1;
let totalPages = 0;
let currentX = 70;
let currentY = 80;
let currentMode = 'drag';
let currentFileType = '';

function handleFileSelect(file) {
    if (!file) return;
    
    $('#fileInfo').show();
    $('#fileName').text(file.name);
    $('#fileSize').text((file.size / 1024 / 1024).toFixed(2) + ' MB');
    $('#modeSelection').show();
    $('#submitBtn').show();
    
    const ext = file.name.split('.').pop().toLowerCase();
    currentFileType = ext;
    
    if (ext === 'pdf') {
        loadPDF(file);
    } else {
        $('#previewContainer').addClass('active');
        $('#pdfCanvas').hide();
        $('#wordPreview').show();
        $('#stampelStamp').hide();
        $('#pageNavigation').hide();
        switchMode('quick');
    }
}

function loadPDF(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const typedarray = new Uint8Array(e.target.result);
        
        pdfjsLib.getDocument(typedarray).promise.then(function(pdf) {
            pdfDoc = pdf;
            totalPages = pdf.numPages;
            currentPage = 1;
            renderPage(currentPage);
            $('#pageNavigation').show();
        });
    };
    reader.readAsArrayBuffer(file);
}

function renderPage(pageNum) {
    pdfDoc.getPage(pageNum).then(function(page) {
        const canvas = document.getElementById('pdfCanvas');
        const ctx = canvas.getContext('2d');
        const viewport = page.getViewport({scale: 1.5});
        
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        
        page.render({canvasContext: ctx, viewport: viewport}).promise.then(function() {
            $('#previewContainer').addClass('active');
            $('#pdfCanvas').show();
            $('#wordPreview').hide();
            $('#stampelStamp').show();
            $('#posInfo').show();
            updatePosition(currentX, currentY);
            updatePageInfo();
        });
    });
}

function updatePageInfo() {
    $('#pageInfo').text(`Page ${currentPage} / ${totalPages}`);
    $('#targetPage').val(currentPage);
    $('#prevBtn').prop('disabled', currentPage <= 1);
    $('#nextBtn').prop('disabled', currentPage >= totalPages);
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

const stamp = document.getElementById('stampelStamp');
let dragging = false;

stamp.onmousedown = function(e) {
    dragging = true;
    e.preventDefault();
};

document.onmousemove = function(e) {
    if (!dragging) return;
    
    const container = document.getElementById('previewContainer');
    const rect = container.getBoundingClientRect();
    
    let x = e.clientX - rect.left - 50;
    let y = e.clientY - rect.top - 50;
    
    x = Math.max(0, Math.min(x, rect.width - 100));
    y = Math.max(0, Math.min(y, rect.height - 100));
    
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
    $('#positionX').val(x.toFixed(2));
    $('#positionY').val(y.toFixed(2));
    $('#posText').text(`X: ${x.toFixed(0)}% | Y: ${y.toFixed(0)}%`);
}

function switchMode(mode) {
    currentMode = mode;
    $('#btnDrag, #btnQuick').removeClass('btn-primary').addClass('btn-outline-primary');
    
    if (mode === 'drag') {
        $('#btnDrag').removeClass('btn-outline-primary').addClass('btn-primary');
        if (currentFileType === 'pdf' && pdfDoc) {
            $('#previewContainer').show();
            $('#quickPanel').hide();
        } else {
            alert('Drag & Drop hanya untuk PDF');
            switchMode('quick');
        }
    } else {
        $('#btnQuick').removeClass('btn-outline-primary').addClass('btn-primary');
        if (currentFileType === 'pdf') {
            $('#previewContainer').hide();
        }
        $('#quickPanel').show();
    }
}

function setQuickPosition(x, y) {
    currentX = x;
    currentY = y;
    $('#positionX').val(x);
    $('#positionY').val(y);
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
    title: 'E-Stampel Berhasil! ✅',
    html: `
        <p><strong>File:</strong> <?= htmlspecialchars($success_data['filename']) ?></p>
        <p><strong>Ukuran:</strong> <?= number_format($success_data['filesize']/1024, 1) ?> KB</p>
        <p><strong>Perusahaan:</strong> <?= htmlspecialchars($success_data['stampel_nama']) ?></p>
    `,
    showDenyButton: true,
    confirmButtonText: '<i class="fas fa-download"></i> Download',
    denyButtonText: '<i class="fas fa-plus"></i> Upload Lagi',
    confirmButtonColor: '#28a745'
}).then((result) => {
    if (result.isConfirmed) {
        const link = document.createElement('a');
        link.href = '<?= $success_data['web_path'] ?>';
        link.download = '<?= $success_data['filename'] ?>';
        link.click();
        setTimeout(() => location.reload(), 1000);
    } else if (result.isDenied) {
        location.reload();
    }
});
<?php endif; ?>

<?php if ($error_message): ?>
Swal.fire({
    icon: 'error',
    title: 'Error',
    text: '<?= addslashes($error_message) ?>'
});
<?php endif; ?>
</script>

</body>
</html>