<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

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
    
    // Generate nomor Surat Edaran otomatis
    $generator = new NomorDokumenGenerator($conn);
    try {
        $hasil = $generator->generateNomor(
            jenis_dokumen: 'SE',
            unit_kerja_id: $unit_kerja_id,
            tabel_referensi: 'surat_edaran',
            referensi_id: 0, // Sementara 0, akan diupdate setelah insert
            user_id: $user_id
        );
        $nomor_surat = mysqli_real_escape_string($conn, $hasil['nomor_lengkap']);
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error generate nomor: ' . $e->getMessage();
        header("Location: surat_edaran.php");
        exit;
    }
    // ===== AKHIR PERUBAHAN =====
    
    $tanggal_surat = mysqli_real_escape_string($conn, $_POST['tanggal_surat']);
    $perihal = mysqli_real_escape_string($conn, $_POST['perihal']);
    $pembukaan = mysqli_real_escape_string($conn, $_POST['pembukaan']);
    
    // Proses array isi poin
    $isi_poin_array = array_filter($_POST['isi_poin'], function($val) { return trim($val) != ''; });
    $isi_poin = json_encode(array_values($isi_poin_array));
    
    $tanggal_berlaku = mysqli_real_escape_string($conn, $_POST['tanggal_berlaku']);
    $penutup = mysqli_real_escape_string($conn, $_POST['penutup']);
    
    // Proses array tembusan
    $tembusan_array = array_filter($_POST['tembusan'], function($val) { return trim($val) != ''; });
    $tembusan = json_encode(array_values($tembusan_array));
    
    $penandatangan_id = intval($_POST['penandatangan_id']);
    $dibuat_oleh = $user_id;
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
    
    // Insert ke database
    $sql = "INSERT INTO surat_edaran (
        nomor_surat, tanggal_surat, perihal, pembukaan, isi_poin,
        tanggal_berlaku, penutup, tembusan,
        penandatangan_id, penandatangan_nama, penandatangan_jabatan, penandatangan_nik,
        tte_token, tte_hash,
        dibuat_oleh, status, created_at
    ) VALUES (
        '$nomor_surat', '$tanggal_surat', '$perihal', '$pembukaan', '$isi_poin',
        ".($tanggal_berlaku ? "'$tanggal_berlaku'" : "NULL").", '$penutup', '$tembusan',
        $penandatangan_id, '$penandatangan_nama', '$penandatangan_jabatan', '$penandatangan_nik',
        ".($tte_token ? "'$tte_token'" : "NULL").", ".($tte_hash ? "'$tte_hash'" : "NULL").",
        $dibuat_oleh, '$status', NOW()
    )";
    
    if(mysqli_query($conn, $sql)){
        $edaran_id = mysqli_insert_id($conn);
        
        // ===== TAMBAHAN: Update referensi_id di log_penomoran =====
        mysqli_query($conn, "
            UPDATE log_penomoran 
            SET referensi_id = $edaran_id 
            WHERE tabel_referensi = 'surat_edaran' 
              AND referensi_id = 0 
              AND nomor_lengkap = '$nomor_surat'
              AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        // ===== AKHIR TAMBAHAN =====
        
        // Jika status = 'final', langsung generate PDF
        if($status == 'final'){
            if(!file_exists('generate_edaran_pdf.php')) {
                $_SESSION['flash_message'] = 'Surat Edaran tersimpan dengan nomor: '.$nomor_surat.'. File generate_edaran_pdf.php tidak ditemukan!';
                header("Location: surat_edaran.php?tab=data");
                exit;
            }
            
            try {
                include 'generate_edaran_pdf.php';
                $result = generateEdaran_PDF($edaran_id);
                
                if($result['success']){
                    $file_path = mysqli_real_escape_string($conn, $result['file_path']);
                    mysqli_query($conn, "UPDATE surat_edaran SET file_path='$file_path' WHERE id=$edaran_id");
                    $_SESSION['flash_message'] = 'Surat Edaran berhasil disimpan dengan nomor: '.$nomor_surat.' dan PDF berhasil di-generate!';
                } else {
                    $_SESSION['flash_message'] = 'Surat Edaran tersimpan dengan nomor: '.$nomor_surat.'. Gagal generate PDF: '.$result['error'];
                }
            } catch (Exception $e) {
                $_SESSION['flash_message'] = 'Surat Edaran tersimpan dengan nomor: '.$nomor_surat.'. Error: ' . $e->getMessage();
            }
        } else {
            $_SESSION['flash_message'] = 'Surat Edaran berhasil disimpan sebagai draft dengan nomor: '.$nomor_surat;
        }
    } else {
        $_SESSION['flash_message'] = 'Error: '.mysqli_error($conn);
    }
    
    header("Location: surat_edaran.php?tab=data");
    exit;
}

