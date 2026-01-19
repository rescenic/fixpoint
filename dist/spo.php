<?php
include 'security.php'; 
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');
require_once 'NomorDokumenGenerator.php'; // ← TAMBAHAN: Include generator

$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

// Ambil nama user
$nama_user = $_SESSION['nama_user'] ?? $_SESSION['nama'] ?? $_SESSION['username'] ?? '';
if ($nama_user === '' && $user_id > 0) {
    $qUser = mysqli_query($conn, "SELECT nama FROM users WHERE id = $user_id LIMIT 1");
    if ($qUser && mysqli_num_rows($qUser) === 1) $nama_user = mysqli_fetch_assoc($qUser)['nama'];
}
if ($nama_user === '') $nama_user = 'User ID #' . $user_id;

// Cek akses user
$current_file = basename(__FILE__);
$rAkses = mysqli_query($conn, "SELECT 1 FROM akses_menu 
           JOIN menu ON akses_menu.menu_id = menu.id 
           WHERE akses_menu.user_id = '$user_id' AND menu.file_menu = '$current_file'");
if (!$rAkses || mysqli_num_rows($rAkses) == 0) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini.'); window.location.href='dashboard.php';</script>";
    exit;
}

// Ambil data perusahaan
$qPerusahaan = mysqli_query($conn, "SELECT * FROM perusahaan LIMIT 1");
$perusahaan = mysqli_fetch_assoc($qPerusahaan);

