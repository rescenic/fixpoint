<?php
include 'security.php'; 
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');
require_once 'NomorDokumenGenerator.php'; // AUTO-NUMBERING

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

// ==== HANDLE FORM SIMPAN ==== (MODIFIED: Auto-generate nomor)
if(isset($_POST['simpan'])){
    // ===== AUTO-GENERATE NOMOR =====
    $unit_kerja_id = intval($_POST['unit_kerja_id']);
    
    $generator = new NomorDokumenGenerator($conn);
    try {
        $hasil = $generator->generateNomor(
            jenis_dokumen: 'SP',
            unit_kerja_id: $unit_kerja_id,
            tabel_referensi: 'surat_pemberitahuan',
            referensi_id: 0,
            user_id: $user_id
        );
        $nomor_surat = mysqli_real_escape_string($conn, $hasil['nomor_lengkap']);
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error generate nomor: ' . $e->getMessage();
        header("Location: pemberitahuan.php");
        exit;
    }
    // ===== END AUTO-GENERATE =====
    
    $tanggal_surat = mysqli_real_escape_string($conn, $_POST['tanggal_surat']);
    $perihal = mysqli_real_escape_string($conn, $_POST['perihal']);
    $isi_pemberitahuan = mysqli_real_escape_string($conn, $_POST['isi_pemberitahuan']);
    
    $waktu_mulai = mysqli_real_escape_string($conn, $_POST['waktu_mulai']);
    $waktu_selesai = mysqli_real_escape_string($conn, $_POST['waktu_selesai']);
    $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
    
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
    $sql = "INSERT INTO surat_pemberitahuan (
        nomor_surat, tanggal_surat, perihal, isi_pemberitahuan,
        waktu_mulai, waktu_selesai, kategori,
        penandatangan_id, penandatangan_nama, penandatangan_jabatan, penandatangan_nik,
        tte_token, tte_hash,
        dibuat_oleh, status, created_at
    ) VALUES (
        '$nomor_surat', '$tanggal_surat', '$perihal', '$isi_pemberitahuan',
        ".($waktu_mulai ? "'$waktu_mulai'" : "NULL").", ".($waktu_selesai ? "'$waktu_selesai'" : "NULL").", '$kategori',
        $penandatangan_id, '$penandatangan_nama', '$penandatangan_jabatan', '$penandatangan_nik',
        ".($tte_token ? "'$tte_token'" : "NULL").", ".($tte_hash ? "'$tte_hash'" : "NULL").",
        $dibuat_oleh, '$status', NOW()
    )";
    
    if(mysqli_query($conn, $sql)){
        $pemberitahuan_id = mysqli_insert_id($conn);
        
        // ===== UPDATE REFERENSI_ID DI LOG_PENOMORAN =====
        mysqli_query($conn, "
            UPDATE log_penomoran 
            SET referensi_id = $pemberitahuan_id 
            WHERE tabel_referensi = 'surat_pemberitahuan' 
              AND referensi_id = 0 
              AND nomor_lengkap = '$nomor_surat'
              AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        // ===== END UPDATE =====
        
        // Jika status = 'final', langsung generate PDF
        if($status == 'final'){
            if(!file_exists('generate_pemberitahuan_pdf.php')) {
                $_SESSION['flash_message'] = 'Surat Pemberitahuan tersimpan dengan nomor: '.$nomor_surat.'. File generate_pemberitahuan_pdf.php tidak ditemukan!';
                header("Location: pemberitahuan.php?tab=data");
                exit;
            }
            
            try {
                include 'generate_pemberitahuan_pdf.php';
                $result = generatePemberitahuan_PDF($pemberitahuan_id);
                
                if($result['success']){
                    $file_path = mysqli_real_escape_string($conn, $result['file_path']);
                    mysqli_query($conn, "UPDATE surat_pemberitahuan SET file_path='$file_path' WHERE id=$pemberitahuan_id");
                    $_SESSION['flash_message'] = 'Surat Pemberitahuan berhasil disimpan dengan nomor: '.$nomor_surat.' dan PDF berhasil di-generate!';
                } else {
                    $_SESSION['flash_message'] = 'Surat Pemberitahuan tersimpan dengan nomor: '.$nomor_surat.'. Gagal generate PDF: '.$result['error'];
                }
            } catch (Exception $e) {
                $_SESSION['flash_message'] = 'Surat Pemberitahuan tersimpan dengan nomor: '.$nomor_surat.'. Error: ' . $e->getMessage();
            }
        } else {
            $_SESSION['flash_message'] = 'Surat Pemberitahuan berhasil disimpan sebagai draft dengan nomor: '.$nomor_surat;
        }
    } else {
        $_SESSION['flash_message'] = 'Error: '.mysqli_error($conn);
    }
    
    header("Location: pemberitahuan.php?tab=data");
    exit;
}

