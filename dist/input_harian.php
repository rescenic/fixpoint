<?php
// input_harian.php
include 'security.php';
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id   = $_SESSION['user_id'] ?? 0;

// ambil nama user login dari tabel users
$nama_user = '';
if ($user_id > 0) {
    $qUser = mysqli_query($conn, "SELECT nama FROM users WHERE id = '".intval($user_id)."' LIMIT 1");
    if ($qUser && $rowU = mysqli_fetch_assoc($qUser)) {
        $nama_user = $rowU['nama'];
    }
}

$activeTab = $_GET['tab'] ?? 'data';
$filterJenis = $_GET['filter_jenis'] ?? ''; // filter jenis indikator

// Filter untuk rekap harian
$bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');
$jenis_indikator_rekap = isset($_GET['jenis_rekap']) ? mysqli_real_escape_string($conn, $_GET['jenis_rekap']) : '';
$id_indikator_rekap = isset($_GET['indikator_rekap']) ? intval($_GET['indikator']) : 0;

// akses menu
$current_file = basename(__FILE__);
$rAkses = mysqli_query($conn, "SELECT 1 
            FROM akses_menu 
            JOIN menu ON akses_menu.menu_id = menu.id 
            WHERE akses_menu.user_id = '".intval($user_id)."' 
              AND menu.file_menu = '".mysqli_real_escape_string($conn,$current_file)."'");
if (!$rAkses || mysqli_num_rows($rAkses) == 0) {
    echo "<script>alert('Tidak ada akses ke halaman ini.'); window.location.href='dashboard.php';</script>";
    exit;
}

// Pastikan kolom tte_token ada di tabel indikator_harian
// Jalankan query ini di database jika belum ada:
// ALTER TABLE indikator_harian ADD COLUMN IF NOT EXISTS tte_token VARCHAR(255) AFTER catatan_validasi;

// proses simpan
if (isset($_POST['simpan'])) {
    $jenis      = mysqli_real_escape_string($conn, $_POST['jenis_indikator']);
    $id_indikator = intval($_POST['id_indikator']);
    $tanggal    = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $numerator  = intval($_POST['numerator']);
    $denominator= intval($_POST['denominator']);
    $ket        = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $petugas    = mysqli_real_escape_string($conn, $nama_user);

    if ($jenis && $id_indikator && $tanggal && $denominator > 0) {
        $q = "INSERT INTO indikator_harian 
              (jenis_indikator, id_indikator, tanggal, numerator, denominator, keterangan, petugas) 
              VALUES 
              ('$jenis','$id_indikator','$tanggal','$numerator','$denominator','$ket','$petugas')";

        if (mysqli_query($conn, $q)) {
            $_SESSION['flash_message'] = "Data berhasil disimpan.";
            $_SESSION['flash_type'] = "success";
            header("Location: input_harian.php");
            exit;
        } else {
            $_SESSION['flash_message'] = "Gagal: " . mysqli_error($conn);
            $_SESSION['flash_type'] = "danger";
            header("Location: input_harian.php?tab=input");
            exit;
        }
    } else {
        $_SESSION['flash_message'] = "Lengkapi semua field! Denominator harus lebih dari 0.";
        $_SESSION['flash_type'] = "warning";
        header("Location: input_harian.php?tab=input");
        exit;
    }
}

// proses update
if (isset($_POST['update'])) {
    $id_harian  = intval($_POST['id_harian']);
    $jenis      = mysqli_real_escape_string($conn, $_POST['jenis_indikator']);
    $id_indikator = intval($_POST['id_indikator']);
    $tanggal    = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $numerator  = intval($_POST['numerator']);
    $denominator= intval($_POST['denominator']);
    $ket        = mysqli_real_escape_string($conn, $_POST['keterangan']);

    if ($jenis && $id_indikator && $tanggal && $denominator > 0) {
        $q = "UPDATE indikator_harian SET 
              jenis_indikator='$jenis',
              id_indikator='$id_indikator',
              tanggal='$tanggal',
              numerator='$numerator',
              denominator='$denominator',
              keterangan='$ket'
              WHERE id_harian='$id_harian'";

        if (mysqli_query($conn, $q)) {
            $_SESSION['flash_message'] = "Data berhasil diperbarui.";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Gagal: " . mysqli_error($conn);
            $_SESSION['flash_type'] = "danger";
        }
    } else {
        $_SESSION['flash_message'] = "Lengkapi semua field! Denominator harus lebih dari 0.";
        $_SESSION['flash_type'] = "warning";
    }
    header("Location: input_harian.php?page=" . ($_POST['current_page'] ?? 1) . ($filterJenis ? "&filter_jenis=$filterJenis" : ""));
    exit;
}

// hapus data
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    mysqli_query($conn, "DELETE FROM indikator_harian WHERE id_harian='$id'");
    $_SESSION['flash_message'] = "Data berhasil dihapus.";
    $_SESSION['flash_type'] = "success";
    header("Location: input_harian.php?page=" . ($_GET['page'] ?? 1) . ($filterJenis ? "&filter_jenis=$filterJenis" : ""));
    exit;
}

