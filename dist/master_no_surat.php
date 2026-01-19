<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// Cek login
$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    echo "<script>alert('Anda belum login.'); window.location.href='login.php';</script>";
    exit;
}

$current_file = basename(__FILE__);

// Cek akses menu
$query = "SELECT 1 FROM akses_menu 
          JOIN menu ON akses_menu.menu_id = menu.id 
          WHERE akses_menu.user_id = ? AND menu.file_menu = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("is", $user_id, $current_file);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows == 0) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini.'); window.location.href='dashboard.php';</script>";
    exit;
}

// ============================================
// PROSES FORM ACTIONS
// ============================================

// Tambah Jenis Dokumen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_jenis') {
    $kode = strtoupper($conn->real_escape_string($_POST['kode_dokumen']));
    $nama = $conn->real_escape_string($_POST['nama_dokumen']);
    $desk = $conn->real_escape_string($_POST['deskripsi']);
    
    // Cek duplikasi
    $check = $conn->query("SELECT 1 FROM jenis_dokumen WHERE kode_dokumen = '$kode'");
    if ($check->num_rows > 0) {
        $_SESSION['flash_message'] = "Kode dokumen '$kode' sudah ada!";
        $_SESSION['flash_type'] = 'warning';
    } else {
        if ($conn->query("INSERT INTO jenis_dokumen (kode_dokumen, nama_dokumen, deskripsi) VALUES ('$kode', '$nama', '$desk')")) {
            $_SESSION['flash_message'] = "Jenis dokumen berhasil ditambahkan!";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = "Error: " . $conn->error;
            $_SESSION['flash_type'] = 'danger';
        }
    }
    header("Location: master_no_surat.php?tab=jenis");
    exit;
}

// Edit Jenis Dokumen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_jenis') {
    $id = intval($_POST['jenis_id']);
    $kode = strtoupper($conn->real_escape_string($_POST['kode_dokumen']));
    $nama = $conn->real_escape_string($_POST['nama_dokumen']);
    $desk = $conn->real_escape_string($_POST['deskripsi']);
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    
    if ($conn->query("UPDATE jenis_dokumen SET kode_dokumen='$kode', nama_dokumen='$nama', deskripsi='$desk', aktif=$aktif WHERE id=$id")) {
        $_SESSION['flash_message'] = "Jenis dokumen berhasil diupdate!";
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = "Error: " . $conn->error;
        $_SESSION['flash_type'] = 'danger';
    }
    header("Location: master_no_surat.php?tab=jenis");
    exit;
}

// Hapus Jenis Dokumen
if (isset($_GET['hapus_jenis'])) {
    $id = intval($_GET['hapus_jenis']);
    if ($conn->query("DELETE FROM jenis_dokumen WHERE id=$id")) {
        $_SESSION['flash_message'] = "Jenis dokumen berhasil dihapus!";
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = "Error: " . $conn->error;
        $_SESSION['flash_type'] = 'danger';
    }
    header("Location: master_no_surat.php?tab=jenis");
    exit;
}

// Update Kode Unit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_kode_unit') {
    $unit_id = intval($_POST['unit_id']);
    $kode = strtoupper($conn->real_escape_string($_POST['kode_unit']));
    
    if ($conn->query("UPDATE unit_kerja SET kode_unit = '$kode' WHERE id = $unit_id")) {
        $_SESSION['flash_message'] = "Kode unit berhasil diupdate!";
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = "Error: " . $conn->error;
        $_SESSION['flash_type'] = 'danger';
    }
    header("Location: master_no_surat.php?tab=unit");
    exit;
}

// Tab aktif
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
$tahun_filter = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');

// Ambil data untuk dashboard
$qTotalJenis = $conn->query("SELECT COUNT(*) as total FROM jenis_dokumen WHERE aktif=1");
$totalJenis = $qTotalJenis ? $qTotalJenis->fetch_assoc()['total'] : 0;

$qTotalUnit = $conn->query("SELECT COUNT(*) as total FROM unit_kerja WHERE kode_unit IS NOT NULL AND kode_unit != ''");
$totalUnit = $qTotalUnit ? $qTotalUnit->fetch_assoc()['total'] : 0;

