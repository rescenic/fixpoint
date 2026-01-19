<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    echo "<script>alert('Anda belum login.'); window.location.href='login.php';</script>";
    exit;
}

$current_file = basename(__FILE__);

// ====== CEK AKSES MENU ======
$query = "SELECT 1 FROM akses_menu 
          JOIN menu ON akses_menu.menu_id = menu.id 
          WHERE akses_menu.user_id = ? AND menu.file_menu = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("is", $user_id, $current_file);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows == 0) {
    echo "<script>alert('Anda tidak memiliki akses.');window.location.href='dashboard.php';</script>";
    exit;
}

// ====== GET USER DATA ======
$qUser = $conn->query("SELECT nama, jabatan, unit_kerja FROM users WHERE id='$user_id'");
$userData = $qUser->fetch_assoc();

$nama       = $userData['nama'] ?? "-";
$jabatan    = $userData['jabatan'] ?? "-";
$unit       = $userData['unit_kerja'] ?? "-";

// ====== SIMPAN DATA ======
if (isset($_POST['simpan'])) {
    $kronologi = trim($_POST['kronologi']);
    $data_terkait = trim($_POST['data_terkait']);
    $alasan = trim($_POST['alasan']);

    if (empty($kronologi) || empty($data_terkait) || empty($alasan)) {
        $_SESSION['flash_message'] = "Semua field wajib diisi!";
        $_SESSION['flash_type'] = "danger";
    } else {

        // === Generate Nomor Surat ===
        $romawi = [
            1 => "I", 2 => "II", 3 => "III", 4 => "IV", 
            5 => "V", 6 => "VI", 7 => "VII", 8 => "VIII", 
            9 => "IX", 10 => "X", 11 => "XI", 12 => "XII"
        ];

        $bulan = date('n');
        $tahun = date('Y');

        // Ambil nomor terakhir di bulan dan tahun ini
        $qLast = $conn->query("
            SELECT nomor_surat FROM permintaan_hapus_data 
            WHERE YEAR(tanggal) = $tahun AND MONTH(tanggal) = $bulan
            ORDER BY id DESC LIMIT 1
        ");
        $lastNum = 1;

        if ($qLast && $qLast->num_rows > 0) {
            $lastData = $qLast->fetch_assoc();
            preg_match('/(\d+)\//', $lastData['nomor_surat'], $match);
            if (isset($match[1])) {
                $lastNum = intval($match[1]) + 1;
            }
        }

        // Format nomor surat
        $nomor_surat = str_pad($lastNum, 4, '0', STR_PAD_LEFT) . "/PHDS/RSPH/" . $romawi[$bulan] . "/$tahun";

        // Simpan data
        $stmt = $conn->prepare("
            INSERT INTO permintaan_hapus_data 
            (user_id, nama, jabatan, unit_kerja, nomor_surat, kronologi, data_terkait, alasan, status, tanggal) 
            VALUES (?,?,?,?,?,?,?,?,'Menunggu', NOW())
        ");
        $stmt->bind_param("isssssss", $user_id, $nama, $jabatan, $unit, $nomor_surat, $kronologi, $data_terkait, $alasan);

        if ($stmt->execute()) {
            $_SESSION['flash_message'] = "Permintaan berhasil terkirim dengan nomor: <strong>$nomor_surat</strong>";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Terjadi kesalahan: " . $conn->error;
            $_SESSION['flash_type'] = "danger";
        }
    }

    echo "<script>location.href='hapus_data.php?tab=data';</script>";
    exit;
}

// ====== HAPUS DRAFT ======
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    
    // Cek apakah data milik user dan statusnya masih Menunggu
    $check = $conn->query("SELECT status FROM permintaan_hapus_data WHERE id='$id' AND user_id='$user_id'");
    if ($check && $check->num_rows > 0) {
        $data = $check->fetch_assoc();
        if ($data['status'] == 'Menunggu') {
            $conn->query("DELETE FROM permintaan_hapus_data WHERE id='$id'");
            $_SESSION['flash_message'] = "Draft berhasil dihapus.";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Tidak bisa menghapus permintaan yang sudah diproses.";
            $_SESSION['flash_type'] = "warning";
        }
    }
    
    echo "<script>location.href='hapus_data.php?tab=data';</script>";
    exit;
}

// Aktif tab
$activeTab = $_GET['tab'] ?? 'input';

// ====== LOAD DATA TABLE ======
$data_query = $conn->query("SELECT * FROM permintaan_hapus_data WHERE user_id='$user_id' ORDER BY id DESC");

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Permintaan Hapus Data SIMRS</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css" />
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css" />
<link rel="stylesheet" href="assets/css/style.css" />
<link rel="stylesheet" href="assets/css/components.css" />

<style>
.flash-center {
    position: fixed;
    top: 20%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 9999;
    min-width: 300px;
    max-width: 90%;
    text-align: center;
    padding: 15px;
    border-radius: 8px;
    font-weight: 500;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}

.info-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.info-card h5 {
    color: white;
    font-weight: 700;
    margin-bottom: 15px;
}

.info-card ul {
    margin-bottom: 0;
}

.info-card li {
    margin-bottom: 8px;
}

.form-group label {
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.form-control {
    font-size: 14px;
}

.required {
    color: red;
}

.table-status span {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.timeline-item {
    padding: 10px;
    background: #f8f9fa;
    border-left: 3px solid #6777ef;
    margin-bottom: 10px;
    border-radius: 5px;
}

.timeline-item small {
    color: #666;
    font-size: 11px;
}

/* Modal fix */
.modal-backdrop {
    z-index: 1040 !important;
}

.modal {
    z-index: 1050 !important;
}

.modal-dialog-centered {
    display: flex;
    align-items: center;
    min-height: calc(100% - 3.5rem);
}

.status-detail {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
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

<!-- FLASH MESSAGE -->
<?php if (isset($_SESSION['flash_message'])): 
    $flashType = $_SESSION['flash_type'] ?? 'info';
?>
<div class="alert alert-<?= $flashType ?> flash-center" id="flashMsg">
    <i class="fas fa-<?= $flashType=='success'?'check-circle':($flashType=='danger'?'exclamation-circle':'info-circle') ?>"></i>
    <?= $_SESSION['flash_message']; ?>
</div>
<script>
setTimeout(() => { document.getElementById("flashMsg").style.display="none"; }, 3000);
</script>
<?php 
    unset($_SESSION['flash_message']); 
    unset($_SESSION['flash_type']); 
endif; 
?>

<div class="card">
<div class="card-header">
    <h4><i class="fas fa-trash-alt"></i> Form Pengajuan Hapus Data SIMRS</h4>
    <div class="card-header-action">
        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#prosedurModal">
            <i class="fas fa-question-circle"></i> Panduan
        </button>
    </div>
</div>

<div class="card-body">

<ul class="nav nav-tabs" id="izinTab">
    <li class="nav-item">
        <a class="nav-link <?= ($activeTab=='input')?'active':'' ?>" data-toggle="tab" href="#input">
            <i class="fas fa-edit"></i> Input Permintaan
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($activeTab=='data')?'active':'' ?>" data-toggle="tab" href="#data">
            <i class="fas fa-list"></i> Riwayat Permintaan
        </a>
    </li>
</ul>

<div class="tab-content mt-3">

<!-- TAB INPUT -->
<div class="tab-pane fade <?= ($activeTab=='input')?'show active':'' ?>" id="input">

<div class="info-card">
    <h5><i class="fas fa-info-circle"></i> Informasi Penting</h5>
    <ul>
        <li>Permintaan akan diproses oleh Administrator SIMRS</li>
        <li>Pastikan data yang Anda isi sudah benar dan lengkap</li>
        <li>Status permintaan dapat Anda cek di tab <strong>"Riwayat Permintaan"</strong></li>
    </ul>
</div>

<form method="POST">

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label><i class="fas fa-user"></i> Nama Pemohon</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($nama) ?>" readonly>
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-group">
            <label><i class="fas fa-briefcase"></i> Jabatan</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($jabatan) ?>" readonly>
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-group">
            <label><i class="fas fa-building"></i> Unit Kerja</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($unit) ?>" readonly>
        </div>
    </div>
</div>

<hr>

<div class="form-group">
    <label><i class="fas fa-database"></i> Data yang Akan Dihapus <span class="required">*</span></label>
    <input type="text" name="data_terkait" class="form-control" 
           placeholder="Contoh: Data pasien No. RM 123456, Tanggal 15 Januari 2026" required>
    <small class="form-text text-muted">Sebutkan secara spesifik data apa yang akan dihapus (No. RM, Tanggal, dll)</small>
</div>

<div class="form-group">
    <label><i class="fas fa-clipboard-list"></i> Alasan Penghapusan <span class="required">*</span></label>
    <select name="alasan" class="form-control" required>
        <option value="">-- Pilih Alasan --</option>
        <option value="Data Ganda">Data Ganda</option>
        <option value="Input Salah">Input Salah</option>
        <option value="Pasien Salah Identitas">Pasien Salah Identitas</option>
        <option value="Permintaan Pasien">Permintaan Pasien</option>
        <option value="Lainnya">Lainnya</option>
    </select>
</div>

<div class="form-group">
    <label><i class="fas fa-file-alt"></i> Kronologi Lengkap <span class="required">*</span></label>
    <textarea name="kronologi" rows="6" class="form-control" 
    placeholder="Jelaskan secara detail kronologi kejadian dan alasan mengapa data tersebut harus dihapus...

Contoh:
Pada tanggal 15 Januari 2026 pukul 10:00 WIB, saya telah melakukan input data pasien dengan No. RM 123456 atas nama Fulan. Namun setelah dilakukan pengecekan ulang, ternyata data tersebut adalah data ganda karena pasien yang sama sudah terdaftar sebelumnya dengan No. RM 123455.

Data ganda ini terjadi karena kesalahan sistem yang tidak mendeteksi duplikasi pada saat input. Oleh karena itu, mohon untuk dapat menghapus data dengan No. RM 123456 tersebut." required></textarea>
    <small class="form-text text-muted">Jelaskan secara detail dan kronologis agar permintaan dapat diproses dengan cepat</small>
</div>

<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> <strong>Perhatian:</strong>
    <ul class="mb-0 mt-2">
        <li>Pastikan semua informasi yang Anda berikan adalah benar</li>
        <li>Penghapusan data bersifat permanen dan tidak dapat dikembalikan</li>
        <li>Proses verifikasi memerlukan waktu 1-3 hari kerja</li>
    </ul>
</div>

<div class="text-right">
    <button type="reset" class="btn btn-secondary">
        <i class="fas fa-redo"></i> Reset
    </button>
    <button type="submit" name="simpan" class="btn btn-primary">
        <i class="fas fa-paper-plane"></i> Kirim Permintaan
    </button>
</div>

</form>
</div>

<!-- TAB DATA -->
<div class="tab-pane fade <?= ($activeTab=='data')?'show active':'' ?>" id="data">
<div class="table-responsive">
<table class="table table-bordered table-hover table-sm">
<thead class="thead-light text-center">
<tr>
    <th width="40">No</th>
    <th width="150">No. Surat</th>
    <th width="100">Tanggal</th>
    <th>Data Terkait</th>
    <th>Alasan</th>
    <th width="120">Status</th>
    <th width="150">Aksi</th>
</tr>
</thead>
<tbody>

<?php 
$no = 1; 
if (mysqli_num_rows($data_query) > 0):
    while($row = $data_query->fetch_assoc()): 
?>
<tr>
    <td class="text-center"><?= $no++; ?></td>
    <td><strong><?= htmlspecialchars($row['nomor_surat']); ?></strong></td>
    <td class="text-center"><?= date('d/m/Y', strtotime($row['tanggal'])); ?></td>
    <td><?= htmlspecialchars($row['data_terkait'] ?? '-'); ?></td>
    <td><?= htmlspecialchars($row['alasan'] ?? '-'); ?></td>
    <td class="text-center table-status">
    <?php 
    $statusConfig = [
        "Menunggu" => ["badge-warning", "hourglass-half"],
        "Diproses" => ["badge-primary", "cog"],
        "Disetujui" => ["badge-info", "check"],
        "Ditolak"  => ["badge-danger", "times"],
        "Selesai"  => ["badge-success", "check-circle"]
    ];
    $config = $statusConfig[$row['status']] ?? ["badge-secondary", "question"];
    ?>
    <span class="badge <?= $config[0] ?>">
        <i class="fas fa-<?= $config[1] ?>"></i> <?= $row['status']; ?>
    </span>
    </td>
    <td class="text-center">
       
        <a href="print_hapus_data.php?id=<?= $row['id']; ?>" 
           target="_blank" 
           class="btn btn-sm btn-secondary"
           title="Cetak">
            <i class="fas fa-print"></i>
        </a>
        <?php if ($row['status'] == 'Menunggu'): ?>
        <a href="?hapus=<?= $row['id']; ?>" 
           onclick="return confirm('Yakin ingin menghapus draft ini?')" 
           class="btn btn-sm btn-danger"
           title="Hapus">
            <i class="fas fa-trash"></i>
        </a>
        <?php endif; ?>
    </td>
</tr>


        
        <?php if ($row['updated_status_at']): ?>
        <div class="status-detail">
          <h6><i class="fas fa-history"></i> Riwayat Pemrosesan</h6>
          <div class="timeline-item">
            <strong>Diperbarui pada:</strong> <?= date('d F Y, H:i', strtotime($row['updated_status_at'])); ?> WIB<br>
            <small>oleh: <?= htmlspecialchars($row['updated_by'] ?? 'Administrator'); ?></small>
          </div>
          <?php if (!empty($row['catatan_admin'])): ?>
          <div class="alert alert-info mt-2">
            <strong><i class="fas fa-comment"></i> Catatan Admin:</strong><br>
            <?= nl2br(htmlspecialchars($row['catatan_admin'])); ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    
    </div>
  </div>
</div>

<?php 
    endwhile;
else:
?>
<tr>
    <td colspan="7" class="text-center">
        <i class="fas fa-inbox fa-3x text-muted mb-3"></i><br>
        Belum ada permintaan yang diajukan
    </td>
</tr>
<?php endif; ?>

</tbody>
</table>
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

<!-- MODAL PROSEDUR -->
<div class="modal fade" id="prosedurModal" tabindex="-1" role="dialog">
<div class="modal-dialog modal-dialog-centered" role="document">
<div class="modal-content">
<div class="modal-header bg-primary text-white">
    <h5 class="modal-title"><i class="fas fa-book"></i> Panduan Pengajuan Hapus Data</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<div class="modal-body">
<h6><strong>Langkah-langkah:</strong></h6>
<ol>
    <li>Isi form dengan data yang lengkap dan jelas</li>
    <li>Sebutkan secara spesifik data apa yang akan dihapus</li>
    <li>Jelaskan kronologi dan alasan dengan detail</li>
    <li>Klik tombol "Kirim Permintaan"</li>
    <li>Tunggu proses verifikasi oleh Admin SIMRS (1-3 hari kerja)</li>
    <li>Cek status di tab "Riwayat Permintaan"</li>
</ol>

<h6 class="mt-3"><strong>Status Permintaan:</strong></h6>
<ul>
    <li><span class="badge badge-warning">Menunggu</span> - Permintaan sedang dalam antrian</li>
    <li><span class="badge badge-primary">Diproses</span> - Admin sedang memeriksa</li>
    <li><span class="badge badge-info">Disetujui</span> - Permintaan disetujui, data akan segera dihapus</li>
    <li><span class="badge badge-danger">Ditolak</span> - Permintaan ditolak (lihat catatan admin)</li>
    <li><span class="badge badge-success">Selesai</span> - Data sudah berhasil dihapus</li>
</ul>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-dismiss="modal">
        <i class="fas fa-times"></i> Tutup
    </button>
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
</body>
</html>