// ==== HANDLE UPDATE ====
if(isset($_POST['update'])){
    $id = intval($_POST['id']);
    $nomor_surat = mysqli_real_escape_string($conn, $_POST['nomor_surat']);
    $tanggal_surat = mysqli_real_escape_string($conn, $_POST['tanggal_surat']);
    $perihal = mysqli_real_escape_string($conn, $_POST['perihal']);
    $isi_pemberitahuan = mysqli_real_escape_string($conn, $_POST['isi_pemberitahuan']);
    
    $waktu_mulai = mysqli_real_escape_string($conn, $_POST['waktu_mulai']);
    $waktu_selesai = mysqli_real_escape_string($conn, $_POST['waktu_selesai']);
    $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
    
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
    
    $sql = "UPDATE surat_pemberitahuan SET 
        nomor_surat='$nomor_surat',
        tanggal_surat='$tanggal_surat',
        perihal='$perihal',
        isi_pemberitahuan='$isi_pemberitahuan',
        waktu_mulai=".($waktu_mulai ? "'$waktu_mulai'" : "NULL").",
        waktu_selesai=".($waktu_selesai ? "'$waktu_selesai'" : "NULL").",
        kategori='$kategori',
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
                include 'generate_pemberitahuan_pdf.php';
                $result = generatePemberitahuan_PDF($id);
                
                if($result['success']){
                    $file_path = mysqli_real_escape_string($conn, $result['file_path']);
                    mysqli_query($conn, "UPDATE surat_pemberitahuan SET file_path='$file_path' WHERE id=$id");
                    $_SESSION['flash_message'] = 'Surat Pemberitahuan berhasil diupdate dan PDF di-generate!';
                } else {
                    $_SESSION['flash_message'] = 'Surat Pemberitahuan terupdate. Gagal generate PDF: '.$result['error'];
                }
            } catch (Exception $e) {
                $_SESSION['flash_message'] = 'Surat Pemberitahuan terupdate. Error: ' . $e->getMessage();
            }
        } else {
            $_SESSION['flash_message'] = 'Surat Pemberitahuan berhasil diupdate.';
        }
    } else {
        $_SESSION['flash_message'] = 'Error: '.mysqli_error($conn);
    }
    
    header("Location: pemberitahuan.php?tab=data");
    exit;
}

// ==== HANDLE GENERATE PDF MANUAL ====
if(isset($_GET['generate_pdf'])){
    $id = intval($_GET['generate_pdf']);
    
    if(!file_exists('generate_pemberitahuan_pdf.php')) {
        $_SESSION['flash_message'] = 'File generate_pemberitahuan_pdf.php tidak ditemukan!';
        header("Location: pemberitahuan.php?tab=data");
        exit;
    }
    
    try {
        include 'generate_pemberitahuan_pdf.php';
        $result = generatePemberitahuan_PDF($id);
        
        if($result['success']){
            $file_path = mysqli_real_escape_string($conn, $result['file_path']);
            mysqli_query($conn, "UPDATE surat_pemberitahuan SET file_path='$file_path' WHERE id=$id");
            $_SESSION['flash_message'] = 'PDF berhasil di-generate!';
        } else {
            $_SESSION['flash_message'] = 'Gagal generate PDF: '.$result['error'];
        }
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
    }
    
    header("Location: pemberitahuan.php?tab=data");
    exit;
}

