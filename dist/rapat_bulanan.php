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
    
    // Generate nomor Undangan otomatis
    $generator = new NomorDokumenGenerator($conn);
    try {
        $hasil = $generator->generateNomor(
            jenis_dokumen: 'UND',
            unit_kerja_id: $unit_kerja_id,
            tabel_referensi: 'undangan_rapat',
            referensi_id: 0, // Sementara 0, akan diupdate setelah insert
            user_id: $user_id
        );
        $nomor_surat = mysqli_real_escape_string($conn, $hasil['nomor_lengkap']);
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error generate nomor: ' . $e->getMessage();
        header("Location: rapat_bulanan.php");
        exit;
    }
    // ===== AKHIR PERUBAHAN =====
    
    $tanggal_surat = mysqli_real_escape_string($conn, $_POST['tanggal_surat']);
    $perihal = mysqli_real_escape_string($conn, $_POST['perihal']);
    
    // Proses array penerima
    $penerima_array = array_filter($_POST['penerima'], function($val) { return trim($val) != ''; });
    $penerima = json_encode(array_values($penerima_array));
    
    $tanggal_rapat = mysqli_real_escape_string($conn, $_POST['tanggal_rapat']);
    $jam_mulai = mysqli_real_escape_string($conn, $_POST['jam_mulai']);
    $jam_selesai = mysqli_real_escape_string($conn, $_POST['jam_selesai']);
    $hari_tanggal = mysqli_real_escape_string($conn, $_POST['hari_tanggal']);
    $waktu = mysqli_real_escape_string($conn, $_POST['waktu']);
    $tempat = mysqli_real_escape_string($conn, $_POST['tempat']);
    $agenda = mysqli_real_escape_string($conn, $_POST['agenda']);
    
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
    $sql = "INSERT INTO undangan_rapat (
        nomor_surat, tanggal_surat, perihal, penerima,
        tanggal_rapat, jam_mulai, jam_selesai, hari_tanggal, waktu, tempat, agenda,
        penandatangan_id, penandatangan_nama, penandatangan_jabatan, penandatangan_nik,
        tte_token, tte_hash,
        dibuat_oleh, status, created_at
    ) VALUES (
        '$nomor_surat', '$tanggal_surat', '$perihal', '$penerima',
        '$tanggal_rapat', '$jam_mulai', '$jam_selesai', '$hari_tanggal', '$waktu', '$tempat', '$agenda',
        $penandatangan_id, '$penandatangan_nama', '$penandatangan_jabatan', '$penandatangan_nik',
        ".($tte_token ? "'$tte_token'" : "NULL").", ".($tte_hash ? "'$tte_hash'" : "NULL").",
        $dibuat_oleh, '$status', NOW()
    )";
    
    if(mysqli_query($conn, $sql)){
        $undangan_id = mysqli_insert_id($conn);
        
        // ===== TAMBAHAN: Update referensi_id di log_penomoran =====
        mysqli_query($conn, "
            UPDATE log_penomoran 
            SET referensi_id = $undangan_id 
            WHERE tabel_referensi = 'undangan_rapat' 
              AND referensi_id = 0 
              AND nomor_lengkap = '$nomor_surat'
              AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        // ===== AKHIR TAMBAHAN =====
        
        // Jika status = 'final', langsung generate PDF
        if($status == 'final'){
            if(!file_exists('generate_undangan_pdf.php')) {
                $_SESSION['flash_message'] = 'Undangan tersimpan dengan nomor: '.$nomor_surat.'. File generate_undangan_pdf.php tidak ditemukan!';
                header("Location: rapat_bulanan.php?tab=data");
                exit;
            }
            
            try {
                include 'generate_undangan_pdf.php';
                $result = generateUndangan_PDF($undangan_id);
                
                if($result['success']){
                    $file_path = mysqli_real_escape_string($conn, $result['file_path']);
                    mysqli_query($conn, "UPDATE undangan_rapat SET file_path='$file_path' WHERE id=$undangan_id");
                    $_SESSION['flash_message'] = 'Undangan berhasil disimpan dengan nomor: '.$nomor_surat.' dan PDF berhasil di-generate!';
                } else {
                    $_SESSION['flash_message'] = 'Undangan tersimpan dengan nomor: '.$nomor_surat.'. Gagal generate PDF: '.$result['error'];
                }
            } catch (Exception $e) {
                $_SESSION['flash_message'] = 'Undangan tersimpan dengan nomor: '.$nomor_surat.'. Error: ' . $e->getMessage();
            }
        } else {
            $_SESSION['flash_message'] = 'Undangan berhasil disimpan sebagai draft dengan nomor: '.$nomor_surat;
        }
    } else {
        $_SESSION['flash_message'] = 'Error: '.mysqli_error($conn);
    }
    
    header("Location: rapat_bulanan.php?tab=data");
    exit;
}