// ==== HANDLE FORM SIMPAN ==== (MODIFIED - dengan auto generate nomor)
if(isset($_POST['simpan'])){
    // ===== PERUBAHAN: Ambil unit_kerja_id dan generate nomor =====
    $unit_kerja_id = intval($_POST['unit_kerja_id']);
    
    // Generate nomor SPO otomatis
    $generator = new NomorDokumenGenerator($conn);
    try {
        $hasil = $generator->generateNomor(
            jenis_dokumen: 'SPO',
            unit_kerja_id: $unit_kerja_id,
            tabel_referensi: 'spo',
            referensi_id: 0, // Sementara 0, akan diupdate setelah insert
            user_id: $user_id
        );
        $no_dokumen = mysqli_real_escape_string($conn, $hasil['nomor_lengkap']);
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error generate nomor: ' . $e->getMessage();
        header("Location: spo.php");
        exit;
    }
    // ===== AKHIR PERUBAHAN =====
    
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $no_revisi = mysqli_real_escape_string($conn, $_POST['no_revisi']);
    $tanggal_terbit = mysqli_real_escape_string($conn, $_POST['tanggal_terbit']);
    
    $pengertian = mysqli_real_escape_string($conn, $_POST['pengertian']);
    
    // Proses array tujuan
    $tujuan_array = array_filter($_POST['tujuan'], function($val) { return trim($val) != ''; });
    $tujuan = json_encode(array_values($tujuan_array));
    
    // Proses array kebijakan
    $kebijakan_array = array_filter($_POST['kebijakan'], function($val) { return trim($val) != ''; });
    $kebijakan = json_encode(array_values($kebijakan_array));
    
    // Proses array prosedur
    $prosedur_array = array_filter($_POST['prosedur'], function($val) { return trim($val) != ''; });
    $prosedur = json_encode(array_values($prosedur_array));
    
    // Proses array unit terkait
    $unit_terkait_array = array_filter($_POST['unit_terkait'], function($val) { return trim($val) != ''; });
    $unit_terkait = json_encode(array_values($unit_terkait_array));
    
    $penandatangan_id = intval($_POST['penandatangan_id']);
    $dibuat_oleh = $user_id;
    $template_id = intval($_POST['template_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Ambil data penandatangan untuk snapshot dari tabel users DAN tte_user
    $qPjb = mysqli_query($conn, "
        SELECT u.nama, u.jabatan, u.nik, t.token, t.file_hash 
        FROM users u
        LEFT JOIN tte_user t ON u.id = t.user_id AND t.status = 'aktif'
        WHERE u.id = $penandatangan_id 
        LIMIT 1
    ");
    $dataPjb = mysqli_fetch_assoc($qPjb);
    
    $penandatangan_nama = mysqli_real_escape_string($conn, $dataPjb['nama']);
    $penandatangan_jabatan = mysqli_real_escape_string($conn, $dataPjb['jabatan']);
    $penandatangan_nik = mysqli_real_escape_string($conn, $dataPjb['nik']);
    $tte_token = $dataPjb['token'] ?? NULL;
    $tte_hash = $dataPjb['file_hash'] ?? NULL;
    
    // Generate nomor halaman (hitung otomatis nanti saat generate PDF)
    $halaman_total = 1; // Default, akan diupdate saat generate
    
    // Insert ke database
    $sql = "INSERT INTO spo (
        judul, no_dokumen, no_revisi, tanggal_terbit, halaman_total,
        pengertian, tujuan, kebijakan, prosedur, unit_terkait,
        penandatangan_id, penandatangan_nama, penandatangan_jabatan, penandatangan_nik,
        tte_token, tte_hash,
        dibuat_oleh, template_id, status, created_at
    ) VALUES (
        '$judul', '$no_dokumen', '$no_revisi', '$tanggal_terbit', $halaman_total,
        '$pengertian', '$tujuan', '$kebijakan', '$prosedur', '$unit_terkait',
        $penandatangan_id, '$penandatangan_nama', '$penandatangan_jabatan', '$penandatangan_nik',
        ".($tte_token ? "'$tte_token'" : "NULL").", ".($tte_hash ? "'$tte_hash'" : "NULL").",
        $dibuat_oleh, $template_id, '$status', NOW()
    )";
    
    if(mysqli_query($conn, $sql)){
        $spo_id = mysqli_insert_id($conn);
        
        // ===== TAMBAHAN: Update referensi_id di log_penomoran =====
        mysqli_query($conn, "
            UPDATE log_penomoran 
            SET referensi_id = $spo_id 
            WHERE tabel_referensi = 'spo' 
              AND referensi_id = 0 
              AND nomor_lengkap = '$no_dokumen'
              AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        // ===== AKHIR TAMBAHAN =====
        
        // Jika status = 'final', langsung generate PDF
        if($status == 'final'){
            // Cek apakah file generate_spo_pdf.php ada
            if(!file_exists('generate_spo_pdf.php')) {
                $_SESSION['flash_message'] = 'SPO tersimpan dengan nomor: '.$no_dokumen.'. File generate_spo_pdf.php tidak ditemukan!';
                header("Location: spo.php?tab=data");
                exit;
            }
            
            // Cek apakah DOMPDF library ada
            $dompdf_paths = ['dompdf/autoload.inc.php', 'libs/dompdf/autoload.inc.php', 'vendor/autoload.php'];
            $dompdf_found = false;
            foreach($dompdf_paths as $path) {
                if(file_exists($path)) {
                    $dompdf_found = true;
                    break;
                }
            }
            
            if(!$dompdf_found) {
                $_SESSION['flash_message'] = 'SPO tersimpan dengan nomor: '.$no_dokumen.'. Library DOMPDF tidak ditemukan! Cek path: dompdf/autoload.inc.php';
                header("Location: spo.php?tab=data");
                exit;
            }
            
            try {
                include 'generate_spo_pdf.php';
                $result = generateSPO_PDF($spo_id);
                
                if($result['success']){
                    // Update file_path dan halaman_total
                    $file_path = mysqli_real_escape_string($conn, $result['file_path']);
                    $halaman_total = intval($result['total_pages']);
                    mysqli_query($conn, "UPDATE spo SET file_path='$file_path', halaman_total=$halaman_total WHERE id=$spo_id");
                    $_SESSION['flash_message'] = 'SPO berhasil disimpan dengan nomor: '.$no_dokumen.' dan PDF berhasil di-generate!';
                } else {
                    $_SESSION['flash_message'] = 'SPO tersimpan dengan nomor: '.$no_dokumen.'. Gagal generate PDF: '.$result['error'];
                }
            } catch (Exception $e) {
                $_SESSION['flash_message'] = 'SPO tersimpan dengan nomor: '.$no_dokumen.'. Error: ' . $e->getMessage();
            }
        } else {
            $_SESSION['flash_message'] = 'SPO berhasil disimpan sebagai draft dengan nomor: '.$no_dokumen;
        }
    } else {
        $_SESSION['flash_message'] = 'Error: '.mysqli_error($conn);
    }
    
    header("Location: spo.php?tab=data");
    exit;
}

// ==== HANDLE UPDATE ====
if(isset($_POST['update'])){
    $id = intval($_POST['id']);
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $no_dokumen = mysqli_real_escape_string($conn, $_POST['no_dokumen']);
    $no_revisi = mysqli_real_escape_string($conn, $_POST['no_revisi']);
    $tanggal_terbit = mysqli_real_escape_string($conn, $_POST['tanggal_terbit']);
    
    $pengertian = mysqli_real_escape_string($conn, $_POST['pengertian']);
    
    $tujuan_array = array_filter($_POST['tujuan'], function($val) { return trim($val) != ''; });
    $tujuan = json_encode(array_values($tujuan_array));
    
    $kebijakan_array = array_filter($_POST['kebijakan'], function($val) { return trim($val) != ''; });
    $kebijakan = json_encode(array_values($kebijakan_array));
    
    $prosedur_array = array_filter($_POST['prosedur'], function($val) { return trim($val) != ''; });
    $prosedur = json_encode(array_values($prosedur_array));
    
    $unit_terkait_array = array_filter($_POST['unit_terkait'], function($val) { return trim($val) != ''; });
    $unit_terkait = json_encode(array_values($unit_terkait_array));
    
    $penandatangan_id = intval($_POST['penandatangan_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Ambil data penandatangan untuk snapshot
    $qPjb = mysqli_query($conn, "
        SELECT u.nama, u.jabatan, u.nik, t.token, t.file_hash 
        FROM users u
        LEFT JOIN tte_user t ON u.id = t.user_id AND t.status = 'aktif'
        WHERE u.id = $penandatangan_id 
        LIMIT 1
    ");
    $dataPjb = mysqli_fetch_assoc($qPjb);
    
    $penandatangan_nama = mysqli_real_escape_string($conn, $dataPjb['nama']);
    $penandatangan_jabatan = mysqli_real_escape_string($conn, $dataPjb['jabatan']);
    $penandatangan_nik = mysqli_real_escape_string($conn, $dataPjb['nik']);
    $tte_token = $dataPjb['token'] ?? NULL;
    $tte_hash = $dataPjb['file_hash'] ?? NULL;
    
    $sql = "UPDATE spo SET 
        judul='$judul', 
        no_dokumen='$no_dokumen', 
        no_revisi='$no_revisi', 
        tanggal_terbit='$tanggal_terbit',
        pengertian='$pengertian',
        tujuan='$tujuan',
        kebijakan='$kebijakan',
        prosedur='$prosedur',
        unit_terkait='$unit_terkait',
        penandatangan_id=$penandatangan_id,
        penandatangan_nama='$penandatangan_nama',
        penandatangan_jabatan='$penandatangan_jabatan',
        penandatangan_nik='$penandatangan_nik',
        tte_token=".($tte_token ? "'$tte_token'" : "NULL").",
        tte_hash=".($tte_hash ? "'$tte_hash'" : "NULL").",
        status='$status',
        updated_at=NOW()
        WHERE id=$id";
    
    if(mysqli_query($conn, $sql)){
        // Jika status diubah ke 'final', generate PDF
        if($status == 'final'){
            // Cek file dependencies
            if(!file_exists('generate_spo_pdf.php')) {
                $_SESSION['flash_message'] = 'SPO terupdate. File generate_spo_pdf.php tidak ditemukan!';
                header("Location: spo.php?tab=data");
                exit;
            }
            
            // Cek apakah DOMPDF library ada
            $dompdf_paths = ['dompdf/autoload.inc.php', 'libs/dompdf/autoload.inc.php', 'vendor/autoload.php'];
            $dompdf_found = false;
            foreach($dompdf_paths as $path) {
                if(file_exists($path)) {
                    $dompdf_found = true;
                    break;
                }
            }
            
            if(!$dompdf_found) {
                $_SESSION['flash_message'] = 'SPO terupdate. Library DOMPDF tidak ditemukan!';
                header("Location: spo.php?tab=data");
                exit;
            }
            
            try {
                include 'generate_spo_pdf.php';
                $result = generateSPO_PDF($id);
                
                if($result['success']){
                    $file_path = mysqli_real_escape_string($conn, $result['file_path']);
                    $halaman_total = intval($result['total_pages']);
                    mysqli_query($conn, "UPDATE spo SET file_path='$file_path', halaman_total=$halaman_total WHERE id=$id");
                    $_SESSION['flash_message'] = 'SPO berhasil diupdate dan PDF di-generate!';
                } else {
                    $_SESSION['flash_message'] = 'SPO terupdate. Gagal generate PDF: '.$result['error'];
                }
            } catch (Exception $e) {
                $_SESSION['flash_message'] = 'SPO terupdate. Error: ' . $e->getMessage();
            }
        } else {
            $_SESSION['flash_message'] = 'SPO berhasil diupdate.';
        }
    } else {
        $_SESSION['flash_message'] = 'Error: '.mysqli_error($conn);
    }
    
    header("Location: spo.php?tab=data");
    exit;
}

// Tentukan tab aktif
$activeTab = $_GET['tab'] ?? 'input';

// Filter untuk tab data
$filter_judul = $_GET['judul'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Pagination setup
$limit = 10; 
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Hitung total data
$sqlCount = "SELECT COUNT(*) AS total FROM spo WHERE 1=1 ";
if($filter_judul != '') $sqlCount .= " AND judul LIKE '%".mysqli_real_escape_string($conn, $filter_judul)."%' ";
if($filter_status != '') $sqlCount .= " AND status = '".mysqli_real_escape_string($conn, $filter_status)."' ";
$totalData = mysqli_fetch_assoc(mysqli_query($conn, $sqlCount))['total'];
$totalPages = ceil($totalData / $limit);

// Ambil data SPO
$sqlData = "SELECT s.*, u.nama as nama_pembuat 
            FROM spo s
            LEFT JOIN users u ON s.dibuat_oleh = u.id
            WHERE 1=1 ";
if($filter_judul != '') $sqlData .= " AND s.judul LIKE '%".mysqli_real_escape_string($conn, $filter_judul)."%' ";
if($filter_status != '') $sqlData .= " AND s.status = '".mysqli_real_escape_string($conn, $filter_status)."' ";
$sqlData .= " ORDER BY s.created_at DESC LIMIT $limit OFFSET $offset";
$data_spo = mysqli_query($conn, $sqlData);

// Array untuk modal edit
$modals = [];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Buat SPO (Standar Operasional Prosedur)</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<style>
  .spo-table { font-size: 13px; white-space: nowrap; }
  .spo-table th, .spo-table td { padding: 6px 10px; vertical-align: middle; }
  .flash-center {
    position: fixed; top: 20%; left: 50%; transform: translate(-50%, -50%);
    z-index: 1050; min-width: 300px; max-width: 90%; text-align: center;
    padding: 15px; border-radius: 8px; font-weight: 500;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
  }
  .dynamic-list-item {
    margin-bottom: 8px;
    position: relative;
  }
  .dynamic-list-item .remove-btn {
    position: absolute;
    right: -35px;
    top: 50%;
    transform: translateY(-50%);
  }
  .add-more-btn {
    margin-top: 10px;
  }
  .template-preview {
    border: 2px solid #ddd;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.3s;
  }
  .template-preview:hover, .template-preview.active {
    border-color: #007bff;
    background-color: #f0f8ff;
  }
  .template-preview img {
    max-width: 100%;
    height: auto;
    margin-bottom: 5px;
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
        <div class="section-body">

        <?php if(isset($_SESSION['flash_message'])): ?>
          <div class="alert alert-info flash-center" id="flashMsg">
            <?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?>
          </div>
        <?php endif; ?>

          <div class="card">
            <div class="card-header">
              <h4 class="mb-0">SPO (Standar Operasional Prosedur)</h4>
            </div>
            <div class="card-body">
              <ul class="nav nav-tabs" id="spoTab" role="tablist">
                <li class="nav-item">
                  <a class="nav-link <?= ($activeTab=='input')?'active':'' ?>" id="input-tab" data-toggle="tab" href="#input" role="tab">Buat SPO</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link <?= ($activeTab=='data')?'active':'' ?>" id="data-tab" data-toggle="tab" href="#data" role="tab">Data SPO</a>
                </li>
              </ul>

              <div class="tab-content mt-3">
                
                <!-- ===== TAB INPUT ===== -->
                <div class="tab-pane fade <?= ($activeTab=='input')?'show active':'' ?>" id="input" role="tabpanel">
                  <form method="POST" id="formSPO">
                    
                    <!-- Pilih Template -->
                    <div class="row mb-4">
                      <div class="col-12">
                        <h5>1. Pilih Template</h5>
                        <div class="row">
                          <div class="col-md-4">
                            <div class="template-preview active" data-template="1">
                              <input type="radio" name="template_id" value="1" checked style="display:none;">
                              <strong>Template Formal Box</strong>
                              <p class="text-muted small mb-0">Format dengan kotak-kotak (seperti contoh RS Permata Hati)</p>
                            </div>
                          </div>
                          <div class="col-md-4">
                            <div class="template-preview" data-template="2">
                              <input type="radio" name="template_id" value="2" style="display:none;">
                              <strong>Template Modern Clean</strong>
                              <p class="text-muted small mb-0">Format minimalis tanpa border</p>
                            </div>
                          </div>
                          <div class="col-md-4">
                            <div class="template-preview" data-template="3">
                              <input type="radio" name="template_id" value="3" style="display:none;">
                              <strong>Template Compact</strong>
                              <p class="text-muted small mb-0">Format padat untuk dokumen panjang</p>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <hr>

                    <!-- Header Dokumen -->
                    <div class="row">
                      <div class="col-12"><h5>2. Header Dokumen</h5></div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Judul SPO <span class="text-danger">*</span></label>
                          <input type="text" name="judul" class="form-control" placeholder="Contoh: Pemusnahan Rekam Medis Elektronik" required>
                        </div>
                        
                        <!-- ===== PERUBAHAN: Unit Kerja + Preview Nomor ===== -->
                        <div class="form-group">
                          <label>Unit Kerja <span class="text-danger">*</span></label>
                          <select name="unit_kerja_id" id="unit_kerja" class="form-control" required>
                            <option value="">-- Pilih Unit Kerja --</option>
                            <?php 
                            $qUnit = mysqli_query($conn, "SELECT id, nama_unit, kode_unit FROM unit_kerja WHERE kode_unit IS NOT NULL ORDER BY nama_unit");
                            while($unit = mysqli_fetch_assoc($qUnit)):
                            ?>
                              <option value="<?= $unit['id'] ?>">
                                <?= htmlspecialchars($unit['nama_unit']) ?> (<?= $unit['kode_unit'] ?>)
                              </option>
                            <?php endwhile; ?>
                          </select>
                        </div>
                        
                        <div class="form-group">
                          <label>Preview Nomor Dokumen</label>
                          <input type="text" id="preview_nomor" class="form-control bg-light" readonly placeholder="Pilih unit kerja untuk melihat preview nomor">
                          <small class="text-muted">Nomor akan di-generate otomatis saat menyimpan</small>
                        </div>
                        <!-- ===== AKHIR PERUBAHAN ===== -->
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Nomor Revisi <span class="text-danger">*</span></label>
                          <select name="no_revisi" class="form-control" required>
                            <option value="A">A (Revisi Pertama)</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                            <option value="E">E</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                          </select>
                        </div>
                        <div class="form-group">
                          <label>Tanggal Terbit <span class="text-danger">*</span></label>
                          <input type="date" name="tanggal_terbit" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                      </div>
                    </div>

                    <hr>

                    <!-- Isi Dokumen -->
                    <div class="row">
                      <div class="col-12"><h5>3. Isi Dokumen</h5></div>
                      
                      <!-- Pengertian -->
                      <div class="col-12">
                        <div class="form-group">
                          <label>Pengertian <span class="text-danger">*</span></label>
                          <textarea name="pengertian" class="form-control" rows="4" required placeholder="Jelaskan pengertian dari SPO ini..."></textarea>
                        </div>
                      </div>

                      <!-- Tujuan -->
                      <div class="col-12">
                        <div class="form-group">
                          <label>Tujuan <span class="text-danger">*</span></label>
                          <div id="tujuan-container">
                            <div class="dynamic-list-item">
                              <input type="text" name="tujuan[]" class="form-control" placeholder="Tujuan 1" required>
                            </div>
                          </div>
                          <button type="button" class="btn btn-sm btn-outline-primary add-more-btn" onclick="addField('tujuan')">
                            <i class="fas fa-plus"></i> Tambah Tujuan
                          </button>
                        </div>
                      </div>

                      <!-- Kebijakan -->
                      <div class="col-12">
                        <div class="form-group">
                          <label>Kebijakan/Dasar Hukum <span class="text-danger">*</span></label>
                          <div id="kebijakan-container">
                            <div class="dynamic-list-item">
                              <input type="text" name="kebijakan[]" class="form-control" placeholder="Contoh: UU No. 44 Tahun 2009 tentang Rumah Sakit" required>
                            </div>
                          </div>
                          <button type="button" class="btn btn-sm btn-outline-primary add-more-btn" onclick="addField('kebijakan')">
                            <i class="fas fa-plus"></i> Tambah Kebijakan
                          </button>
                        </div>
                      </div>

                      <!-- Prosedur -->
                      <div class="col-12">
                        <div class="form-group">
                          <label>Prosedur/Langkah-Langkah <span class="text-danger">*</span></label>
                          <div id="prosedur-container">
                            <div class="dynamic-list-item">
                              <textarea name="prosedur[]" class="form-control" rows="2" placeholder="Langkah 1" required></textarea>
                            </div>
                          </div>
                          <button type="button" class="btn btn-sm btn-outline-primary add-more-btn" onclick="addField('prosedur')">
                            <i class="fas fa-plus"></i> Tambah Langkah
                          </button>
                        </div>
                      </div>

                      <!-- Unit Terkait -->
                      <div class="col-12">
                        <div class="form-group">
                          <label>Unit Terkait <span class="text-danger">*</span></label>
                          <div id="unit_terkait-container">
                            <div class="dynamic-list-item">
                              <input type="text" name="unit_terkait[]" class="form-control" placeholder="Contoh: Instalasi Rekam Medis" required>
                            </div>
                          </div>
                          <button type="button" class="btn btn-sm btn-outline-primary add-more-btn" onclick="addField('unit_terkait')">
                            <i class="fas fa-plus"></i> Tambah Unit
                          </button>
                        </div>
                      </div>
                    </div>

                    <hr>

                    <!-- Penandatangan -->
                    <div class="row">
                      <div class="col-12"><h5>4. Penandatangan</h5></div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Ditandatangani Oleh <span class="text-danger">*</span></label>
                          <select name="penandatangan_id" id="penandatangan" class="form-control" required>
                            <option value="">-- Pilih Pejabat Penandatangan --</option>
                            <?php 
                            // Query list pejabat untuk form input
                            // Menampilkan user dengan status active ATAU pending
                            $list_pejabat_form = mysqli_query($conn, "
                                SELECT u.id, u.nama, u.jabatan, u.nik, u.status,
                                       CASE WHEN t.status = 'aktif' THEN 'Ya' ELSE 'Tidak' END as tte_status
                                FROM users u
                                LEFT JOIN tte_user t ON u.id = t.user_id AND t.status = 'aktif'
                                WHERE u.status IN ('active', 'pending')
                                ORDER BY 
                                    CASE WHEN u.status = 'active' THEN 1 ELSE 2 END,
                                    u.nama ASC
                            ");
                            
                            // Debug: cek apakah query berhasil
                            if(!$list_pejabat_form) {
                                echo "<!-- Error Query: " . mysqli_error($conn) . " -->";
                            } else {
                                echo "<!-- Total User: " . mysqli_num_rows($list_pejabat_form) . " -->";
                            }
                            
                            if($list_pejabat_form && mysqli_num_rows($list_pejabat_form) > 0):
                                while($pjb = mysqli_fetch_assoc($list_pejabat_form)):
                            ?>
                              <option 
                                value="<?= $pjb['id'] ?>" 
                                data-nama="<?= htmlspecialchars($pjb['nama']) ?>"
                                data-jabatan="<?= htmlspecialchars($pjb['jabatan'] ?? '-') ?>"
                                data-nik="<?= htmlspecialchars($pjb['nik']) ?>"
                                data-tte="<?= $pjb['tte_status'] ?>">
                                <?= htmlspecialchars($pjb['nama']) ?> 
                                <?php if(!empty($pjb['jabatan'])): ?>
                                  - <?= htmlspecialchars($pjb['jabatan']) ?>
                                <?php endif; ?>
                                <?php if($pjb['tte_status'] == 'Ya'): ?>
                                  ✓ TTE
                                <?php endif; ?>
                                <?php if($pjb['status'] == 'pending'): ?>
                                  (Pending)
                                <?php endif; ?>
                              </option>
                            <?php 
                                endwhile;
                            else:
                            ?>
                              <option value="" disabled>Tidak ada pejabat yang tersedia</option>
                            <?php endif; ?>
                          </select>
                          <small class="text-muted">Semua user aktif akan ditampilkan. Pilih yang berwenang menandatangani SPO.</small>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="form-group">
                          <label>NIK Penandatangan</label>
                          <input type="text" id="nik_ttd" class="form-control" readonly>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="form-group">
                          <label>Status TTE</label>
                          <input type="text" id="tte_status" class="form-control" readonly>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Dibuat Oleh</label>
                          <input type="text" class="form-control" value="<?= htmlspecialchars($nama_user) ?>" readonly>
                          <small class="text-muted">User yang sedang login</small>
                        </div>
                      </div>
                    </div>

                    <hr>

                    <!-- Status & Submit -->
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Status Dokumen <span class="text-danger">*</span></label>
                          <select name="status" class="form-control" required>
                            <option value="draft">Draft (Simpan saja, belum generate PDF)</option>
                            <option value="final">Final (Langsung generate PDF)</option>
                          </select>
                        </div>
                      </div>
                    </div>

                    <div class="form-group">
                      <button type="submit" name="simpan" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan SPO
                      </button>
                      <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset Form
                      </button>
                    </div>

                  </form>
                </div>

                <!-- ===== TAB DATA ===== -->
                <div class="tab-pane fade <?= ($activeTab=='data')?'show active':'' ?>" id="data" role="tabpanel">
                  
                  <!-- Filter Pencarian -->
                  <form method="GET" class="form-inline mb-3">
                    <input type="hidden" name="tab" value="data">
                    <div class="form-group mr-2">
                      <input type="text" name="judul" class="form-control" placeholder="Cari Judul" value="<?= htmlspecialchars($filter_judul) ?>">
                    </div>
                    <div class="form-group mr-2">
                      <select name="status" class="form-control">
                        <option value="">-- Semua Status --</option>
                        <option value="draft" <?= ($filter_status=='draft')?'selected':'' ?>>Draft</option>
                        <option value="final" <?= ($filter_status=='final')?'selected':'' ?>>Final</option>
                        <option value="revisi" <?= ($filter_status=='revisi')?'selected':'' ?>>Revisi</option>
                      </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm mr-2">
                      <i class="fas fa-search"></i> Cari
                    </button>
                    <a href="spo.php?tab=data" class="btn btn-secondary btn-sm">
                      <i class="fas fa-sync"></i> Reset
                    </a>
                  </form>

                  <div class="table-responsive">
                    <table class="table table-bordered spo-table">
                      <thead class="thead-dark">
                        <tr>
                          <th>No</th>
                          <th>Judul SPO</th>
                          <th>No. Dokumen</th>
                          <th>Rev</th>
                          <th>Tanggal Terbit</th>
                          <th>Penandatangan</th>
                          <th>Dibuat Oleh</th>
                          <th>Status</th>
                          <th>File PDF</th>
                          <th>Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php 
                        if(mysqli_num_rows($data_spo)==0):
                        ?>
                          <tr><td colspan="10" class="text-center">Belum ada data SPO.</td></tr>
                        <?php 
                        else:
                          $no = $offset + 1;
                          while($spo=mysqli_fetch_assoc($data_spo)):
                            $badge_status = '';
                            if($spo['status']=='draft') $badge_status = 'badge-warning';
                            elseif($spo['status']=='final') $badge_status = 'badge-success';
                            elseif($spo['status']=='revisi') $badge_status = 'badge-info';
                        ?>
                        <tr>
                          <td><?= $no++ ?></td>
                          <td><?= htmlspecialchars($spo['judul']) ?></td>
                          <td><?= htmlspecialchars($spo['no_dokumen']) ?></td>
                          <td><?= htmlspecialchars($spo['no_revisi']) ?></td>
                          <td><?= date('d-m-Y', strtotime($spo['tanggal_terbit'])) ?></td>
                          <td>
                            <?= htmlspecialchars($spo['penandatangan_nama']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($spo['penandatangan_jabatan']) ?></small>
                          </td>
                          <td><?= htmlspecialchars($spo['nama_pembuat'] ?? 'N/A') ?></td>
                          <td>
                            <span class="badge <?= $badge_status ?>"><?= strtoupper($spo['status']) ?></span>
                          </td>
                          <td>
                            <?php if(!empty($spo['file_path']) && file_exists($spo['file_path'])): ?>
                              <a href="<?= htmlspecialchars($spo['file_path']) ?>" target="_blank" class="btn btn-sm btn-info">
                                <i class="fas fa-file-pdf"></i> Lihat
                              </a>
                            <?php else: ?>
                              <span class="text-muted">-</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editModal<?= $spo['id'] ?>">
                              <i class="fas fa-edit"></i>
                            </button>
                            <a href="hapus_spo.php?id=<?= $spo['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus SPO ini?')">
                              <i class="fas fa-trash"></i>
                            </a>
                          </td>
                        </tr>

                        <?php
                        // Prepare data untuk modal edit
                        $tujuan_edit = json_decode($spo['tujuan'], true) ?? [];
                        $kebijakan_edit = json_decode($spo['kebijakan'], true) ?? [];
                        $prosedur_edit = json_decode($spo['prosedur'], true) ?? [];
                        $unit_terkait_edit = json_decode($spo['unit_terkait'], true) ?? [];
                        
                        // Modal Edit
                        $modals[] = '
                        <div class="modal fade" id="editModal'.$spo['id'].'" tabindex="-1" role="dialog">
                          <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
                            <div class="modal-content">
                              <form method="POST" action="spo.php?tab=data">
                                <input type="hidden" name="id" value="'.$spo['id'].'">
                                <div class="modal-header">
                                  <h5 class="modal-title">Edit SPO: '.htmlspecialchars($spo['judul']).'</h5>
                                  <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                  <div class="row">
                                    <div class="col-md-6">
                                      <div class="form-group">
                                        <label>Judul SPO</label>
                                        <input type="text" name="judul" class="form-control" value="'.htmlspecialchars($spo['judul']).'" required>
                                      </div>
                                    </div>
                                    <div class="col-md-6">
                                      <div class="form-group">
                                        <label>Nomor Dokumen</label>
                                        <input type="text" name="no_dokumen" class="form-control" value="'.htmlspecialchars($spo['no_dokumen']).'" required>
                                      </div>
                                    </div>
                                    <div class="col-md-4">
                                      <div class="form-group">
                                        <label>Nomor Revisi</label>
                                        <select name="no_revisi" class="form-control" required>';
                                        $revisi_options = ['A','B','C','D','E','1','2','3'];
                                        foreach($revisi_options as $rev){
                                            $sel = ($spo['no_revisi']==$rev)?'selected':'';
                                            $modals[count($modals)-1] .= '<option value="'.$rev.'" '.$sel.'>'.$rev.'</option>';
                                        }
                        $modals[count($modals)-1] .= '</select>
                                      </div>
                                    </div>
                                    <div class="col-md-4">
                                      <div class="form-group">
                                        <label>Tanggal Terbit</label>
                                        <input type="date" name="tanggal_terbit" class="form-control" value="'.$spo['tanggal_terbit'].'" required>
                                      </div>
                                    </div>
                                    <div class="col-md-4">
                                      <div class="form-group">
                                        <label>Status</label>
                                        <select name="status" class="form-control" required>
                                          <option value="draft" '.($spo['status']=='draft'?'selected':'').'>Draft</option>
                                          <option value="final" '.($spo['status']=='final'?'selected':'').'>Final</option>
                                          <option value="revisi" '.($spo['status']=='revisi'?'selected':'').'>Revisi</option>
                                        </select>
                                      </div>
                                    </div>
                                    <div class="col-12">
                                      <div class="form-group">
                                        <label>Pengertian</label>
                                        <textarea name="pengertian" class="form-control" rows="3" required>'.htmlspecialchars($spo['pengertian']).'</textarea>
                                      </div>
                                    </div>
                                    <div class="col-12">
                                      <div class="form-group">
                                        <label>Tujuan</label>';
                                        foreach($tujuan_edit as $tj){
                                            $modals[count($modals)-1] .= '<input type="text" name="tujuan[]" class="form-control mb-2" value="'.htmlspecialchars($tj).'">';
                                        }
                        $modals[count($modals)-1] .= '
                                      </div>
                                    </div>
                                    <div class="col-12">
                                      <div class="form-group">
                                        <label>Kebijakan</label>';
                                        foreach($kebijakan_edit as $kb){
                                            $modals[count($modals)-1] .= '<input type="text" name="kebijakan[]" class="form-control mb-2" value="'.htmlspecialchars($kb).'">';
                                        }
                        $modals[count($modals)-1] .= '
                                      </div>
                                    </div>
                                    <div class="col-12">
                                      <div class="form-group">
                                        <label>Prosedur</label>';
                                        foreach($prosedur_edit as $pr){
                                            $modals[count($modals)-1] .= '<textarea name="prosedur[]" class="form-control mb-2" rows="2">'.htmlspecialchars($pr).'</textarea>';
                                        }
                        $modals[count($modals)-1] .= '
                                      </div>
                                    </div>
                                    <div class="col-12">
                                      <div class="form-group">
                                        <label>Unit Terkait</label>';
                                        foreach($unit_terkait_edit as $ut){
                                            $modals[count($modals)-1] .= '<input type="text" name="unit_terkait[]" class="form-control mb-2" value="'.htmlspecialchars($ut).'">';
                                        }
                        $modals[count($modals)-1] .= '
                                      </div>
                                    </div>
                                    <div class="col-12">
                                      <div class="form-group">
                                        <label>Penandatangan</label>
                                        <select name="penandatangan_id" class="form-control" required>';
                                        $list_pejabat2 = mysqli_query($conn, "
                                            SELECT u.id, u.nama, u.jabatan, u.nik
                                            FROM users u
                                            WHERE u.status IN ('active', 'pending')
                                            ORDER BY u.nama ASC
                                        ");
                                        while($p2=mysqli_fetch_assoc($list_pejabat2)){
                                            $sel = ($spo['penandatangan_id']==$p2['id'])?'selected':'';
                                            $modals[count($modals)-1] .= '<option value="'.$p2['id'].'" '.$sel.'>'.htmlspecialchars($p2['nama']).' - '.htmlspecialchars($p2['jabatan']).'</option>';
                                        }
                        $modals[count($modals)-1] .= '</select>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                  <button type="submit" name="update" class="btn btn-primary">Simpan Perubahan</button>
                                </div>
                              </form>
                            </div>
                          </div>
                        </div>';
                          endwhile; 
                        endif;
                        ?>
                      </tbody>
                    </table>
                  </div>

                  <!-- Pagination -->
                  <?php if($totalPages>1): ?>
                  <nav aria-label="Page navigation">
                    <ul class="pagination">
                      <?php for($i=1;$i<=$totalPages;$i++): ?>
                        <li class="page-item <?= ($i==$page)?'active':'' ?>">
                          <a class="page-link" href="?tab=data&page=<?= $i ?>&judul=<?= urlencode($filter_judul) ?>&status=<?= urlencode($filter_status) ?>"><?= $i ?></a>
                        </li>
                      <?php endfor; ?>
                    </ul>
                  </nav>
                  <?php endif; ?>

                </div> <!-- End Tab Data -->
              </div> <!-- End Tab Content -->
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

<?php foreach($modals as $modal) echo $modal; ?>

<script>
$(document).ready(function() {
  // Auto hide flash message
  setTimeout(function(){ $("#flashMsg").fadeOut("slow"); }, 3000);
  
  // Template selection
  $('.template-preview').on('click', function(){
    $('.template-preview').removeClass('active');
    $(this).addClass('active');
    $(this).find('input[type="radio"]').prop('checked', true);
  });
  
  // Auto-fill NIK dan TTE status ketika pilih penandatangan
  $('#penandatangan').on('change', function(){
    var selected = $(this).find(':selected');
    $('#nik_ttd').val(selected.data('nik'));
    $('#tte_status').val(selected.data('tte') == 'Ya' ? 'TTE Aktif' : 'TTE Tidak Aktif');
  });
  
  // ===== TAMBAHAN: Preview nomor saat pilih unit =====
  $('#unit_kerja').on('change', function(){
    var unit_id = $(this).val();
    
    if(unit_id) {
      $('#preview_nomor').val('Loading...');
      
      $.ajax({
        url: 'ajax_preview_nomor.php',
        type: 'POST',
        data: {
          jenis_dokumen: 'SPO',
          unit_kerja_id: unit_id
        },
        dataType: 'json',
        success: function(response) {
          if(response.success) {
            $('#preview_nomor').val(response.nomor_preview);
          } else {
            $('#preview_nomor').val('Error: ' + response.error);
            alert('Gagal preview nomor: ' + response.error);
          }
        },
        error: function(xhr, status, error) {
          $('#preview_nomor').val('Error koneksi');
          console.error('AJAX Error:', error);
        }
      });
    } else {
      $('#preview_nomor').val('');
    }
  });
  // ===== AKHIR TAMBAHAN =====
});

// Function untuk menambah field dinamis
function addField(type) {
  var container = $('#' + type + '-container');
  var fieldHTML = '';
  
  if(type == 'prosedur') {
    fieldHTML = `
      <div class="dynamic-list-item">
        <textarea name="${type}[]" class="form-control" rows="2" placeholder="Langkah berikutnya"></textarea>
        <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeField(this)">
          <i class="fas fa-times"></i>
        </button>
      </div>`;
  } else {
    fieldHTML = `
      <div class="dynamic-list-item">
        <input type="text" name="${type}[]" class="form-control" placeholder="${type.charAt(0).toUpperCase() + type.slice(1)} berikutnya">
        <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeField(this)">
          <i class="fas fa-times"></i>
        </button>
      </div>`;
  }
  
  container.append(fieldHTML);
}

// Function untuk menghapus field dinamis
function removeField(btn) {
  $(btn).closest('.dynamic-list-item').remove();
}
</script>
</body>
</html>