// ==== PAGINATION & FILTER ====
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'buat';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$filter_perihal = isset($_GET['perihal']) ? mysqli_real_escape_string($conn, $_GET['perihal']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$filter_kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($conn, $_GET['kategori']) : '';

$where = "WHERE 1=1";
if($filter_perihal != '') $where .= " AND perihal LIKE '%$filter_perihal%'";
if($filter_status != '') $where .= " AND status='$filter_status'";
if($filter_kategori != '') $where .= " AND kategori='$filter_kategori'";

$qTotal = mysqli_query($conn, "SELECT COUNT(*) as total FROM surat_pemberitahuan $where");
$total_data = mysqli_fetch_assoc($qTotal)['total'];
$total_pages = ceil($total_data / $limit);

$qData = mysqli_query($conn, "SELECT * FROM surat_pemberitahuan $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");

// Data untuk modals
$modals = mysqli_query($conn, "SELECT * FROM surat_pemberitahuan ORDER BY created_at DESC");

// List penandatangan
$list_pejabat = mysqli_query($conn, "
    SELECT u.id, u.nama, u.jabatan, u.nik,
    CASE WHEN t.status='aktif' THEN 'Ya' ELSE 'Tidak' END as tte_status
    FROM users u
    LEFT JOIN tte_user t ON u.id = t.user_id AND t.status = 'aktif'
    WHERE u.status IN ('active', 'pending')
    ORDER BY u.nama ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
  <title>Surat Pemberitahuan - Sistem Informasi Rumah Sakit</title>

  <link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/components.css">
  
  <style>
    .dynamic-list-item {
      position: relative;
      margin-bottom: 10px;
    }
    .dynamic-list-item .remove-btn {
      position: absolute;
      right: 5px;
      top: 5px;
      padding: 2px 8px;
    }
    .badge-draft { background-color: #ffc107; }
    .badge-final { background-color: #28a745; }
    .kategori-badge {
      font-size: 11px;
      padding: 3px 8px;
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
          <h1><i class="fas fa-bullhorn"></i> Surat Pemberitahuan</h1>
          <div class="section-header-breadcrumb">
            <div class="breadcrumb-item active"><a href="dashboard.php">Dashboard</a></div>
            <div class="breadcrumb-item">Surat Pemberitahuan</div>
          </div>
        </div>

        <div class="section-body">
          
          <?php if(isset($_SESSION['flash_message'])): ?>
          <div class="alert alert-success alert-dismissible fade show" id="flashMsg">
            <?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?>
          </div>
          <?php endif; ?>

          <div class="card">
            <div class="card-header">
              <h4>Manajemen Surat Pemberitahuan</h4>
            </div>
            <div class="card-body">
              
              <!-- TABS -->
              <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                  <a class="nav-link <?= ($active_tab=='buat'?'active':'') ?>" href="?tab=buat">
                    <i class="fas fa-plus-circle"></i> Buat Surat Pemberitahuan
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link <?= ($active_tab=='data'?'active':'') ?>" href="?tab=data">
                    <i class="fas fa-list"></i> Data Surat Pemberitahuan
                  </a>
                </li>
              </ul>

              <div class="tab-content mt-3">
                
                <!-- TAB BUAT -->
                <div class="tab-pane fade <?= ($active_tab=='buat'?'show active':'') ?>">
                  <form method="POST" action="pemberitahuan.php?tab=data">
                    <div class="row">
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
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Tanggal Surat <span class="text-danger">*</span></label>
                          <input type="date" name="tanggal_surat" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                      </div>
                      
                      <div class="col-md-8">
                        <div class="form-group">
                          <label>Perihal Pemberitahuan <span class="text-danger">*</span></label>
                          <input type="text" name="perihal" class="form-control" placeholder="Contoh: Gangguan Jaringan Internet" required>
                        </div>
                      </div>
                      
                      <div class="col-md-4">
                        <div class="form-group">
                          <label>Kategori <span class="text-danger">*</span></label>
                          <select name="kategori" class="form-control" required>
                            <option value="">-- Pilih Kategori --</option>
                            <option value="Fasilitas">Fasilitas</option>
                            <option value="Teknologi">Teknologi</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Layanan">Layanan</option>
                            <option value="Umum">Umum</option>
                            <option value="Lainnya">Lainnya</option>
                          </select>
                        </div>
                      </div>
                      
                      <div class="col-12">
                        <div class="form-group">
                          <label>Isi Pemberitahuan <span class="text-danger">*</span></label>
                          <textarea name="isi_pemberitahuan" class="form-control" rows="6" placeholder="Tulis isi pemberitahuan di sini..." required></textarea>
                          <small class="text-muted">Jelaskan informasi yang ingin disampaikan kepada seluruh karyawan/civitas rumah sakit</small>
                        </div>
                      </div>
                      
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Waktu Mulai (Opsional)</label>
                          <input type="datetime-local" name="waktu_mulai" class="form-control">
                          <small class="text-muted">Untuk gangguan/maintenance yang terjadwal</small>
                        </div>
                      </div>
                      
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Waktu Selesai (Opsional)</label>
                          <input type="datetime-local" name="waktu_selesai" class="form-control">
                          <small class="text-muted">Estimasi waktu selesai gangguan/maintenance</small>
                        </div>
                      </div>
                      
                      <div class="col-md-4">
                        <div class="form-group">
                          <label>Penandatangan <span class="text-danger">*</span></label>
                          <select name="penandatangan_id" id="penandatangan" class="form-control" required>
                            <option value="">-- Pilih Penandatangan --</option>
                            <?php while($pjb=mysqli_fetch_assoc($list_pejabat)): ?>
                            <option value="<?= $pjb['id'] ?>" 
                                    data-nik="<?= htmlspecialchars($pjb['nik']) ?>"
                                    data-tte="<?= $pjb['tte_status'] ?>">
                              <?= htmlspecialchars($pjb['nama']) ?> - <?= htmlspecialchars($pjb['jabatan']) ?>
                            </option>
                            <?php endwhile; ?>
                          </select>
                        </div>
                      </div>
                      
                      <div class="col-md-4">
                        <div class="form-group">
                          <label>NIK Penandatangan</label>
                          <input type="text" id="nik_ttd" class="form-control" readonly placeholder="Auto-fill">
                        </div>
                      </div>
                      
                      <div class="col-md-4">
                        <div class="form-group">
                          <label>Status TTE</label>
                          <input type="text" id="tte_status" class="form-control" readonly placeholder="Auto-fill">
                        </div>
                      </div>
                      
                      <div class="col-md-12">
                        <div class="form-group">
                          <label>Status <span class="text-danger">*</span></label>
                          <select name="status" class="form-control" required>
                            <option value="draft">Draft (Belum Final)</option>
                            <option value="final">Final (Generate PDF)</option>
                          </select>
                          <small class="text-muted">Pilih "Final" untuk langsung generate PDF surat pemberitahuan</small>
                        </div>
                      </div>
                      
                      <div class="col-12">
                        <button type="submit" name="simpan" class="btn btn-primary">
                          <i class="fas fa-save"></i> Simpan Surat Pemberitahuan
                        </button>
                        <button type="reset" class="btn btn-secondary">
                          <i class="fas fa-undo"></i> Reset Form
                        </button>
                      </div>
                    </div>
                  </form>
                </div> <!-- End Tab Buat -->

                <!-- TAB DATA -->
                <div class="tab-pane fade <?= ($active_tab=='data'?'show active':'') ?>">
                  
                  <!-- Filter -->
                  <form method="GET" action="pemberitahuan.php" class="mb-3">
                    <input type="hidden" name="tab" value="data">
                    <div class="row">
                      <div class="col-md-4">
                        <input type="text" name="perihal" class="form-control" placeholder="Cari Perihal..." value="<?= htmlspecialchars($filter_perihal) ?>">
                      </div>
                      <div class="col-md-3">
                        <select name="kategori" class="form-control">
                          <option value="">-- Semua Kategori --</option>
                          <option value="Fasilitas" <?= ($filter_kategori=='Fasilitas'?'selected':'') ?>>Fasilitas</option>
                          <option value="Teknologi" <?= ($filter_kategori=='Teknologi'?'selected':'') ?>>Teknologi</option>
                          <option value="Maintenance" <?= ($filter_kategori=='Maintenance'?'selected':'') ?>>Maintenance</option>
                          <option value="Layanan" <?= ($filter_kategori=='Layanan'?'selected':'') ?>>Layanan</option>
                          <option value="Umum" <?= ($filter_kategori=='Umum'?'selected':'') ?>>Umum</option>
                          <option value="Lainnya" <?= ($filter_kategori=='Lainnya'?'selected':'') ?>>Lainnya</option>
                        </select>
                      </div>
                      <div class="col-md-3">
                        <select name="status" class="form-control">
                          <option value="">-- Semua Status --</option>
                          <option value="draft" <?= ($filter_status=='draft'?'selected':'') ?>>Draft</option>
                          <option value="final" <?= ($filter_status=='final'?'selected':'') ?>>Final</option>
                        </select>
                      </div>
                      <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i> Filter</button>
                      </div>
                    </div>
                  </form>

                  <div class="table-responsive">
                    <table class="table table-striped table-hover">
                      <thead>
                        <tr>
                          <th width="5%">No</th>
                          <th width="12%">Nomor Surat</th>
                          <th width="10%">Tanggal</th>
                          <th width="20%">Perihal</th>
                          <th width="10%">Kategori</th>
                          <th width="12%">Penandatangan</th>
                          <th width="8%">Status</th>
                          <th width="8%">PDF</th>
                          <th width="15%">Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php 
                        $no = $offset + 1;
                        if(mysqli_num_rows($qData) > 0):
                          while($sp = mysqli_fetch_assoc($qData)):
                        ?>
                        <tr>
                          <td><?= $no++ ?></td>
                          <td><?= htmlspecialchars($sp['nomor_surat']) ?></td>
                          <td><?= date('d/m/Y', strtotime($sp['tanggal_surat'])) ?></td>
                          <td><?= htmlspecialchars($sp['perihal']) ?></td>
                          <td><span class="badge badge-info kategori-badge"><?= htmlspecialchars($sp['kategori']) ?></span></td>
                          <td>
                            <small><?= htmlspecialchars($sp['penandatangan_nama']) ?></small><br>
                            <small class="text-muted"><?= htmlspecialchars($sp['penandatangan_jabatan']) ?></small>
                          </td>
                          <td>
                            <span class="badge badge-<?= ($sp['status']=='final'?'final':'draft') ?>">
                              <?= strtoupper($sp['status']) ?>
                            </span>
                          </td>
                          <td>
                            <?php if(!empty($sp['file_path']) && file_exists($sp['file_path'])): ?>
                              <a href="<?= $sp['file_path'] ?>" target="_blank" class="btn btn-sm btn-success">
                                <i class="fas fa-file-pdf"></i>
                              </a>
                            <?php else: ?>
                              <a href="?generate_pdf=<?= $sp['id'] ?>" class="btn btn-sm btn-warning" onclick="return confirm('Generate PDF untuk surat ini?')">
                                <i class="fas fa-sync"></i>
                              </a>
                            <?php endif; ?>
                          </td>
                          <td>
                            <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#editModal<?= $sp['id'] ?>">
                              <i class="fas fa-edit"></i>
                            </button>
                            <a href="hapus_pemberitahuan.php?id=<?= $sp['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus surat pemberitahuan ini?')">
                              <i class="fas fa-trash"></i>
                            </a>
                          </td>
                        </tr>
                        <?php 
                          endwhile;
                        else:
                        ?>
                        <tr>
                          <td colspan="9" class="text-center">Tidak ada data surat pemberitahuan</td>
                        </tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>

                  <!-- Pagination -->
                  <?php if($total_pages > 1): ?>
                  <nav>
                    <ul class="pagination">
                      <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <li class="page-item <?= ($i==$page?'active':'') ?>">
                          <a class="page-link" href="?tab=data&page=<?= $i ?>&perihal=<?= urlencode($filter_perihal) ?>&kategori=<?= urlencode($filter_kategori) ?>&status=<?= urlencode($filter_status) ?>"><?= $i ?></a>
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

<!-- Modals Edit -->
<?php 
mysqli_data_seek($modals, 0);
while($sp = mysqli_fetch_assoc($modals)): 
?>
<div class="modal fade" id="editModal<?= $sp['id'] ?>" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <form method="POST" action="pemberitahuan.php?tab=data">
        <input type="hidden" name="id" value="<?= $sp['id'] ?>">
        <div class="modal-header">
          <h5 class="modal-title">Edit Surat Pemberitahuan: <?= htmlspecialchars($sp['perihal']) ?></h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Nomor Surat</label>
                <input type="text" name="nomor_surat" class="form-control" value="<?= htmlspecialchars($sp['nomor_surat']) ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Tanggal Surat</label>
                <input type="date" name="tanggal_surat" class="form-control" value="<?= $sp['tanggal_surat'] ?>" required>
              </div>
            </div>
            <div class="col-md-8">
              <div class="form-group">
                <label>Perihal Pemberitahuan</label>
                <input type="text" name="perihal" class="form-control" value="<?= htmlspecialchars($sp['perihal']) ?>" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Kategori</label>
                <select name="kategori" class="form-control" required>
                  <option value="Fasilitas" <?= ($sp['kategori']=='Fasilitas'?'selected':'') ?>>Fasilitas</option>
                  <option value="Teknologi" <?= ($sp['kategori']=='Teknologi'?'selected':'') ?>>Teknologi</option>
                  <option value="Maintenance" <?= ($sp['kategori']=='Maintenance'?'selected':'') ?>>Maintenance</option>
                  <option value="Layanan" <?= ($sp['kategori']=='Layanan'?'selected':'') ?>>Layanan</option>
                  <option value="Umum" <?= ($sp['kategori']=='Umum'?'selected':'') ?>>Umum</option>
                  <option value="Lainnya" <?= ($sp['kategori']=='Lainnya'?'selected':'') ?>>Lainnya</option>
                </select>
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label>Isi Pemberitahuan</label>
                <textarea name="isi_pemberitahuan" class="form-control" rows="6" required><?= htmlspecialchars($sp['isi_pemberitahuan']) ?></textarea>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Waktu Mulai</label>
                <input type="datetime-local" name="waktu_mulai" class="form-control" value="<?= $sp['waktu_mulai'] ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Waktu Selesai</label>
                <input type="datetime-local" name="waktu_selesai" class="form-control" value="<?= $sp['waktu_selesai'] ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control" required>
                  <option value="draft" <?= ($sp['status']=='draft'?'selected':'') ?>>Draft</option>
                  <option value="final" <?= ($sp['status']=='final'?'selected':'') ?>>Final</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Penandatangan</label>
                <select name="penandatangan_id" class="form-control" required>
                  <?php 
                  $list_pejabat2 = mysqli_query($conn, "
                      SELECT u.id, u.nama, u.jabatan
                      FROM users u
                      WHERE u.status IN ('active', 'pending')
                      ORDER BY u.nama ASC
                  ");
                  while($p2=mysqli_fetch_assoc($list_pejabat2)):
                      $sel = ($sp['penandatangan_id']==$p2['id'])?'selected':'';
                  ?>
                  <option value="<?= $p2['id'] ?>" <?= $sel ?>><?= htmlspecialchars($p2['nama']) ?></option>
                  <?php endwhile; ?>
                </select>
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
</div>
<?php endwhile; ?>

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
  
  // ===== AJAX PREVIEW NOMOR SURAT PEMBERITAHUAN =====
  $('#unit_kerja').on('change', function(){
    var unit_id = $(this).val();
    
    if(unit_id) {
      $('#preview_nomor').val('Loading...');
      
      $.ajax({
        url: 'ajax_preview_nomor.php',
        type: 'POST',
        data: {
          jenis_dokumen: 'SP',
          unit_kerja_id: unit_id
        },
        dataType: 'json',
        success: function(response) {
          if(response.success) {
            $('#preview_nomor').val(response.nomor_preview);
          } else {
            $('#preview_nomor').val('Error: ' + response.error);
          }
        },
        error: function() {
          $('#preview_nomor').val('Error koneksi');
        }
      });
    } else {
      $('#preview_nomor').val('');
    }
  });
  // ===== END AJAX PREVIEW =====
});
</script>
</body>
</html>