// ==== HANDLE UPDATE ==== (tidak perlu generate nomor lagi, nomor sudah fix)
if(isset($_POST['update'])){
    $id = intval($_POST['id']);
    $nomor_surat = mysqli_real_escape_string($conn, $_POST['nomor_surat']); // Nomor tidak diubah
    $tanggal_surat = mysqli_real_escape_string($conn, $_POST['tanggal_surat']);
    $perihal = mysqli_real_escape_string($conn, $_POST['perihal']);
    
    $penerima_array = array_filter($_POST['penerima'], function($val) { return trim($val) != ''; });
    $penerima = json_encode(array_values($penerima_array));
    
    $tanggal_rapat = mysqli_real_escape_string($conn, $_POST['tanggal_rapat']);
    $jam_mulai = mysqli_real_escape_string($conn, $_POST['jam_mulai']);
    $jam_selesai = mysqli_real_escape_string($conn, $_POST['jam_selesai']);
    $hari_tanggal = mysqli_real_escape_string($conn, $_POST['hari_tanggal']);
    $waktu = mysqli_real_escape_string($conn, $_POST['waktu']);
    $tempat = mysqli_real_escape_string($conn, $_POST['tempat']);
    $agenda = mysqli_real_escape_string($conn, $_POST['agenda']);
    
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
    
    $sql = "UPDATE undangan_rapat SET 
        nomor_surat='$nomor_surat',
        tanggal_surat='$tanggal_surat',
        perihal='$perihal',
        penerima='$penerima',
        tanggal_rapat='$tanggal_rapat',
        jam_mulai='$jam_mulai',
        jam_selesai='$jam_selesai',
        hari_tanggal='$hari_tanggal',
        waktu='$waktu',
        tempat='$tempat',
        agenda='$agenda',
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
                include 'generate_undangan_pdf.php';
                $result = generateUndangan_PDF($id);
                
                if($result['success']){
                    $file_path = mysqli_real_escape_string($conn, $result['file_path']);
                    mysqli_query($conn, "UPDATE undangan_rapat SET file_path='$file_path' WHERE id=$id");
                    $_SESSION['flash_message'] = 'Undangan berhasil diupdate dan PDF di-generate!';
                } else {
                    $_SESSION['flash_message'] = 'Undangan terupdate. Gagal generate PDF: '.$result['error'];
                }
            } catch (Exception $e) {
                $_SESSION['flash_message'] = 'Undangan terupdate. Error: ' . $e->getMessage();
            }
        } else {
            $_SESSION['flash_message'] = 'Undangan berhasil diupdate.';
        }
    } else {
        $_SESSION['flash_message'] = 'Error: '.mysqli_error($conn);
    }
    
    header("Location: rapat_bulanan.php?tab=data");
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
$sqlCount = "SELECT COUNT(*) AS total FROM undangan_rapat WHERE 1=1 ";
if($filter_perihal != '') $sqlCount .= " AND perihal LIKE '%".mysqli_real_escape_string($conn, $filter_perihal)."%' ";
if($filter_status != '') $sqlCount .= " AND status = '".mysqli_real_escape_string($conn, $filter_status)."' ";
$totalData = mysqli_fetch_assoc(mysqli_query($conn, $sqlCount))['total'];
$totalPages = ceil($totalData / $limit);