// ==== HANDLE UPDATE ==== (tidak perlu generate nomor lagi, nomor sudah fix)
if(isset($_POST['update'])){
    $id = intval($_POST['id']);
    $nomor_surat = mysqli_real_escape_string($conn, $_POST['nomor_surat']); // Nomor tidak diubah
    $tanggal_surat = mysqli_real_escape_string($conn, $_POST['tanggal_surat']);
    $perihal = mysqli_real_escape_string($conn, $_POST['perihal']);
    $pembukaan = mysqli_real_escape_string($conn, $_POST['pembukaan']);
    
    $isi_poin_array = array_filter($_POST['isi_poin'], function($val) { return trim($val) != ''; });
    $isi_poin = json_encode(array_values($isi_poin_array));
    
    $tanggal_berlaku = mysqli_real_escape_string($conn, $_POST['tanggal_berlaku']);
    $penutup = mysqli_real_escape_string($conn, $_POST['penutup']);
    
    $tembusan_array = array_filter($_POST['tembusan'], function($val) { return trim($val) != ''; });
    $tembusan = json_encode(array_values($tembusan_array));
    
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
    
    $sql = "UPDATE surat_edaran SET 
        nomor_surat='$nomor_surat',
        tanggal_surat='$tanggal_surat',
        perihal='$perihal',
        pembukaan='$pembukaan',
        isi_poin='$isi_poin',
        tanggal_berlaku=".($tanggal_berlaku ? "'$tanggal_berlaku'" : "NULL").",
        penutup='$penutup',
        tembusan='$tembusan',
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
        if($status == 'final'){
            try {
                include 'generate_edaran_pdf.php';
                $result = generateEdaran_PDF($id);
                
                if($result['success']){
                    $file_path = mysqli_real_escape_string($conn, $result['file_path']);
                    mysqli_query($conn, "UPDATE surat_edaran SET file_path='$file_path' WHERE id=$id");
                    $_SESSION['flash_message'] = 'Surat Edaran berhasil diupdate dan PDF di-generate!';
                } else {
                    $_SESSION['flash_message'] = 'Surat Edaran terupdate. Gagal generate PDF: '.$result['error'];
                }
            } catch (Exception $e) {
                $_SESSION['flash_message'] = 'Surat Edaran terupdate. Error: ' . $e->getMessage();
            }
        } else {
            $_SESSION['flash_message'] = 'Surat Edaran berhasil diupdate.';
        }
    } else {
        $_SESSION['flash_message'] = 'Error: '.mysqli_error($conn);
    }
    
    header("Location: surat_edaran.php?tab=data");
    exit;
}

// Tentukan tab aktif
$activeTab = $_GET['tab'] ?? 'input';