// Proses Validasi oleh Penanggung Jawab
if (isset($_POST['validasi_rekap'])) {
    $jenis = mysqli_real_escape_string($conn, $_POST['jenis_indikator']);
    $id_indikator = intval($_POST['id_indikator']);
    $bulan_val = intval($_POST['bulan_val']);
    $tahun_val = intval($_POST['tahun_val']);
    
    // Ambil TTE user yang login
    $qTTE = mysqli_query($conn, "SELECT token FROM tte_user WHERE user_id = '$user_id' AND status = 'aktif' LIMIT 1");
    $tte_data = mysqli_fetch_assoc($qTTE);
    
    if ($tte_data) {
        $tte_token = $tte_data['token'];
        
        // Update semua data indikator ini di bulan tersebut dengan validasi TTE
        $q = "UPDATE indikator_harian SET 
              status_validasi = 'tervalidasi',
              validasi_oleh = '".mysqli_real_escape_string($conn, $nama_user)."',
              validasi_tanggal = NOW(),
              tte_token = '$tte_token'
              WHERE jenis_indikator = '$jenis'
              AND id_indikator = '$id_indikator'
              AND MONTH(tanggal) = $bulan_val
              AND YEAR(tanggal) = $tahun_val";
        
        if (mysqli_query($conn, $q)) {
            $_SESSION['flash_message'] = "Data berhasil divalidasi dengan TTE.";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Gagal validasi: " . mysqli_error($conn);
            $_SESSION['flash_type'] = "danger";
        }
    } else {
        $_SESSION['flash_message'] = "Anda belum memiliki TTE aktif. Silakan hubungi administrator.";
        $_SESSION['flash_type'] = "warning";
    }
    
    header("Location: input_harian.php?tab=rekap&bulan=$bulan_val&tahun=$tahun_val&jenis_rekap=$jenis&indikator_rekap=$id_indikator");
    exit;
}

// Pagination setup
$limit = 10; // Jumlah data per halaman
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page = $page < 1 ? 1 : $page;
$offset = ($page - 1) * $limit;

// Query untuk hitung total data (data user yang login ATAU user sebagai penanggung jawab indikator)
$countQuery = "SELECT COUNT(*) as total FROM indikator_harian h WHERE (
                h.petugas = '".mysqli_real_escape_string($conn, $nama_user)."' 
                OR (
                  (h.jenis_indikator = 'nasional' AND EXISTS (SELECT 1 FROM indikator_nasional n WHERE n.id_nasional = h.id_indikator AND n.penanggung_jawab = '".mysqli_real_escape_string($conn, $nama_user)."'))
                  OR (h.jenis_indikator = 'rs' AND EXISTS (SELECT 1 FROM indikator_rs r JOIN users u ON r.penanggung_jawab = u.id WHERE r.id_rs = h.id_indikator AND u.nama = '".mysqli_real_escape_string($conn, $nama_user)."'))
                  OR (h.jenis_indikator = 'unit' AND EXISTS (SELECT 1 FROM indikator_unit iu JOIN users u ON iu.penanggung_jawab = u.id WHERE iu.id_unit = h.id_indikator AND u.nama = '".mysqli_real_escape_string($conn, $nama_user)."'))
                )
              )";
if($filterJenis) {
    $countQuery .= " AND h.jenis_indikator='".mysqli_real_escape_string($conn,$filterJenis)."'";
}
$countResult = mysqli_query($conn, $countQuery);
$totalData = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalData / $limit);