$qTotalDokumen = $conn->query("SELECT COUNT(*) as total FROM log_penomoran WHERE YEAR(created_at) = $tahun_filter");
$totalDokumen = $qTotalDokumen ? $qTotalDokumen->fetch_assoc()['total'] : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Management & Laporan Surat/Dokumen</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<style>
.table-responsive { margin-top:20px; overflow-x:auto; }
.flash-center { 
    position:fixed; 
    top:20%; 
    left:50%; 
    transform:translate(-50%,-50%); 
    z-index:1050; 
    min-width:300px; 
    max-width:90%; 
    text-align:center; 
    padding:15px; 
    border-radius:8px; 
    font-weight:500; 
    box-shadow:0 5px 15px rgba(0,0,0,0.3);
}
.stat-card {
    border-left: 4px solid #6777ef;
    transition: transform 0.2s;
    height: 100%;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    color: #6777ef;
}
.nav-tabs .nav-link {
    color: #6c757d;
    border: none;
    border-bottom: 3px solid transparent;
}
.nav-tabs .nav-link.active {
    color: #6777ef;
    background-color: transparent;
    border-bottom: 3px solid #6777ef;
}
.nav-tabs .nav-link:hover {
    border-bottom: 3px solid #6777ef;
}
.badge-kode {
    font-size: 0.9rem;
    padding: 0.4rem 0.8rem;
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
<div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> flash-center" id="flashMsg">
    <?= htmlspecialchars($_SESSION['flash_message']) ?>
</div>
<?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-file-alt"></i> Management & Laporan Surat/Dokumen</h4>
            </div>
            <div class="card-body">
                
                <!-- Nav Tabs -->
                <ul class="nav nav-tabs mb-4" id="masterTabs">
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab == 'dashboard' ? 'active' : '' ?>" href="?tab=dashboard">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab == 'jenis' ? 'active' : '' ?>" href="?tab=jenis">
                            <i class="fas fa-file-alt"></i> Jenis Dokumen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab == 'unit' ? 'active' : '' ?>" href="?tab=unit">
                            <i class="fas fa-building"></i> Kode Unit
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab == 'log' ? 'active' : '' ?>" href="?tab=log">
                            <i class="fas fa-history"></i> Log Penomoran
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab == 'statistik' ? 'active' : '' ?>" href="?tab=statistik">
                            <i class="fas fa-chart-bar"></i> Statistik
                        </a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content">
                
                    <!-- ==================== TAB DASHBOARD ==================== -->
                    <?php if ($activeTab == 'dashboard'): ?>
                    <div class="tab-pane active">
                        
                        <!-- Statistik Cards -->
                        <div class="row mb-4">
                            <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="text-muted mb-2">Total Jenis Dokumen</h6>
                                                <div class="stat-number"><?= $totalJenis ?></div>
                                            </div>
                                            <div class="text-primary" style="font-size: 3rem;">
                                                <i class="fas fa-file-alt"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="text-muted mb-2">Total Unit Kerja</h6>
                                                <div class="stat-number"><?= $totalUnit ?></div>
                                            </div>
                                            <div class="text-success" style="font-size: 3rem;">
                                                <i class="fas fa-building"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="text-muted mb-2">Dokumen Tahun Ini</h6>
                                                <div class="stat-number"><?= $totalDokumen ?></div>
                                            </div>
                                            <div class="text-warning" style="font-size: 3rem;">
                                                <i class="fas fa-file-invoice"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="text-muted mb-2">Tahun Aktif</h6>
                                                <div class="stat-number"><?= date('Y') ?></div>
                                            </div>
                                            <div class="text-info" style="font-size: 3rem;">
                                                <i class="fas fa-calendar"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Info Panel -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h4>Tentang Sistem</h4>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Sistem Penomoran Dokumen Otomatis</strong> dirancang untuk mengelola nomor dokumen secara terpusat dan terstruktur.</p>
                                        
                                        <h6 class="mt-3"><i class="fas fa-check-circle text-success"></i> Fitur Utama:</h6>
                                        <ul>
                                            <li>Penomoran otomatis per unit & jenis dokumen</li>
                                            <li>Auto increment per bulan</li>
                                            <li>Auto reset setiap tahun baru</li>
                                            <li>Format nomor terstruktur dan konsisten</li>
                                            <li>Log audit trail lengkap</li>
                                        </ul>
                                        
                                        <h6 class="mt-3"><i class="fas fa-info-circle text-info"></i> Format Nomor:</h6>
                                        <div class="alert alert-light">
                                            <code>001/SPO-IT/RSPH/I/2026</code>
                                            <small class="d-block mt-2">
                                                <strong>001</strong> = Nomor urut<br>
                                                <strong>SPO-IT</strong> = Jenis-Kode Unit<br>
                                                <strong>RSPH</strong> = Kode Instansi<br>
                                                <strong>I</strong> = Bulan (Romawi)<br>
                                                <strong>2026</strong> = Tahun
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h4>Panduan Singkat</h4>
                                    </div>
                                    <div class="card-body">
                                        <h6><i class="fas fa-step-forward text-primary"></i> Langkah Penggunaan:</h6>
                                        
                                        <ol class="mb-4">
                                            <li><strong>Setup Jenis Dokumen</strong><br>
                                                <small class="text-muted">Tambahkan jenis dokumen (SPO, SE, SK, dll) di tab <strong>Jenis Dokumen</strong></small>
                                            </li>
                                            
                                            <li><strong>Setup Kode Unit</strong><br>
                                                <small class="text-muted">Pastikan setiap unit kerja memiliki kode di tab <strong>Kode Unit</strong></small>
                                            </li>
                                            
                                            <li><strong>Generate Nomor</strong><br>
                                                <small class="text-muted">Nomor akan otomatis di-generate saat membuat dokumen baru</small>
                                            </li>
                                            
                                            <li><strong>Monitoring</strong><br>
                                                <small class="text-muted">Lihat log dan statistik di tab <strong>Log</strong> & <strong>Statistik</strong></small>
                                            </li>
                                        </ol>
                                        
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i> 
                                            <strong>Penting!</strong> Pastikan semua unit kerja sudah memiliki kode unit sebelum mulai membuat dokumen.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    <?php endif; ?>

                    <!-- ==================== TAB JENIS DOKUMEN ==================== -->
                    <?php if ($activeTab == 'jenis'): ?>
                    <div class="tab-pane active">
                        <div class="row">
                            <div class="col-lg-8">
                                <h5 class="mb-3"><i class="fas fa-list"></i> Daftar Jenis Dokumen</h5>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th width="50">No</th>
                                                <th width="120">Kode</th>
                                                <th>Nama Dokumen</th>
                                                <th>Deskripsi</th>
                                                <th width="100" class="text-center">Status</th>
                                                <th width="150" class="text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $qJenis = $conn->query("SELECT * FROM jenis_dokumen ORDER BY kode_dokumen");
                                            if($qJenis && mysqli_num_rows($qJenis) > 0):
                                                $no = 1;
                                                while ($row = $qJenis->fetch_assoc()):
                                            ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><span class="badge badge-primary badge-kode"><?= $row['kode_dokumen'] ?></span></td>
                                                <td><?= htmlspecialchars($row['nama_dokumen']) ?></td>
                                                <td><?= htmlspecialchars($row['deskripsi'] ?: '-') ?></td>
                                                <td class="text-center">
                                                    <?php if ($row['aktif']): ?>
                                                    <span class="badge badge-success">Aktif</span>
                                                    <?php else: ?>
                                                    <span class="badge badge-secondary">Nonaktif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#modalEdit<?= $row['id'] ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="?tab=jenis&hapus_jenis=<?= $row['id'] ?>" 
                                                       onclick="return confirm('Yakin ingin hapus jenis dokumen ini?')" 
                                                       class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php 
                                                endwhile;
                                            else:
                                            ?>
                                            <tr><td colspan="6" class="text-center">Belum ada data jenis dokumen</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="col-lg-4">
                                <div class="card card-primary">
                                    <div class="card-header">
                                        <h4><i class="fas fa-plus-circle"></i> Tambah Jenis Dokumen</h4>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="add_jenis">
                                            
                                            <div class="form-group">
                                                <label>Kode Dokumen <span class="text-danger">*</span></label>
                                                <input type="text" name="kode_dokumen" class="form-control" 
                                                       required maxlength="20" placeholder="SPO, SE, SK, dll" 
                                                       style="text-transform: uppercase;">
                                                <small class="form-text text-muted">
                                                    Maksimal 20 karakter, akan otomatis huruf besar
                                                </small>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Nama Dokumen <span class="text-danger">*</span></label>
                                                <input type="text" name="nama_dokumen" class="form-control" 
                                                       required maxlength="100" 
                                                       placeholder="Standar Prosedur Operasional">
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Deskripsi</label>
                                                <textarea name="deskripsi" class="form-control" rows="3" 
                                                          placeholder="Deskripsi jenis dokumen (opsional)"></textarea>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary btn-block">
                                                <i class="fas fa-plus-circle"></i> Tambah Jenis Dokumen
                                            </button>
                                        </form>
                                        
                                        <hr>
                                        
                                        <div class="alert alert-info">
                                            <h6><i class="fas fa-info-circle"></i> Informasi</h6>
                                            <small>
                                                Jenis dokumen digunakan untuk membedakan kategori surat/dokumen yang akan dibuat nomor otomatisnya.
                                                <br><br>
                                                <strong>Contoh:</strong><br>
                                                - SPO = Standar Prosedur Operasional<br>
                                                - SE = Surat Edaran<br>
                                                - SK = Surat Keputusan
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    <?php endif; ?>

                    <!-- ==================== TAB KODE UNIT ==================== -->
                    <?php if ($activeTab == 'unit'): ?>
                    <div class="tab-pane active">
                        <h5 class="mb-3"><i class="fas fa-building"></i> Manajemen Kode Unit Kerja</h5>
                        <p class="text-muted">Kode unit digunakan dalam format penomoran dokumen. Pastikan setiap unit memiliki kode yang unik dan sesuai.</p>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th width="50">No</th>
                                        <th>Nama Unit Kerja</th>
                                        <th width="150">Kode Unit</th>
                                        <th width="200" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $qUnit = $conn->query("SELECT * FROM unit_kerja ORDER BY nama_unit");
                                    if($qUnit && mysqli_num_rows($qUnit) > 0):
                                        $no = 1;
                                        while ($row = $qUnit->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($row['nama_unit']) ?></td>
                                        <td>
                                            <?php if (!empty($row['kode_unit'])): ?>
                                            <span class="badge badge-info badge-kode"><?= $row['kode_unit'] ?></span>
                                            <?php else: ?>
                                            <span class="badge badge-warning">Belum diset</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modalUnit<?= $row['id'] ?>">
                                                <i class="fas fa-edit"></i> <?= empty($row['kode_unit']) ? 'Set Kode' : 'Edit Kode' ?>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                    <tr><td colspan="4" class="text-center">Belum ada data unit kerja</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Penting!</strong> Kode unit yang sudah digunakan dalam penomoran dokumen sebaiknya tidak diubah untuk menjaga konsistensi.
                        </div>
                        
                    </div>
                    <?php endif; ?>

                    <!-- ==================== TAB LOG PENOMORAN ==================== -->
                    <?php if ($activeTab == 'log'): ?>
                    <div class="tab-pane active">
                        <h5 class="mb-3"><i class="fas fa-history"></i> Log Penomoran Dokumen</h5>
                        <p class="text-muted">Riwayat nomor dokumen yang telah di-generate oleh sistem</p>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th width="50">No</th>
                                        <th width="150">Tanggal</th>
                                        <th>Nomor Dokumen</th>
                                        <th width="100">Jenis</th>
                                        <th width="100">Unit</th>
                                        <th width="120">Tabel Ref</th>
                                        <th width="80">ID Ref</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $qLog = $conn->query("
                                        SELECT lp.*, jd.kode_dokumen, uk.kode_unit
                                        FROM log_penomoran lp
                                        JOIN master_nomor_dokumen mnd ON lp.master_nomor_id = mnd.id
                                        JOIN jenis_dokumen jd ON mnd.jenis_dokumen_id = jd.id
                                        JOIN unit_kerja uk ON mnd.unit_kerja_id = uk.id
                                        ORDER BY lp.created_at DESC
                                        LIMIT 100
                                    ");
                                    
                                    if (!$qLog || $qLog->num_rows == 0) {
                                        echo "<tr><td colspan='7' class='text-center'>Belum ada log penomoran</td></tr>";
                                    } else {
                                        $no = 1;
                                        while ($log = $qLog->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                                        <td><code><?= htmlspecialchars($log['nomor_lengkap']) ?></code></td>
                                        <td><span class="badge badge-primary"><?= $log['kode_dokumen'] ?></span></td>
                                        <td><span class="badge badge-info"><?= $log['kode_unit'] ?></span></td>
                                        <td><?= $log['tabel_referensi'] ?></td>
                                        <td class="text-center"><?= $log['referensi_id'] ?></td>
                                    </tr>
                                    <?php 
                                        endwhile;
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i> Menampilkan maksimal 100 log terakhir
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ==================== TAB STATISTIK ==================== -->
                    <?php if ($activeTab == 'statistik'): ?>
                    <div class="tab-pane active">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h5><i class="fas fa-chart-bar"></i> Statistik Penomoran Tahun <?= $tahun_filter ?></h5>
                            </div>
                            <div class="col-md-6 text-right">
                                <select class="form-control d-inline-block w-auto" onchange="location.href='?tab=statistik&tahun='+this.value">
                                    <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                                    <option value="<?= $y ?>" <?= $y == $tahun_filter ? 'selected' : '' ?>>
                                        Tahun <?= $y ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Statistik per Unit -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Unit Kerja</th>
                                        <?php
                                        $qJenis = $conn->query("SELECT id, kode_dokumen FROM jenis_dokumen ORDER BY kode_dokumen");
                                        $jenisDocs = [];
                                        if($qJenis && mysqli_num_rows($qJenis) > 0):
                                            while ($j = $qJenis->fetch_assoc()) {
                                                $jenisDocs[] = $j;
                                                echo "<th class='text-center'>{$j['kode_dokumen']}</th>";
                                            }
                                        endif;
                                        ?>
                                        <th class='text-center bg-light'><strong>Total</strong></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $qUnit = $conn->query("SELECT id, nama_unit, kode_unit FROM unit_kerja ORDER BY nama_unit");
                                    $grandTotal = 0;
                                    if($qUnit && mysqli_num_rows($qUnit) > 0):
                                        while ($unit = $qUnit->fetch_assoc()):
                                            $unit_id = $unit['id'];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($unit['nama_unit']) ?></strong>
                                            <?php if ($unit['kode_unit']): ?>
                                            <br><span class="badge badge-info"><?= $unit['kode_unit'] ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <?php
                                        $total_unit = 0;
                                        foreach ($jenisDocs as $jenis) {
                                            $qCount = $conn->query("
                                                SELECT COALESCE(SUM(nomor_terakhir), 0) as cnt 
                                                FROM master_nomor_dokumen 
                                                WHERE jenis_dokumen_id = {$jenis['id']} 
                                                  AND unit_kerja_id = $unit_id 
                                                  AND tahun = $tahun_filter
                                            ");
                                            $cnt = $qCount ? $qCount->fetch_assoc()['cnt'] : 0;
                                            $total_unit += $cnt;
                                            echo "<td class='text-center'>" . ($cnt > 0 ? "<strong>$cnt</strong>" : '-') . "</td>";
                                        }
                                        $grandTotal += $total_unit;
                                        ?>
                                        <td class='text-center bg-light'><strong><?= $total_unit ?></strong></td>
                                    </tr>
                                    <?php 
                                        endwhile;
                                    endif;
                                    ?>
                                    
                                    <tr class="bg-light">
                                        <td><strong>TOTAL KESELURUHAN</strong></td>
                                        <?php
                                        foreach ($jenisDocs as $jenis) {
                                            $qTotal = $conn->query("
                                                SELECT COALESCE(SUM(nomor_terakhir), 0) as cnt 
                                                FROM master_nomor_dokumen 
                                                WHERE jenis_dokumen_id = {$jenis['id']} 
                                                  AND tahun = $tahun_filter
                                            ");
                                            $total = $qTotal ? $qTotal->fetch_assoc()['cnt'] : 0;
                                            echo "<td class='text-center'><strong>" . ($total > 0 ? $total : '-') . "</strong></td>";
                                        }
                                        ?>
                                        <td class='text-center'><strong><?= $grandTotal ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                    </div>
                    <?php endif; ?>
                    
                </div><!-- End tab-content -->
                
            </div>
        </div>
    </div>
</div>

</div>
</section>
</div>

<?php include 'footer.php'; ?>

</div>
</div>

<!-- Modal Edit Jenis Dokumen -->
<?php
$qJenisModal = $conn->query("SELECT * FROM jenis_dokumen ORDER BY kode_dokumen");
if($qJenisModal && mysqli_num_rows($qJenisModal) > 0):
    while ($rowModal = $qJenisModal->fetch_assoc()):
?>
<div class="modal fade" id="modalEdit<?= $rowModal['id'] ?>" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Jenis Dokumen</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_jenis">
                    <input type="hidden" name="jenis_id" value="<?= $rowModal['id'] ?>">
                    
                    <div class="form-group">
                        <label>Kode Dokumen <span class="text-danger">*</span></label>
                        <input type="text" name="kode_dokumen" class="form-control" 
                               value="<?= $rowModal['kode_dokumen'] ?>" required maxlength="20">
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Dokumen <span class="text-danger">*</span></label>
                        <input type="text" name="nama_dokumen" class="form-control" 
                               value="<?= htmlspecialchars($rowModal['nama_dokumen']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="3"><?= htmlspecialchars($rowModal['deskripsi']) ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="aktif" class="custom-control-input" 
                                   id="aktif<?= $rowModal['id'] ?>" <?= $rowModal['aktif'] ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="aktif<?= $rowModal['id'] ?>">
                                Status Aktif
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php 
    endwhile;
endif;
?>

<!-- Modal Edit Kode Unit -->
<?php
$qUnitModal = $conn->query("SELECT * FROM unit_kerja ORDER BY nama_unit");
if($qUnitModal && mysqli_num_rows($qUnitModal) > 0):
    while ($rowUnit = $qUnitModal->fetch_assoc()):
?>
<div class="modal fade" id="modalUnit<?= $rowUnit['id'] ?>" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Set Kode Unit</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_kode_unit">
                    <input type="hidden" name="unit_id" value="<?= $rowUnit['id'] ?>">
                    
                    <div class="form-group">
                        <label>Nama Unit</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($rowUnit['nama_unit']) ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label>Kode Unit <span class="text-danger">*</span></label>
                        <input type="text" name="kode_unit" class="form-control" 
                               value="<?= $rowUnit['kode_unit'] ?>" required maxlength="20" 
                               placeholder="IT, KEP, UM, dll" style="text-transform: uppercase;">
                        <small class="form-text text-muted">
                            Maksimal 20 karakter, huruf besar tanpa spasi
                        </small>
                    </div>
                    
                    <div class="alert alert-light">
                        <small>
                            <strong>Contoh format nomor:</strong><br>
                            <code>001/SPO-<?= $rowUnit['kode_unit'] ?: 'KODE' ?>/RSPH/I/2026</code>
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php 
    endwhile;
endif;
?>

<script src="assets/modules/jquery.min.js"></script>
<script src="assets/modules/popper.js"></script>
<script src="assets/modules/bootstrap/js/bootstrap.min.js"></script>
<script src="assets/modules/nicescroll/jquery.nicescroll.min.js"></script>
<script src="assets/modules/moment.min.js"></script>
<script src="assets/js/stisla.js"></script>
<script src="assets/js/scripts.js"></script>
<script src="assets/js/custom.js"></script>
<script>
$(document).ready(function(){
    setTimeout(()=>$("#flashMsg").fadeOut("slow"),3000);
    
    // Auto uppercase untuk input kode
    $('input[name="kode_dokumen"], input[name="kode_unit"]').on('input', function() {
        this.value = this.value.toUpperCase();
    });
});
</script>
</body>
</html>