// Filter untuk tab data
$filter_perihal = $_GET['perihal'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Pagination setup
$limit = 10; 
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Hitung total data
$sqlCount = "SELECT COUNT(*) AS total FROM surat_edaran WHERE 1=1 ";
if($filter_perihal != '') $sqlCount .= " AND perihal LIKE '%".mysqli_real_escape_string($conn, $filter_perihal)."%' ";
if($filter_status != '') $sqlCount .= " AND status = '".mysqli_real_escape_string($conn, $filter_status)."' ";
$totalData = mysqli_fetch_assoc(mysqli_query($conn, $sqlCount))['total'];
$totalPages = ceil($totalData / $limit);

// Ambil data surat edaran
$sqlData = "SELECT se.*, u.nama as nama_pembuat 
            FROM surat_edaran se
            LEFT JOIN users u ON se.dibuat_oleh = u.id
            WHERE 1=1 ";
if($filter_perihal != '') $sqlData .= " AND se.perihal LIKE '%".mysqli_real_escape_string($conn, $filter_perihal)."%' ";
if($filter_status != '') $sqlData .= " AND se.status = '".mysqli_real_escape_string($conn, $filter_status)."' ";
$sqlData .= " ORDER BY se.created_at DESC LIMIT $limit OFFSET $offset";
$data_edaran = mysqli_query($conn, $sqlData);

// Array untuk modal edit
$modals = [];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Surat Edaran</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<style>
  .edaran-table { font-size: 13px; white-space: nowrap; }
  .edaran-table th, .edaran-table td { padding: 6px 10px; vertical-align: middle; }
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
  
  /* ===== MODAL EDIT - CUSTOM WIDTH (LEBAR) ===== */
  .modal-edit-custom {
    max-width: 95%;
    width: 1400px;
  }
  
  .modal-edit-custom .modal-body {
    max-height: 75vh;
    overflow-y: auto;
    padding: 25px;
  }
  
  .modal-edit-custom .modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 25px;
  }
  
  .modal-edit-custom .modal-header .modal-title {
    font-size: 18px;
    font-weight: 600;
  }
  
  .modal-edit-custom .modal-header .close {
    color: white;
    opacity: 0.8;
    font-size: 32px;
  }
  
  .modal-edit-custom .modal-header .close:hover {
    opacity: 1;
  }
  
  .modal-edit-custom .form-group label {
    font-weight: 600;
    font-size: 13px;
    color: #333;
    margin-bottom: 8px;
  }
  
  .modal-edit-custom textarea.form-control {
    min-height: 90px;
    font-size: 13px;
  }
  
  .modal-edit-custom .form-control {
    font-size: 13px;
    border-radius: 6px;
    border: 1px solid #d1d5db;
  }
  
  .modal-edit-custom .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
  }
  
  .modal-edit-custom .modal-footer {
    padding: 20px 25px;
    background-color: #f8f9fa;
  }
  
  .modal-edit-custom .row {
    margin-bottom: 10px;
  }
  
  .modal-edit-custom small.text-muted {
    display: block;
    margin-top: 5px;
    font-size: 11px;
  }
  
  .modal-edit-custom textarea[name="isi_poin[]"],
  .modal-edit-custom input[name="tembusan[]"] {
    margin-bottom: 10px;
  }
  
  .modal-edit-custom .bg-light {
    background-color: #f1f3f5 !important;
    cursor: not-allowed;
  }
  
  .modal-edit-custom .section-divider {
    border-top: 2px solid #e9ecef;
    margin: 20px 0;
  }
  
  .modal-edit-custom .section-title {
    font-size: 15px;
    font-weight: 600;
    color: #667eea;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
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
              <h4 class="mb-0">Surat Edaran</h4>
            </div>
            <div class="card-body">
              <ul class="nav nav-tabs" id="edaranTab" role="tablist">
                <li class="nav-item">
                  <a class="nav-link <?= ($activeTab=='input')?'active':'' ?>" id="input-tab" data-toggle="tab" href="#input" role="tab">Buat Surat Edaran</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link <?= ($activeTab=='data')?'active':'' ?>" id="data-tab" data-toggle="tab" href="#data" role="tab">Data Surat Edaran</a>
                </li>
              </ul>

              <div class="tab-content mt-3">
                
                <!-- ===== TAB INPUT ===== -->
                <div class="tab-pane fade <?= ($activeTab=='input')?'show active':'' ?>" id="input" role="tabpanel">
                  <form method="POST" id="formEdaran">
                    
                    <!-- Header Surat -->
                    <div class="row">
                      <div class="col-12"><h5>1. Header Surat</h5></div>
                      <div class="col-md-6">
                        <!-- ===== PERUBAHAN: Dropdown Unit Kerja ===== -->
                        <div class="form-group">
                          <label>Unit Kerja <span class="text-danger">*</span></label>
                          <select name="unit_kerja_id" id="unit_kerja" class="form-control" required>
                            <option value="">-- Pilih Unit Kerja --</option>
                            <?php
                            $qUnit = mysqli_query($conn, "
                                SELECT id, nama_unit, kode_unit 
                                FROM unit_kerja 
                                WHERE kode_unit IS NOT NULL AND kode_unit != ''
                                ORDER BY nama_unit
                            ");
                            while($unit = mysqli_fetch_assoc($qUnit)):
                            ?>
                            <option value="<?= $unit['id'] ?>">
                                <?= htmlspecialchars($unit['nama_unit']) ?> (Kode: <?= $unit['kode_unit'] ?>)
                            </option>
                            <?php endwhile; ?>
                          </select>
                          <small class="text-muted">Unit kerja akan menentukan kode dalam nomor surat</small>
                        </div>

                        <div class="form-group">
                          <label>Nomor Surat <span class="badge badge-info">Preview</span></label>
                          <input type="text" id="preview_nomor" class="form-control bg-light" readonly 
                                 placeholder="Pilih unit kerja untuk melihat preview nomor">
                          <small class="text-muted">
                            <i class="fas fa-info-circle"></i> Format: 001/SE-KODE/RSPH/I/2026 (Otomatis saat simpan)
                          </small>
                        </div>
                        <!-- ===== AKHIR PERUBAHAN ===== -->
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Tanggal Surat <span class="text-danger">*</span></label>
                          <input type="date" name="tanggal_surat" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                      </div>
                      <div class="col-12">
                        <div class="form-group">
                          <label>Tentang/Perihal <span class="text-danger">*</span></label>
                          <input type="text" name="perihal" class="form-control" placeholder="Contoh: LARANGAN MEMBAWA ANAK PADA SAAT KERJA" required>
                        </div>
                      </div>
                    </div>

                    <hr>

                    <!-- Isi Surat -->
                    <div class="row">
                      <div class="col-12"><h5>2. Isi Surat Edaran</h5></div>
                      <div class="col-12">
                        <div class="form-group">
                          <label>Paragraf Pembukaan <span class="text-danger">*</span></label>
                          <textarea name="pembukaan" class="form-control" rows="3" placeholder="Contoh: Dalam rangka penegakan disiplin kerja..." required></textarea>
                        </div>
                      </div>

                      <div class="col-12">
                        <div class="form-group">
                          <label>Poin-Poin Isi Edaran <span class="text-danger">*</span></label>
                          <div id="isi_poin-container">
                            <div class="dynamic-list-item">
                              <textarea name="isi_poin[]" class="form-control" rows="2" placeholder="Poin 1" required></textarea>
                            </div>
                          </div>
                          <button type="button" class="btn btn-sm btn-outline-primary add-more-btn" onclick="addField('isi_poin')">
                            <i class="fas fa-plus"></i> Tambah Poin
                          </button>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Tanggal Berlaku Efektif</label>
                          <input type="date" name="tanggal_berlaku" class="form-control">
                          <small class="text-muted">Contoh: "Ketentuan ini berlaku efektif per tanggal 30 Juli 2019"</small>
                        </div>
                      </div>

                      <div class="col-12">
                        <div class="form-group">
                          <label>Paragraf Penutup <span class="text-danger">*</span></label>
                          <textarea name="penutup" class="form-control" rows="2" placeholder="Contoh: Demikian surat edaran ini di buat untuk dilaksanakan dengan sebaik-baiknya..." required></textarea>
                        </div>
                      </div>
                    </div>

                    <hr>

                    <!-- Tembusan -->
                    <div class="row">
                      <div class="col-12"><h5>3. Tembusan (Opsional)</h5></div>
                      <div class="col-12">
                        <div class="form-group">
                          <label>Daftar Tembusan</label>
                          <div id="tembusan-container">
                            <div class="dynamic-list-item">
                              <input type="text" name="tembusan[]" class="form-control" placeholder="Contoh: Yth. Direktur PT. Permata Griya Husada">
                            </div>
                          </div>
                          <button type="button" class="btn btn-sm btn-outline-primary add-more-btn" onclick="addField('tembusan')">
                            <i class="fas fa-plus"></i> Tambah Tembusan
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
                            <option value="">-- Pilih Penandatangan --</option>
                            <?php 
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
                              </option>
                            <?php 
                                endwhile;
                            else:
                            ?>
                              <option value="" disabled>Tidak ada user yang tersedia</option>
                            <?php endif; ?>
                          </select>
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
                        <i class="fas fa-save"></i> Simpan Surat Edaran
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
                      <input type="text" name="perihal" class="form-control" placeholder="Cari Perihal" value="<?= htmlspecialchars($filter_perihal) ?>">
                    </div>
                    <div class="form-group mr-2">
                      <select name="status" class="form-control">
                        <option value="">-- Semua Status --</option>
                        <option value="draft" <?= ($filter_status=='draft')?'selected':'' ?>>Draft</option>
                        <option value="final" <?= ($filter_status=='final')?'selected':'' ?>>Final</option>
                      </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm mr-2">
                      <i class="fas fa-search"></i> Cari
                    </button>
                    <a href="surat_edaran.php?tab=data" class="btn btn-secondary btn-sm">
                      <i class="fas fa-sync"></i> Reset
                    </a>
                  </form>

                  <div class="table-responsive">
                    <table class="table table-bordered edaran-table">
                      <thead class="thead-dark">
                        <tr>
                          <th>No</th>
                          <th>Nomor Surat</th>
                          <th>Tanggal</th>
                          <th>Perihal</th>
                          <th>Penandatangan</th>
                          <th>Status</th>
                          <th>File PDF</th>
                          <th>Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php 
                        if(mysqli_num_rows($data_edaran)==0):
                        ?>
                          <tr><td colspan="8" class="text-center">Belum ada data surat edaran.</td></tr>
                        <?php 
                        else:
                          $no = $offset + 1;
                          while($se=mysqli_fetch_assoc($data_edaran)):
                            $badge_status = '';
                            if($se['status']=='draft') $badge_status = 'badge-warning';
                            elseif($se['status']=='final') $badge_status = 'badge-success';
                        ?>
                        <tr>
                          <td><?= $no++ ?></td>
                          <td><?= htmlspecialchars($se['nomor_surat']) ?></td>
                          <td><?= date('d-m-Y', strtotime($se['tanggal_surat'])) ?></td>
                          <td><?= htmlspecialchars($se['perihal']) ?></td>
                          <td>
                            <?= htmlspecialchars($se['penandatangan_nama']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($se['penandatangan_jabatan']) ?></small>
                          </td>
                          <td>
                            <span class="badge <?= $badge_status ?>"><?= strtoupper($se['status']) ?></span>
                          </td>
                          <td>
                            <?php if(!empty($se['file_path']) && file_exists($se['file_path'])): ?>
                              <a href="<?= htmlspecialchars($se['file_path']) ?>" target="_blank" class="btn btn-sm btn-info">
                                <i class="fas fa-file-pdf"></i> Lihat
                              </a>
                            <?php else: ?>
                              <span class="text-muted">-</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editModal<?= $se['id'] ?>">
                              <i class="fas fa-edit"></i>
                            </button>
                            <a href="hapus_edaran.php?id=<?= $se['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus surat edaran ini?')">
                              <i class="fas fa-trash"></i>
                            </a>
                          </td>
                        </tr>

                        <?php
                        // Prepare data untuk modal edit
                        $modals[] = $se;
                        
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
                          <a class="page-link" href="?tab=data&page=<?= $i ?>&perihal=<?= urlencode($filter_perihal) ?>&status=<?= urlencode($filter_status) ?>"><?= $i ?></a>
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

<!-- ===== MODALS EDIT (SUDAH DIPERBESAR) ===== -->
<?php foreach($modals as $se): 
$isi_poin_edit = json_decode($se['isi_poin'], true) ?? [];
$tembusan_edit = json_decode($se['tembusan'], true) ?? [];
?>
<div class="modal fade" id="editModal<?= $se['id'] ?>" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-edit-custom modal-dialog-centered" role="document">
    <div class="modal-content">
      <form method="POST" action="surat_edaran.php?tab=data">
        <input type="hidden" name="id" value="<?= $se['id'] ?>">
        
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fas fa-edit"></i> Edit Surat Edaran: <?= htmlspecialchars($se['perihal']) ?>
          </h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        
        <div class="modal-body">
          
          <!-- Alert Informasi -->
          <div class="row">
            <div class="col-12 mb-3">
              <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle"></i> 
                <strong>Informasi:</strong> Nomor surat tidak dapat diubah setelah dibuat. Perubahan status ke "Final" akan otomatis generate ulang PDF.
              </div>
            </div>
          </div>
          
          <!-- 1. Header Surat -->
          <div class="row">
            <div class="col-12">
              <div class="section-title">
                <i class="fas fa-file-alt"></i> 1. Header Surat
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Nomor Surat <span class="badge badge-secondary badge-sm">Tidak dapat diubah</span></label>
                <input type="text" name="nomor_surat" class="form-control bg-light" value="<?= htmlspecialchars($se['nomor_surat']) ?>" readonly>
                <small class="text-muted">Nomor ini sudah digenerate otomatis dan tidak dapat diubah</small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Tanggal Surat <span class="text-danger">*</span></label>
                <input type="date" name="tanggal_surat" class="form-control" value="<?= $se['tanggal_surat'] ?>" required>
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label>Tentang/Perihal <span class="text-danger">*</span></label>
                <input type="text" name="perihal" class="form-control" value="<?= htmlspecialchars($se['perihal']) ?>" required>
              </div>
            </div>
          </div>
          
          <div class="section-divider"></div>
          
          <!-- 2. Isi Surat -->
          <div class="row">
            <div class="col-12">
              <div class="section-title">
                <i class="fas fa-align-left"></i> 2. Isi Surat Edaran
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label>Paragraf Pembukaan <span class="text-danger">*</span></label>
                <textarea name="pembukaan" class="form-control" rows="3" required><?= htmlspecialchars($se['pembukaan']) ?></textarea>
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label>Poin-Poin Isi Edaran <span class="text-danger">*</span></label>
                <?php foreach($isi_poin_edit as $idx => $poin): ?>
                <textarea name="isi_poin[]" class="form-control" rows="2" placeholder="Poin <?= ($idx+1) ?>" required><?= htmlspecialchars($poin) ?></textarea>
                <?php endforeach; ?>
                <small class="text-muted">
                  <i class="fas fa-lightbulb"></i> Untuk menambah/mengurangi poin, gunakan form input utama
                </small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Tanggal Berlaku Efektif</label>
                <input type="date" name="tanggal_berlaku" class="form-control" value="<?= $se['tanggal_berlaku'] ?>">
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label>Paragraf Penutup <span class="text-danger">*</span></label>
                <textarea name="penutup" class="form-control" rows="2" required><?= htmlspecialchars($se['penutup']) ?></textarea>
              </div>
            </div>
          </div>
          
          <div class="section-divider"></div>
          
          <!-- 3. Tembusan -->
          <div class="row">
            <div class="col-12">
              <div class="section-title">
                <i class="fas fa-copy"></i> 3. Tembusan
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label>Daftar Tembusan</label>
                <?php if(count($tembusan_edit) > 0): ?>
                  <?php foreach($tembusan_edit as $tmb): ?>
                  <input type="text" name="tembusan[]" class="form-control" placeholder="Contoh: Yth. Direktur..." value="<?= htmlspecialchars($tmb) ?>">
                  <?php endforeach; ?>
                <?php else: ?>
                  <input type="text" name="tembusan[]" class="form-control" placeholder="Contoh: Yth. Direktur..." value="">
                <?php endif; ?>
              </div>
            </div>
          </div>
          
          <div class="section-divider"></div>
          
          <!-- 4. Penandatangan & Status -->
          <div class="row">
            <div class="col-12">
              <div class="section-title">
                <i class="fas fa-user-tie"></i> 4. Penandatangan & Status
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Penandatangan <span class="text-danger">*</span></label>
                <select name="penandatangan_id" class="form-control" required>
                  <?php 
                  $list_pejabat2 = mysqli_query($conn, "
                      SELECT u.id, u.nama, u.jabatan, u.nik,
                             CASE WHEN t.status = 'aktif' THEN 'Ya' ELSE 'Tidak' END as tte_status
                      FROM users u
                      LEFT JOIN tte_user t ON u.id = t.user_id AND t.status = 'aktif'
                      WHERE u.status IN ('active', 'pending')
                      ORDER BY u.nama ASC
                  ");
                  while($p2=mysqli_fetch_assoc($list_pejabat2)):
                      $sel = ($se['penandatangan_id']==$p2['id'])?'selected':'';
                  ?>
                  <option value="<?= $p2['id'] ?>" <?= $sel ?>>
                    <?= htmlspecialchars($p2['nama']) ?> - <?= htmlspecialchars($p2['jabatan']) ?>
                    <?php if($p2['tte_status'] == 'Ya'): ?> ✓ TTE<?php endif; ?>
                  </option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Status Dokumen <span class="text-danger">*</span></label>
                <select name="status" class="form-control" required>
                  <option value="draft" <?= ($se['status']=='draft'?'selected':'') ?>>Draft (Belum final)</option>
                  <option value="final" <?= ($se['status']=='final'?'selected':'') ?>>Final (Generate PDF)</option>
                </select>
                <small class="text-muted">
                  <i class="fas fa-sync"></i> Ubah ke "Final" untuk generate ulang PDF dengan data terbaru
                </small>
              </div>
            </div>
          </div>
          
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times"></i> Batal
          </button>
          <button type="submit" name="update" class="btn btn-primary">
            <i class="fas fa-save"></i> Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>

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
  // Auto hide flash message
  setTimeout(function(){ $("#flashMsg").fadeOut("slow"); }, 3000);
  
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
          jenis_dokumen: 'SE',
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
  
  if(type == 'isi_poin') {
    fieldHTML = `
      <div class="dynamic-list-item">
        <textarea name="${type}[]" class="form-control" rows="2" placeholder="Poin berikutnya"></textarea>
        <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeField(this)">
          <i class="fas fa-times"></i>
        </button>
      </div>`;
  } else {
    fieldHTML = `
      <div class="dynamic-list-item">
        <input type="text" name="${type}[]" class="form-control" placeholder="Tambah ${type}">
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