// ambil daftar indikator untuk form (dengan numerator dan denominator)
$nasional = mysqli_query($conn, "SELECT id_nasional AS id, nama_indikator, standar, numerator, denominator, 'nasional' AS jenis FROM indikator_nasional ORDER BY nama_indikator");
$rs       = mysqli_query($conn, "SELECT id_rs AS id, nama_indikator, standar, numerator, denominator, 'rs' AS jenis FROM indikator_rs ORDER BY nama_indikator");
$unit     = mysqli_query($conn, "SELECT iu.id_unit AS id, iu.nama_indikator, iu.standar, iu.numerator, iu.denominator, u.nama_unit, 'unit' AS jenis
                                 FROM indikator_unit iu 
                                 LEFT JOIN unit_kerja u ON iu.unit_id=u.id 
                                 ORDER BY u.nama_unit, iu.nama_indikator");

$indikators = [];
while($row = mysqli_fetch_assoc($nasional)) $indikators['nasional'][] = $row;
while($row = mysqli_fetch_assoc($rs))       $indikators['rs'][] = $row;
while($row = mysqli_fetch_assoc($unit))     $indikators['unit'][] = $row;

$modals = [];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Input Harian Indikator</title>
  <link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <style>
    .dokumen-table { font-size: 13px; white-space: nowrap; }
    .dokumen-table th, .dokumen-table td { padding: 6px 10px; vertical-align: middle; }
    .info-box { 
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
      color: white;
      border: none;
      padding: 15px; 
      margin-top: 10px; 
      border-radius: 10px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .info-box b { color: #fff; }
    .flash-center {
      position: fixed; top: 20%; left: 50%; transform: translate(-50%, -50%);
      z-index: 1050; min-width: 300px; max-width: 90%; text-align: center;
      padding: 15px; border-radius: 8px; font-weight: 500;
      box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }
    .pagination-wrapper {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 20px;
      padding: 10px 0;
    }
    .pagination-info {
      font-size: 14px;
      color: #000;
      font-weight: 500;
    }
    .aksi-btn { display: flex; gap: 5px; justify-content: center; }
    .badge-jenis { font-size: 11px; padding: 4px 8px; }
    .form-icon { margin-right: 5px; color: #6777ef; }
    
    /* Style untuk rekap harian */
    .table-rekap { font-size: 11px; }
    .table-rekap th, .table-rekap td { 
      padding: 4px; 
      text-align: center; 
      vertical-align: middle;
      border: 1px solid #dee2e6;
    }
    .table-rekap th { background: #f8f9fa; font-weight: 600; }
    .table-rekap .indikator-name { 
      text-align: left; 
      font-weight: 500;
      min-width: 200px;
      max-width: 300px;
    }
    .day-cell { min-width: 30px; }
    .total-cell { background: #e9ecef; font-weight: 700; }
    .formula-text { font-size: 10px; color: #000; font-style: italic; margin-bottom: 10px; }
    .filter-card-rekap { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
    .validasi-cell { min-width: 150px; vertical-align: middle !important; }
    .qr-tte { width: 80px; height: 80px; }
    .validasi-info { font-size: 9px; color: #555; }
    .btn-validasi { font-size: 11px; padding: 5px 10px; }
    
    /* Simple Modal Fix - Same as Working Example */
    .modal-backdrop {
      z-index: 1040 !important;
    }
    
    .modal {
      z-index: 1050 !important;
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

        <?php if(isset($_SESSION['flash_message'])): 
            $flashType = $_SESSION['flash_type'] ?? 'info';
        ?>
          <div class="alert alert-<?= $flashType ?> flash-center" id="flashMsg">
            <i class="fas fa-<?= $flashType=='success'?'check-circle':($flashType=='danger'?'exclamation-circle':'info-circle') ?>"></i>
            <?= htmlspecialchars($_SESSION['flash_message']); 
                unset($_SESSION['flash_message']); 
                unset($_SESSION['flash_type']); 
            ?>
          </div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header">
            <h4><i class="fas fa-chart-line"></i> Input Harian Indikator</h4>
          </div>
          <div class="card-body">
            <ul class="nav nav-tabs">
              <li class="nav-item">
                <a class="nav-link <?= ($activeTab=='input')?'active':'' ?>" data-toggle="tab" href="#input">
                  <i class="fas fa-plus-circle"></i> Input Data
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= ($activeTab=='data')?'active':'' ?>" data-toggle="tab" href="#data">
                  <i class="fas fa-database"></i> Data Harian
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= ($activeTab=='rekap')?'active':'' ?>" data-toggle="tab" href="#rekap">
                  <i class="fas fa-file-alt"></i> Rekap Harian
                </a>
              </li>
            </ul>

            <div class="tab-content mt-3">
              <!-- FORM INPUT -->
              <div class="tab-pane fade <?= ($activeTab=='input')?'show active':'' ?>" id="input">
                <form method="POST">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label><i class="fas fa-layer-group form-icon"></i> Jenis Indikator <span class="text-danger">*</span></label>
                        <select name="jenis_indikator" id="jenis_indikator" class="form-control" required>
                          <option value="">-- Pilih Jenis --</option>
                          <option value="nasional">Indikator Nasional</option>
                          <option value="rs">Indikator RS</option>
                          <option value="unit">Indikator Unit</option>
                        </select>
                      </div>
                      <div class="form-group">
                        <label><i class="fas fa-list form-icon"></i> Indikator <span class="text-danger">*</span></label>
                        <select name="id_indikator" id="id_indikator" class="form-control select2" required>
                          <option value="">-- Pilih Indikator --</option>
                        </select>
                      </div>
                      <div id="indikatorInfo" class="info-box" style="display:none;"></div>
                      <div class="form-group mt-3">
                        <label><i class="fas fa-calendar-alt form-icon"></i> Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                      </div>
                    </div>

                    <div class="col-md-6">
                      <div class="form-group">
                        <label>
                          <i class="fas fa-sort-numeric-up form-icon"></i> Numerator <span class="text-danger">*</span>
                          <span id="numeratorDetail" class="small" style="font-weight: normal; color: #000; display: none;"></span>
                        </label>
                        <input type="number" name="numerator" class="form-control" min="0" value="0" required>
                        <small class="form-text" style="color: #000;">Pembilang / Jumlah yang dicapai</small>
                      </div>
                      <div class="form-group">
                        <label>
                          <i class="fas fa-divide form-icon"></i> Denominator <span class="text-danger">*</span>
                          <span id="denominatorDetail" class="small" style="font-weight: normal; color: #000; display: none;"></span>
                        </label>
                        <input type="number" name="denominator" class="form-control" min="1" value="1" required>
                        <small class="form-text" style="color: #000;">Penyebut / Total keseluruhan (min: 1)</small>
                      </div>
                      <div class="form-group">
                        <label><i class="fas fa-comment-dots form-icon"></i> Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="3" placeholder="Catatan tambahan (opsional)"></textarea>
                      </div>
                      <div class="form-group text-right mt-4">
                        <button type="submit" name="simpan" class="btn btn-primary btn-lg">
                          <i class="fas fa-save"></i> Simpan Data
                        </button>
                        <button type="reset" class="btn btn-secondary btn-lg">
                          <i class="fas fa-redo"></i> Reset
                        </button>
                      </div>
                    </div>
                  </div>
                </form>
              </div>

              <!-- DATA -->
              <div class="tab-pane fade <?= ($activeTab=='data')?'show active':'' ?>" id="data">
               
                <div class="mb-3">
                  <form method="GET" class="form-inline">
                    <input type="hidden" name="tab" value="data">
                    <label class="mr-2"><i class="fas fa-filter"></i> Filter Jenis:</label>
                    <select name="filter_jenis" class="form-control mr-2" onchange="this.form.submit()">
                      <option value="">-- Semua Jenis --</option>
                      <option value="nasional" <?= ($filterJenis=='nasional')?'selected':'' ?>>Indikator Nasional</option>
                      <option value="rs" <?= ($filterJenis=='rs')?'selected':'' ?>>Indikator RS</option>
                      <option value="unit" <?= ($filterJenis=='unit')?'selected':'' ?>>Indikator Unit</option>
                    </select>
                  </form>
                </div>
                <div class="table-responsive">
                  <table class="table table-bordered table-striped dokumen-table">
                    <thead class="thead-light">
                      <tr>
                        <th width="40">No</th>
                        <th width="100">Tanggal</th>
                        <th width="80">Jenis</th>
                        <th>Indikator</th>
                        <th width="80">Numerator</th>
                        <th width="100">Denominator</th>
                        <th width="90">Persentase</th>
                        <th>Keterangan</th>
                        <th width="120">Petugas</th>
                        <th width="120">Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php
                    $qStr = "SELECT h.*, 
                              CASE h.jenis_indikator 
                                WHEN 'nasional' THEN (SELECT nama_indikator FROM indikator_nasional WHERE id_nasional=h.id_indikator) 
                                WHEN 'rs' THEN (SELECT nama_indikator FROM indikator_rs WHERE id_rs=h.id_indikator) 
                                WHEN 'unit' THEN (SELECT nama_indikator FROM indikator_unit WHERE id_unit=h.id_indikator) 
                              END AS nama_indikator,
                              CASE h.jenis_indikator 
                                WHEN 'nasional' THEN (SELECT penanggung_jawab FROM indikator_nasional WHERE id_nasional=h.id_indikator) 
                                WHEN 'rs' THEN (SELECT u.nama FROM indikator_rs r JOIN users u ON r.penanggung_jawab=u.id WHERE r.id_rs=h.id_indikator) 
                                WHEN 'unit' THEN (SELECT u.nama FROM indikator_unit iu JOIN users u ON iu.penanggung_jawab=u.id WHERE iu.id_unit=h.id_indikator) 
                              END AS penanggung_jawab_indikator
                              FROM indikator_harian h
                              WHERE (
                                h.petugas = '".mysqli_real_escape_string($conn, $nama_user)."' 
                                OR (
                                  (h.jenis_indikator = 'nasional' AND EXISTS (SELECT 1 FROM indikator_nasional n WHERE n.id_nasional = h.id_indikator AND n.penanggung_jawab = '".mysqli_real_escape_string($conn, $nama_user)."'))
                                  OR (h.jenis_indikator = 'rs' AND EXISTS (SELECT 1 FROM indikator_rs r JOIN users u ON r.penanggung_jawab = u.id WHERE r.id_rs = h.id_indikator AND u.nama = '".mysqli_real_escape_string($conn, $nama_user)."'))
                                  OR (h.jenis_indikator = 'unit' AND EXISTS (SELECT 1 FROM indikator_unit iu JOIN users u ON iu.penanggung_jawab = u.id WHERE iu.id_unit = h.id_indikator AND u.nama = '".mysqli_real_escape_string($conn, $nama_user)."'))
                                )
                              )";

                    if($filterJenis) {
                        $qStr .= " AND h.jenis_indikator='".mysqli_real_escape_string($conn,$filterJenis)."'";
                    }

                    $qStr .= " ORDER BY h.tanggal DESC, h.id_harian DESC LIMIT $limit OFFSET $offset";

                    $q = mysqli_query($conn, $qStr);
                    $no = $offset + 1;
                    if(mysqli_num_rows($q) > 0):
                      while($row = mysqli_fetch_assoc($q)): 
                        $persen = ($row['denominator'] > 0) ? ($row['numerator']/$row['denominator']*100) : 0;
                        
                        // Badge color berdasarkan jenis
                        $badgeColor = 'secondary';
                        switch($row['jenis_indikator']) {
                            case 'nasional': $badgeColor = 'primary'; break;
                            case 'rs': $badgeColor = 'success'; break;
                            case 'unit': $badgeColor = 'info'; break;
                        }
                        
                        ob_start(); ?>
                        <!-- Modal Edit -->
                        <div class="modal fade" id="editModal<?= $row['id_harian'] ?>" tabindex="-1">
                          <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                              <form method="POST">
                                <div class="modal-header bg-primary text-white">
                                  <h5 class="modal-title">Edit Data Harian</h5>
                                  <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                  <input type="hidden" name="id_harian" value="<?= $row['id_harian'] ?>">
                                  <input type="hidden" name="current_page" value="<?= $page ?>">
                                  
                                  <div class="row">
                                    <div class="col-md-6">
                                      <div class="form-group">
                                        <label>Jenis Indikator <span class="text-danger">*</span></label>
                                        <select name="jenis_indikator" class="form-control edit-jenis" 
                                                data-modal="<?= $row['id_harian'] ?>" required>
                                          <option value="">-- Pilih Jenis --</option>
                                          <option value="nasional" <?= ($row['jenis_indikator']=='nasional')?'selected':'' ?>>Indikator Nasional</option>
                                          <option value="rs" <?= ($row['jenis_indikator']=='rs')?'selected':'' ?>>Indikator RS</option>
                                          <option value="unit" <?= ($row['jenis_indikator']=='unit')?'selected':'' ?>>Indikator Unit</option>
                                        </select>
                                      </div>
                                      <div class="form-group">
                                        <label>Indikator <span class="text-danger">*</span></label>
                                        <select name="id_indikator" class="form-control edit-indikator" 
                                                data-modal="<?= $row['id_harian'] ?>" 
                                                data-selected="<?= $row['id_indikator'] ?>" required>
                                          <option value="">-- Pilih Indikator --</option>
                                        </select>
                                      </div>
                                      <div class="form-group">
                                        <label>Tanggal <span class="text-danger">*</span></label>
                                        <input type="date" name="tanggal" class="form-control" 
                                               value="<?= $row['tanggal'] ?>" required>
                                      </div>
                                    </div>
                                    <div class="col-md-6">
                                      <div class="form-group">
                                        <label>Numerator <span class="text-danger">*</span></label>
                                        <input type="number" name="numerator" class="form-control" min="0"
                                               value="<?= $row['numerator'] ?>" required>
                                      </div>
                                      <div class="form-group">
                                        <label>Denominator <span class="text-danger">*</span></label>
                                        <input type="number" name="denominator" class="form-control" min="1"
                                               value="<?= $row['denominator'] ?>" required>
                                      </div>
                                      <div class="form-group">
                                        <label>Keterangan</label>
                                        <textarea name="keterangan" class="form-control" rows="3"><?= htmlspecialchars($row['keterangan']) ?></textarea>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                                <div class="modal-footer">
                                  <button type="submit" name="update" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Simpan Perubahan
                                  </button>
                                  <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                    <i class="fas fa-times"></i> Batal
                                  </button>
                                </div>
                              </form>
                            </div>
                          </div>
                        </div>
                        <?php $modals[] = ob_get_clean(); ?>
                      <tr>
                        <td><?= $no++ ?></td>
                        <td><?= date('d-m-Y', strtotime($row['tanggal'])) ?></td>
                        <td><span class="badge badge-<?= $badgeColor ?> badge-jenis"><?= strtoupper($row['jenis_indikator']) ?></span></td>
                        <td>
                          <?= htmlspecialchars($row['nama_indikator']) ?>
                          <?php if($row['petugas'] != $nama_user): ?>
                            <br><small class="text-muted"><i class="fas fa-user-shield"></i> Sebagai Penanggung Jawab</small>
                          <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $row['numerator'] ?></td>
                        <td class="text-center"><?= $row['denominator'] ?></td>
                        <td class="text-center"><b><?= number_format($persen,2) ?>%</b></td>
                        <td><?= htmlspecialchars($row['keterangan']) ?></td>
                        <td>
                          <?= htmlspecialchars($row['petugas']) ?>
                          <?php if($row['petugas'] == $nama_user): ?>
                            <br><span class="badge badge-success badge-sm">Saya</span>
                          <?php endif; ?>
                        </td>
                        <td class="text-center">
                          <div class="aksi-btn">
                            <?php if($row['petugas'] == $nama_user): ?>
                            <button class="btn btn-sm btn-warning" data-toggle="modal" 
                                    data-target="#editModal<?= $row['id_harian'] ?>" 
                                    title="Edit">
                              <i class="fas fa-edit"></i>
                            </button>
                            <a href="?hapus=<?= $row['id_harian'] ?>&page=<?= $page ?><?= $filterJenis?"&filter_jenis=$filterJenis":'' ?>" 
                               onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')" 
                               class="btn btn-sm btn-danger"
                               title="Hapus">
                              <i class="fas fa-trash"></i>
                            </a>
                            <?php else: ?>
                            <button class="btn btn-sm btn-secondary" disabled title="Hanya bisa dilihat">
                              <i class="fas fa-eye"></i>
                            </button>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                      <?php endwhile;
                    else: ?>
                      <tr>
                        <td colspan="10" class="text-center">Tidak ada data</td>
                      </tr>
                    <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <!-- Pagination -->
                <?php if($totalPages > 1): ?>
                <div class="pagination-wrapper">
                  <div class="pagination-info">
                    Menampilkan <?= $offset + 1 ?> - <?= min($offset + $limit, $totalData) ?> dari <?= $totalData ?> data
                  </div>
                  <nav>
                    <ul class="pagination mb-0">
                      <!-- Previous Button -->
                      <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?><?= $filterJenis?"&filter_jenis=$filterJenis":'' ?>" aria-label="Previous">
                          <span aria-hidden="true">&laquo;</span>
                        </a>
                      </li>

                      <?php
                      // Pagination logic
                      $start = max(1, $page - 2);
                      $end = min($totalPages, $page + 2);

                      // First page
                      if($start > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=1<?= $filterJenis?"&filter_jenis=$filterJenis":'' ?>">1</a></li>
                        <?php if($start > 2): ?>
                          <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif;
                      endif;

                      // Page numbers
                      for($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                          <a class="page-link" href="?page=<?= $i ?><?= $filterJenis?"&filter_jenis=$filterJenis":'' ?>"><?= $i ?></a>
                        </li>
                      <?php endfor;

                      // Last page
                      if($end < $totalPages): 
                        if($end < $totalPages - 1): ?>
                          <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $totalPages ?><?= $filterJenis?"&filter_jenis=$filterJenis":'' ?>"><?= $totalPages ?></a></li>
                      <?php endif; ?>

                      <!-- Next Button -->
                      <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?><?= $filterJenis?"&filter_jenis=$filterJenis":'' ?>" aria-label="Next">
                          <span aria-hidden="true">&raquo;</span>
                        </a>
                      </li>
                    </ul>
                  </nav>
                </div>
                <?php endif; ?>

              </div>

              <!-- REKAP HARIAN -->
              <div class="tab-pane fade <?= ($activeTab=='rekap')?'show active':'' ?>" id="rekap">
              
                <!-- Filter -->
                <div class="filter-card-rekap">
                  <form method="GET" id="filterRekapForm">
                    <input type="hidden" name="tab" value="rekap">
                    <div class="row">
                      <div class="col-md-2">
                        <div class="form-group mb-2">
                          <label><i class="fas fa-calendar"></i> Bulan</label>
                          <select name="bulan" class="form-control form-control-sm" onchange="this.form.submit()">
                            <?php for($m=1; $m<=12; $m++): ?>
                              <option value="<?= $m ?>" <?= ($bulan==$m)?'selected':'' ?>>
                                <?= date('F', mktime(0,0,0,$m,1)) ?>
                              </option>
                            <?php endfor; ?>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-2">
                        <div class="form-group mb-2">
                          <label><i class="fas fa-calendar-alt"></i> Tahun</label>
                          <select name="tahun" class="form-control form-control-sm" onchange="this.form.submit()">
                            <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                              <option value="<?= $y ?>" <?= ($tahun==$y)?'selected':'' ?>><?= $y ?></option>
                            <?php endfor; ?>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="form-group mb-2">
                          <label><i class="fas fa-layer-group"></i> Jenis Indikator</label>
                          <select name="jenis_rekap" id="jenisFilterRekap" class="form-control form-control-sm" onchange="updateIndikatorFilterRekap()">
                            <option value="">-- Semua Jenis --</option>
                            <option value="nasional" <?= ($jenis_indikator_rekap=='nasional')?'selected':'' ?>>Indikator Nasional</option>
                            <option value="rs" <?= ($jenis_indikator_rekap=='rs')?'selected':'' ?>>Indikator RS</option>
                            <option value="unit" <?= ($jenis_indikator_rekap=='unit')?'selected':'' ?>>Indikator Unit</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="form-group mb-2">
                          <label><i class="fas fa-list"></i> Indikator</label>
                          <select name="indikator_rekap" id="indikatorFilterRekap" class="form-control form-control-sm">
                            <option value="">-- Semua Indikator --</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-2">
                        <div class="form-group mb-2">
                          <label>&nbsp;</label>
                          <button type="submit" class="btn btn-primary btn-sm btn-block">
                            <i class="fas fa-search"></i> Filter
                          </button>
                        </div>
                      </div>
                    </div>
                  </form>
                </div>

                <!-- Tabel Rekap -->
                <?php
                // Jumlah hari dalam bulan
                $jumlahHari = date('t', mktime(0, 0, 0, $bulan, 1, $tahun));
                
                // Filter WHERE untuk rekap - Data yang DIINPUT USER atau TANGGUNG JAWAB USER
                $whereClauseRekap = "WHERE MONTH(h.tanggal) = $bulan AND YEAR(h.tanggal) = $tahun";
                $whereClauseRekap .= " AND (
                    h.petugas = '".mysqli_real_escape_string($conn, $nama_user)."'
                    OR (
                      (h.jenis_indikator = 'nasional' AND EXISTS (SELECT 1 FROM indikator_nasional n WHERE n.id_nasional = h.id_indikator AND n.penanggung_jawab = '".mysqli_real_escape_string($conn, $nama_user)."'))
                      OR (h.jenis_indikator = 'rs' AND EXISTS (SELECT 1 FROM indikator_rs r JOIN users u ON r.penanggung_jawab = u.id WHERE r.id_rs = h.id_indikator AND u.nama = '".mysqli_real_escape_string($conn, $nama_user)."'))
                      OR (h.jenis_indikator = 'unit' AND EXISTS (SELECT 1 FROM indikator_unit iu JOIN users u ON iu.penanggung_jawab = u.id WHERE iu.id_unit = h.id_indikator AND u.nama = '".mysqli_real_escape_string($conn, $nama_user)."'))
                    )
                  )";
                
                if ($jenis_indikator_rekap) {
                    $whereClauseRekap .= " AND h.jenis_indikator = '$jenis_indikator_rekap'";
                }
                if ($id_indikator_rekap > 0) {
                    $whereClauseRekap .= " AND h.id_indikator = $id_indikator_rekap";
                }
                
                // Query untuk mendapatkan indikator yang ada datanya
                $qIndikatorRekap = mysqli_query($conn, "SELECT DISTINCT h.jenis_indikator, h.id_indikator
                                                   FROM indikator_harian h
                                                   $whereClauseRekap
                                                   ORDER BY h.jenis_indikator, h.id_indikator");
                
                if (mysqli_num_rows($qIndikatorRekap) > 0):
                  while($indRow = mysqli_fetch_assoc($qIndikatorRekap)):
                    $jenis = $indRow['jenis_indikator'];
                    $idInd = $indRow['id_indikator'];
                    
                    // Ambil data indikator
                    $namaIndikator = '';
                    $standar = 0;
                    $numeratorDef = '';
                    $denominatorDef = '';
                    $penanggungJawab = '';
                    
                    if ($jenis == 'nasional') {
                        $qInfo = mysqli_query($conn, "SELECT nama_indikator, standar, numerator, denominator, penanggung_jawab 
                                                       FROM indikator_nasional WHERE id_nasional = $idInd");
                    } elseif ($jenis == 'rs') {
                        $qInfo = mysqli_query($conn, "SELECT r.nama_indikator, r.standar, r.numerator, r.denominator, u.nama as penanggung_jawab 
                                                       FROM indikator_rs r
                                                       LEFT JOIN users u ON r.penanggung_jawab = u.id
                                                       WHERE r.id_rs = $idInd");
                    } else {
                        $qInfo = mysqli_query($conn, "SELECT iu.nama_indikator, iu.standar, iu.numerator, iu.denominator, u.nama as penanggung_jawab 
                                                       FROM indikator_unit iu
                                                       LEFT JOIN users u ON iu.penanggung_jawab = u.id
                                                       WHERE iu.id_unit = $idInd");
                    }
                    
                    if ($qInfo && $infoRow = mysqli_fetch_assoc($qInfo)) {
                        $namaIndikator = $infoRow['nama_indikator'];
                        $standar = $infoRow['standar'];
                        $numeratorDef = $infoRow['numerator'];
                        $denominatorDef = $infoRow['denominator'];
                        $penanggungJawab = $infoRow['penanggung_jawab'];
                    }
                    
                    // Cek apakah user adalah penanggung jawab
                    $isPenanggungJawab = ($nama_user == $penanggungJawab);
                    
                    // Query data harian untuk indikator ini
                    $qData = mysqli_query($conn, "SELECT h.*, DAY(h.tanggal) as hari
                                                  FROM indikator_harian h
                                                  WHERE h.jenis_indikator = '$jenis' 
                                                  AND h.id_indikator = $idInd
                                                  AND MONTH(h.tanggal) = $bulan 
                                                  AND YEAR(h.tanggal) = $tahun
                                                  ORDER BY h.tanggal");
                    
                    $dataHarian = [];
                    $totalNumerator = 0;
                    $totalDenominator = 0;
                    $statusValidasi = '';
                    $validasiOleh = '';
                    $validasiTanggal = '';
                    $tteToken = '';
                    
                    while($dataRow = mysqli_fetch_assoc($qData)) {
                        $hari = $dataRow['hari'];
                        $dataHarian[$hari] = $dataRow;
                        $totalNumerator += $dataRow['numerator'];
                        $totalDenominator += $dataRow['denominator'];
                        
                        // Ambil status validasi dari salah satu data
                        if (!empty($dataRow['status_validasi'])) {
                            $statusValidasi = $dataRow['status_validasi'];
                            $validasiOleh = $dataRow['validasi_oleh'];
                            $validasiTanggal = $dataRow['validasi_tanggal'];
                            $tteToken = $dataRow['tte_token'];
                        }
                    }
                    
                    $persentaseTotal = ($totalDenominator > 0) ? ($totalNumerator / $totalDenominator * 100) : 0;
                    ?>
                    
                    <div class="mb-4">
                      <h6 class="mb-2">
                        <span class="badge badge-<?= ($jenis=='nasional')?'primary':(($jenis=='rs')?'success':'info') ?>">
                          <?= strtoupper($jenis) ?>
                        </span>
                        <?= htmlspecialchars($namaIndikator) ?>
                      </h6>
                      <div class="formula-text">
                        <strong>Numerator:</strong> <?= htmlspecialchars($numeratorDef) ?> | 
                        <strong>Denominator:</strong> <?= htmlspecialchars($denominatorDef) ?> | 
                        <strong>Standar:</strong> <?= $standar ?>%
                      </div>
                      
                      <div class="table-responsive">
                        <table class="table table-bordered table-rekap table-sm">
                          <thead>
                            <tr>
                              <th rowspan="2" class="indikator-name">Indikator Mutu</th>
                              <?php for($d=1; $d<=$jumlahHari; $d++): ?>
                                <th class="day-cell"><?= $d ?></th>
                              <?php endfor; ?>
                              <th rowspan="2" class="total-cell">Total</th>
                              <th rowspan="2">Capaian</th>
                              <th rowspan="2" class="validasi-cell">Validasi</th>
                            </tr>
                          </thead>
                          <tbody>
                            <!-- Row Numerator -->
                            <tr>
                              <td class="indikator-name">Numerator</td>
                              <?php for($d=1; $d<=$jumlahHari; $d++): ?>
                                <td><?= isset($dataHarian[$d]) ? $dataHarian[$d]['numerator'] : '-' ?></td>
                              <?php endfor; ?>
                              <td class="total-cell"><?= $totalNumerator ?></td>
                              <td rowspan="2" class="total-cell">
                                <strong><?= number_format($persentaseTotal, 2) ?>%</strong>
                                <?php if($persentaseTotal >= $standar): ?>
                                  <br><span class="badge badge-success">Tercapai</span>
                                <?php else: ?>
                                  <br><span class="badge badge-danger">Tidak Tercapai</span>
                                <?php endif; ?>
                              </td>
                              <td rowspan="2" class="validasi-cell text-center">
                                <?php if ($statusValidasi == 'tervalidasi' && $tteToken): ?>
                                  <!-- Sudah Tervalidasi dengan TTE -->
                                  <div class="text-success mb-2">
                                    <i class="fas fa-check-circle"></i> <strong>TERVALIDASI</strong>
                                  </div>
                                  <?php
                                  // Generate QR Code URL
                                  $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=" . urlencode("http://".$_SERVER['HTTP_HOST']."/cek_tte.php?token=".$tteToken);
                                  ?>
                                  <img src="<?= $qr_url ?>" class="qr-tte mb-1" alt="QR TTE">
                                  <div class="validasi-info">
                                    Oleh: <strong><?= htmlspecialchars($validasiOleh) ?></strong><br>
                                    <?= date('d/m/Y H:i', strtotime($validasiTanggal)) ?>
                                  </div>
                                <?php elseif ($isPenanggungJawab): ?>
                                  <!-- Tombol Validasi untuk Penanggung Jawab -->
                                  <button class="btn btn-warning btn-validasi" data-toggle="modal" data-target="#validasiModal<?= $jenis ?>_<?= $idInd ?>">
                                    <i class="fas fa-stamp"></i> Validasi dengan TTE
                                  </button>
                                <?php else: ?>
                                  <!-- Belum Tervalidasi -->
                                  <div class="text-muted">
                                    <i class="fas fa-hourglass-half"></i><br>
                                    <small>Belum Divalidasi</small>
                                  </div>
                                <?php endif; ?>
                              </td>
                            </tr>
                            <!-- Row Denominator -->
                            <tr>
                              <td class="indikator-name">Denominator</td>
                              <?php for($d=1; $d<=$jumlahHari; $d++): ?>
                                <td><?= isset($dataHarian[$d]) ? $dataHarian[$d]['denominator'] : '-' ?></td>
                              <?php endfor; ?>
                              <td class="total-cell"><?= $totalDenominator ?></td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                    
                    <?php
                    // Simpan Modal Validasi untuk di-render di akhir (seperti modal edit)
                    if ($isPenanggungJawab && $statusValidasi != 'tervalidasi'):
                      ob_start();
                    ?>
                    <!-- Modal Validasi -->
                    <div class="modal fade" id="validasiModal<?= $jenis ?>_<?= $idInd ?>" tabindex="-1">
                      <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                          <form method="POST">
                            <div class="modal-header bg-warning text-white">
                              <h5 class="modal-title"><i class="fas fa-stamp"></i> Validasi Data dengan TTE</h5>
                              <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                              </button>
                            </div>
                            <div class="modal-body">
                              <input type="hidden" name="jenis_indikator" value="<?= $jenis ?>">
                              <input type="hidden" name="id_indikator" value="<?= $idInd ?>">
                              <input type="hidden" name="bulan_val" value="<?= $bulan ?>">
                              <input type="hidden" name="tahun_val" value="<?= $tahun ?>">
                              
                              <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Validasi akan diterapkan untuk <strong>semua data</strong> indikator ini di bulan <strong><?= date('F Y', mktime(0,0,0,$bulan,1,$tahun)) ?></strong> dengan menggunakan Tanda Tangan Elektronik (TTE) Anda.
                              </div>
                              
                              <div class="form-group">
                                <label><strong>Indikator:</strong></label>
                                <p><?= htmlspecialchars($namaIndikator) ?></p>
                              </div>
                              
                              <div class="row">
                                <div class="col-md-6">
                                  <div class="form-group">
                                    <label><strong>Total Numerator:</strong></label>
                                    <p class="text-primary font-weight-bold"><?= $totalNumerator ?></p>
                                  </div>
                                </div>
                                <div class="col-md-6">
                                  <div class="form-group">
                                    <label><strong>Total Denominator:</strong></label>
                                    <p class="text-primary font-weight-bold"><?= $totalDenominator ?></p>
                                  </div>
                                </div>
                              </div>
                              
                              <div class="form-group">
                                <label><strong>Capaian:</strong></label>
                                <p class="font-weight-bold text-<?= ($persentaseTotal >= $standar) ? 'success' : 'danger' ?>">
                                  <?= number_format($persentaseTotal, 2) ?>% 
                                  <?php if($persentaseTotal >= $standar): ?>
                                    <span class="badge badge-success">Tercapai (Standar: <?= $standar ?>%)</span>
                                  <?php else: ?>
                                    <span class="badge badge-danger">Tidak Tercapai (Standar: <?= $standar ?>%)</span>
                                  <?php endif; ?>
                                </p>
                              </div>
                              
                              <hr>
                              
                              <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> <strong>Perhatian:</strong> Dengan menekan tombol "Validasi dengan TTE", Anda menyatakan bahwa data ini sudah benar dan akan ditandatangani secara elektronik dengan TTE Anda.
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                <i class="fas fa-times"></i> Batal
                              </button>
                              <button type="submit" name="validasi_rekap" class="btn btn-primary">
                                <i class="fas fa-stamp"></i> Validasi dengan TTE
                              </button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                    <?php 
                      $modals[] = ob_get_clean();
                    endif; 
                    ?>
                    
                  <?php endwhile;
                else: ?>
                  <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle"></i> Tidak ada data untuk periode dan filter yang dipilih.
                  </div>
                <?php endif; ?>
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
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function(){
  // Auto hide flash message
  setTimeout(function(){ $("#flashMsg").fadeOut("slow"); }, 3000);
  
  // Inisialisasi Select2
  $('.select2').select2({
    placeholder: "-- Pilih Indikator --", 
    allowClear: true, 
    width: '100%'
  });
  
  // Data indikator dari PHP
  var indikatorData = <?= json_encode($indikators) ?>;

  // Handler untuk perubahan jenis indikator (form input)
  $("#jenis_indikator").change(function(){
    var jenis = $(this).val();
    var $idInd = $("#id_indikator");
    $idInd.empty().append('<option value="">-- Pilih Indikator --</option>');
    
    if(indikatorData[jenis]){
      indikatorData[jenis].forEach(function(opt){
        var text = (opt.nama_unit ? opt.nama_unit + ' - ' : '') + opt.nama_indikator;
        $idInd.append('<option data-standar="'+opt.standar+'" data-numerator="'+(opt.numerator || '')+'" data-denominator="'+(opt.denominator || '')+'" value="'+opt.id+'">'+text+'</option>');
      });
    }
    $idInd.val(null).trigger('change');
    $("#indikatorInfo").hide();
    $("#numeratorDetail").hide();
    $("#denominatorDetail").hide();
  });

  // Handler untuk perubahan indikator (form input)
  $("#id_indikator").change(function(){
    var standar = $(this).find(':selected').data('standar') || '';
    var numerator = $(this).find(':selected').data('numerator') || '';
    var denominator = $(this).find(':selected').data('denominator') || '';
    var nama = $(this).find(':selected').text();
    
    if(nama && nama != '-- Pilih Indikator --'){ 
      $("#indikatorInfo").html("<b><i class='fas fa-chart-line'></i> Indikator:</b> "+nama+"<br><b><i class='fas fa-bullseye'></i> Standar:</b> "+standar+"%").show(); 
      
      // Tampilkan detail numerator jika ada
      if(numerator) {
        $("#numeratorDetail").html("(" + numerator + ")").show();
      } else {
        $("#numeratorDetail").hide();
      }
      
      // Tampilkan detail denominator jika ada
      if(denominator) {
        $("#denominatorDetail").html("(" + denominator + ")").show();
      } else {
        $("#denominatorDetail").hide();
      }
    } else { 
      $("#indikatorInfo").hide(); 
      $("#numeratorDetail").hide();
      $("#denominatorDetail").hide();
    }
  });

  // Handler untuk perubahan jenis indikator di modal edit
  $(".edit-jenis").change(function(){
    var jenis = $(this).val();
    var modalId = $(this).data('modal');
    var $idInd = $(".edit-indikator[data-modal='"+modalId+"']");
    var selectedId = $idInd.data('selected');
    
    $idInd.empty().append('<option value="">-- Pilih Indikator --</option>');
    
    if(indikatorData[jenis]){
      indikatorData[jenis].forEach(function(opt){
        var text = (opt.nama_unit ? opt.nama_unit + ' - ' : '') + opt.nama_indikator;
        var selected = (opt.id == selectedId) ? 'selected' : '';
        $idInd.append('<option value="'+opt.id+'" '+selected+'>'+text+'</option>');
      });
    }
  });

  // Trigger perubahan jenis untuk setiap modal saat modal dibuka
  $('.modal').on('shown.bs.modal', function() {
    $(this).find('.edit-jenis').trigger('change');
  });
  
  // Handler untuk filter indikator di tab Rekap Harian
  window.updateIndikatorFilterRekap = function() {
    var jenis = $('#jenisFilterRekap').val();
    var $indFilter = $('#indikatorFilterRekap');
    
    $indFilter.empty().append('<option value="">-- Semua Indikator --</option>');
    
    if(jenis && indikatorData[jenis]) {
      indikatorData[jenis].forEach(function(opt) {
        var text = (opt.nama_unit ? opt.nama_unit + ' - ' : '') + opt.nama_indikator;
        $indFilter.append('<option value="'+opt.id+'">'+text+'</option>');
      });
    }
  };
  
  // Initialize filter rekap saat load
  updateIndikatorFilterRekap();
  <?php if($id_indikator_rekap > 0): ?>
  $('#indikatorFilterRekap').val(<?= $id_indikator_rekap ?>);
  <?php endif; ?>
});
</script>

<?php
// Render all modals
foreach ($modals as $m) echo $m;
?>

</body>
</html>