// Ambil data undangan
$sqlData = "SELECT u.*, us.nama as nama_pembuat 
            FROM undangan_rapat u
            LEFT JOIN users us ON u.dibuat_oleh = us.id
            WHERE 1=1 ";
if($filter_perihal != '') $sqlData .= " AND u.perihal LIKE '%".mysqli_real_escape_string($conn, $filter_perihal)."%' ";
if($filter_status != '') $sqlData .= " AND u.status = '".mysqli_real_escape_string($conn, $filter_status)."' ";
$sqlData .= " ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset";
$data_undangan = mysqli_query($conn, $sqlData);

// Array untuk modal edit
$modals = [];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Undangan Rapat Bulanan</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<style>
  .undangan-table { font-size: 13px; white-space: nowrap; }
  .undangan-table th, .undangan-table td { padding: 6px 10px; vertical-align: middle; }
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
              <h4 class="mb-0">Undangan Rapat Bulanan</h4>
            </div>
            <div class="card-body">
              <ul class="nav nav-tabs" id="undanganTab" role="tablist">
                <li class="nav-item">
                  <a class="nav-link <?= ($activeTab=='input')?'active':'' ?>" id="input-tab" data-toggle="tab" href="#input" role="tab">Buat Undangan</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link <?= ($activeTab=='data')?'active':'' ?>" id="data-tab" data-toggle="tab" href="#data" role="tab">Data Undangan</a>
                </li>
              </ul>

              <div class="tab-content mt-3">
                
                <!-- ===== TAB INPUT ===== -->
                <div class="tab-pane fade <?= ($activeTab=='input')?'show active':'' ?>" id="input" role="tabpanel">
                  <form method="POST" id="formUndangan">
                    
                    <!-- Header Surat -->
                    <div class="row">
                      <div class="col-12"><h5>1. Header Surat</h5></div>
                      
                      <!-- ===== PERUBAHAN: Unit Kerja + Preview Nomor ===== -->
                      <div class="col-md-6">
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
                          <label>Preview Nomor Surat</label>
                          <input type="text" id="preview_nomor" class="form-control bg-light" readonly placeholder="Pilih unit kerja untuk melihat preview nomor">
                          <small class="text-muted">Nomor akan di-generate otomatis saat menyimpan</small>
                        </div>
                      </div>
                      <!-- ===== AKHIR PERUBAHAN ===== -->
                      
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Tanggal Surat <span class="text-danger">*</span></label>
                          <input type="date" name="tanggal_surat" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                      </div>
                      <div class="col-12">
                        <div class="form-group">
                          <label>Perihal <span class="text-danger">*</span></label>
                          <input type="text" name="perihal" class="form-control" placeholder="Contoh: Undangan Rapat Koordinasi Bulanan" required>
                        </div>
                      </div>
                    </div>

                    <hr>

                    <!-- Penerima -->
                    <div class="row">
                      <div class="col-12"><h5>2. Kepada Yth (Penerima)</h5></div>
                      <div class="col-12">
                        <div class="form-group">
                          <label>Daftar Penerima <span class="text-danger">*</span></label>
                          <div id="penerima-container">
                            <div class="dynamic-list-item">
                              <input type="text" name="penerima[]" class="form-control" placeholder="Contoh: Direktur PT. Permata Griya Husada" required>
                            </div>
                          </div>
                          <button type="button" class="btn btn-sm btn-outline-primary add-more-btn" onclick="addField('penerima')">
                            <i class="fas fa-plus"></i> Tambah Penerima
                          </button>
                        </div>
                      </div>
                    </div>

                    <hr>

                    <!-- Detail Rapat -->
                    <div class="row">
                      <div class="col-12"><h5>3. Detail Rapat</h5></div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Tanggal Rapat <span class="text-danger">*</span></label>
                          <input type="date" name="tanggal_rapat" id="tanggal_rapat" class="form-control" required>
                          <small class="text-muted">Hari akan otomatis terisi</small>
                        </div>
                        <div class="form-group">
                          <label>Hari, Tanggal (Preview) <span class="text-danger">*</span></label>
                          <input type="text" name="hari_tanggal" id="hari_tanggal" class="form-control" readonly required>
                          <small class="text-muted">Otomatis dari tanggal rapat</small>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Jam Mulai <span class="text-danger">*</span></label>
                          <input type="time" name="jam_mulai" id="jam_mulai" class="form-control" required>
                        </div>
                        <div class="form-group">
                          <label>Jam Selesai</label>
                          <input type="time" name="jam_selesai" id="jam_selesai" class="form-control">
                          <small class="text-muted">Kosongkan jika "s/d Selesai"</small>
                        </div>
                        <div class="form-group">
                          <label>Waktu (Preview) <span class="text-danger">*</span></label>
                          <input type="text" name="waktu" id="waktu" class="form-control" readonly required>
                          <small class="text-muted">Otomatis dari jam mulai-selesai</small>
                        </div>
                      </div>
                    </div>
                    
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Tempat <span class="text-danger">*</span></label>
                          <input type="text" name="tempat" class="form-control" placeholder="Contoh: Ruang Aula Lantai II" required>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Agenda <span class="text-danger">*</span></label>
                          <textarea name="agenda" class="form-control" rows="3" placeholder="Contoh: Rapat koordinasi laporan bulan November 2025" required></textarea>
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
                        <i class="fas fa-save"></i> Simpan Undangan
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
                    <a href="rapat_bulanan.php?tab=data" class="btn btn-secondary btn-sm">
                      <i class="fas fa-sync"></i> Reset
                    </a>
                  </form>

                  <div class="table-responsive">
                    <table class="table table-bordered undangan-table">
                      <thead class="thead-dark">
                        <tr>
                          <th>No</th>
                          <th>Nomor Surat</th>
                          <th>Tanggal</th>
                          <th>Perihal</th>
                          <th>Hari/Tanggal Rapat</th>
                          <th>Penandatangan</th>
                          <th>Status</th>
                          <th>File PDF</th>
                          <th>Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php 
                        if(mysqli_num_rows($data_undangan)==0):
                        ?>
                          <tr><td colspan="9" class="text-center">Belum ada data undangan.</td></tr>
                        <?php 
                        else:
                          $no = $offset + 1;
                          while($und=mysqli_fetch_assoc($data_undangan)):
                            $badge_status = '';
                            if($und['status']=='draft') $badge_status = 'badge-warning';
                            elseif($und['status']=='final') $badge_status = 'badge-success';
                        ?>
                        <tr>
                          <td><?= $no++ ?></td>
                          <td><?= htmlspecialchars($und['nomor_surat']) ?></td>
                          <td><?= date('d-m-Y', strtotime($und['tanggal_surat'])) ?></td>
                          <td><?= htmlspecialchars($und['perihal']) ?></td>
                          <td><?= htmlspecialchars($und['hari_tanggal']) ?></td>
                          <td>
                            <?= htmlspecialchars($und['penandatangan_nama']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($und['penandatangan_jabatan']) ?></small>
                          </td>
                          <td>
                            <span class="badge <?= $badge_status ?>"><?= strtoupper($und['status']) ?></span>
                          </td>
                          <td>
                            <?php if(!empty($und['file_path']) && file_exists($und['file_path'])): ?>
                              <a href="<?= htmlspecialchars($und['file_path']) ?>" target="_blank" class="btn btn-sm btn-info">
                                <i class="fas fa-file-pdf"></i> Lihat
                              </a>
                            <?php else: ?>
                              <span class="text-muted">-</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editModal<?= $und['id'] ?>">
                              <i class="fas fa-edit"></i>
                            </button>
                            <a href="hapus_undangan.php?id=<?= $und['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus undangan ini?')">
                              <i class="fas fa-trash"></i>
                            </a>
                          </td>
                        </tr>

                        <?php
                        // Prepare data untuk modal edit
                        $penerima_edit = json_decode($und['penerima'], true) ?? [];
                        
                        // Modal Edit
                        $modals[] = '
                        <div class="modal fade" id="editModal'.$und['id'].'" tabindex="-1" role="dialog">
                          <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                            <div class="modal-content">
                              <form method="POST" action="rapat_bulanan.php?tab=data">
                                <input type="hidden" name="id" value="'.$und['id'].'">
                                <div class="modal-header">
                                  <h5 class="modal-title">Edit Undangan: '.htmlspecialchars($und['perihal']).'</h5>
                                  <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                  <div class="row">
                                    <div class="col-md-6">
                                      <div class="form-group">
                                        <label>Nomor Surat</label>
                                        <input type="text" name="nomor_surat" class="form-control" value="'.htmlspecialchars($und['nomor_surat']).'" readonly>
                                        <small class="text-muted">Nomor tidak dapat diubah</small>
                                      </div>
                                    </div>
                                    <div class="col-md-6">
                                      <div class="form-group">
                                        <label>Tanggal Surat</label>
                                        <input type="date" name="tanggal_surat" class="form-control" value="'.$und['tanggal_surat'].'" required>
                                      </div>
                                    </div>
                                    <div class="col-12">
                                      <div class="form-group">
                                        <label>Perihal</label>
                                        <input type="text" name="perihal" class="form-control" value="'.htmlspecialchars($und['perihal']).'" required>
                                      </div>
                                    </div>
                                    <div class="col-12">
                                      <div class="form-group">
                                        <label>Penerima</label>';
                                        foreach($penerima_edit as $pnr){
                                            $modals[count($modals)-1] .= '<input type="text" name="penerima[]" class="form-control mb-2" value="'.htmlspecialchars($pnr).'">';
                                        }
                        $modals[count($modals)-1] .= '
                                      </div>
                                    </div>
                                    <div class="col-md-6">
                                      <div class="form-group">
                                        <label>Tanggal Rapat</label>
                                        <input type="date" name="tanggal_rapat" class="form-control tanggal-rapat-edit" value="'.$und['tanggal_rapat'].'" required>
                                      </div>
                                    </div>
                                    <div class="col-md-6">
                                      <div class="form-group">
                                        <label>Hari, Tanggal (Preview)</label>
                                        <input type="text" name="hari_tanggal" class="form-control hari-tanggal-edit" value="'.htmlspecialchars($und['hari_tanggal']).'" readonly required>
                                      </div>
                                    </div>
                                    <div class="col-md-4">
                                      <div class="form-group">
                                        <label>Jam Mulai</label>
                                        <input type="time" name="jam_mulai" class="form-control jam-mulai-edit" value="'.$und['jam_mulai'].'" required>
                                      </div>
                                    </div>
                                    <div class="col-md-4">
                                      <div class="form-group">
                                        <label>Jam Selesai</label>
                                        <input type="time" name="jam_selesai" class="form-control jam-selesai-edit" value="'.$und['jam_selesai'].'">
                                      </div>
                                    </div>
                                    <div class="col-md-4">
                                      <div class="form-group">
                                        <label>Waktu (Preview)</label>
                                        <input type="text" name="waktu" class="form-control waktu-edit" value="'.htmlspecialchars($und['waktu']).'" readonly required>
                                      </div>
                                    </div>
                                    <div class="col-md-6">
                                      <div class="form-group">
                                        <label>Tempat</label>
                                        <input type="text" name="tempat" class="form-control" value="'.htmlspecialchars($und['tempat']).'" required>
                                      </div>
                                    </div>
                                    <div class="col-md-6">
                                      <div class="form-group">
                                        <label>Agenda</label>
                                        <textarea name="agenda" class="form-control" rows="2" required>'.htmlspecialchars($und['agenda']).'</textarea>
                                      </div>
                                    </div>
                                    <div class="col-md-6">
                                      <div class="form-group">
                                        <label>Status</label>
                                        <select name="status" class="form-control" required>
                                          <option value="draft" '.($und['status']=='draft'?'selected':'').'>Draft</option>
                                          <option value="final" '.($und['status']=='final'?'selected':'').'>Final</option>
                                        </select>
                                      </div>
                                    </div>
                                    <div class="col-md-6">
                                      <div class="form-group">
                                        <label>Penandatangan</label>
                                        <select name="penandatangan_id" class="form-control" required>';
                                        $list_pejabat2 = mysqli_query($conn, "
                                            SELECT u.id, u.nama, u.jabatan
                                            FROM users u
                                            WHERE u.status IN ('active', 'pending')
                                            ORDER BY u.nama ASC
                                        ");
                                        while($p2=mysqli_fetch_assoc($list_pejabat2)){
                                            $sel = ($und['penandatangan_id']==$p2['id'])?'selected':'';
                                            $modals[count($modals)-1] .= '<option value="'.$p2['id'].'" '.$sel.'>'.htmlspecialchars($p2['nama']).'</option>';
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
          jenis_dokumen: 'UND',
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
  
  // Auto-generate Hari, Tanggal dalam Bahasa Indonesia
  $('#tanggal_rapat').on('change', function(){
    var tanggal = $(this).val();
    if(tanggal) {
      var date = new Date(tanggal);
      
      // Array hari dan bulan dalam Bahasa Indonesia
      var hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
      var bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                   'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
      
      var hariNama = hari[date.getDay()];
      var tanggalAngka = date.getDate();
      var bulanNama = bulan[date.getMonth()];
      var tahun = date.getFullYear();
      
      // Format: Kamis, 11 Desember 2025
      var hariTanggal = hariNama + ', ' + tanggalAngka + ' ' + bulanNama + ' ' + tahun;
      $('#hari_tanggal').val(hariTanggal);
    } else {
      $('#hari_tanggal').val('');
    }
  });
  
  // Auto-generate Waktu dalam format WIB
  function updateWaktu() {
    var jamMulai = $('#jam_mulai').val();
    var jamSelesai = $('#jam_selesai').val();
    
    if(jamMulai) {
      var waktuText = jamMulai.replace(':', '.') + ' WIB';
      
      if(jamSelesai) {
        waktuText += ' s/d ' + jamSelesai.replace(':', '.') + ' WIB';
      } else {
        waktuText += ' s/d Selesai';
      }
      
      $('#waktu').val(waktuText);
    } else {
      $('#waktu').val('');
    }
  }
  
  $('#jam_mulai, #jam_selesai').on('change', updateWaktu);
  
  // Handle date/time picker di modal edit
  $(document).on('change', '.tanggal-rapat-edit', function(){
    var modal = $(this).closest('.modal');
    var tanggal = $(this).val();
    if(tanggal) {
      var date = new Date(tanggal);
      var hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
      var bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                   'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
      
      var hariNama = hari[date.getDay()];
      var tanggalAngka = date.getDate();
      var bulanNama = bulan[date.getMonth()];
      var tahun = date.getFullYear();
      
      var hariTanggal = hariNama + ', ' + tanggalAngka + ' ' + bulanNama + ' ' + tahun;
      modal.find('.hari-tanggal-edit').val(hariTanggal);
    }
  });
  
  $(document).on('change', '.jam-mulai-edit, .jam-selesai-edit', function(){
    var modal = $(this).closest('.modal');
    var jamMulai = modal.find('.jam-mulai-edit').val();
    var jamSelesai = modal.find('.jam-selesai-edit').val();
    
    if(jamMulai) {
      var waktuText = jamMulai.replace(':', '.') + ' WIB';
      if(jamSelesai) {
        waktuText += ' s/d ' + jamSelesai.replace(':', '.') + ' WIB';
      } else {
        waktuText += ' s/d Selesai';
      }
      modal.find('.waktu-edit').val(waktuText);
    }
  });
});

// Function untuk menambah field dinamis
function addField(type) {
  var container = $('#' + type + '-container');
  var fieldHTML = `
    <div class="dynamic-list-item">
      <input type="text" name="${type}[]" class="form-control" placeholder="Tambah ${type}">
      <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeField(this)">
        <i class="fas fa-times"></i>
      </button>
    </div>`;
  
  container.append(fieldHTML);
}

// Function untuk menghapus field dinamis
function removeField(btn) {
  $(btn).closest('.dynamic-list-item').remove();
}
</script>
</body>
</html>