<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    echo "<script>alert('Anda belum login.'); window.location.href='login.php';</script>";
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
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini.'); window.location.href='dashboard.php';</script>";
    exit;
}

// Ambil data perusahaan
$qPerusahaan = mysqli_query($conn, "SELECT * FROM perusahaan LIMIT 1");
$perusahaan = mysqli_fetch_assoc($qPerusahaan);

if (!$perusahaan) {
    echo "<script>alert('Data perusahaan belum dikonfigurasi. Silakan hubungi admin.'); window.location.href='dashboard.php';</script>";
    exit;
}

// Perbaiki path logo jika masih menggunakan dist/
if (!empty($perusahaan['logo']) && strpos($perusahaan['logo'], 'dist/') === 0) {
    $perusahaan['logo'] = str_replace('dist/', '', $perusahaan['logo']);
}

// Handle nonaktifkan stampel
if (isset($_POST['nonaktifkan_stampel'])) {
    $stampel_id = $_POST['stampel_id'] ?? 0;
    
    $update = $conn->prepare("UPDATE e_stampel SET status='nonaktif' WHERE id=?");
    $update->bind_param("i", $stampel_id);
    
    if ($update->execute()) {
        $_SESSION['flash_message'] = "E-Stampel berhasil dinonaktifkan.";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Gagal menonaktifkan E-Stampel.";
        $_SESSION['flash_type'] = "danger";
    }
    
    header("Location: buat_stampel.php");
    exit;
}

// Handle generate stampel
if (isset($_POST['generate_stampel'])) {
    // Cek apakah sudah ada E-Stampel aktif
    $cek = $conn->prepare("SELECT id FROM e_stampel WHERE status='aktif'");
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        $_SESSION['flash_message'] = "E-Stampel sudah aktif. Nonaktifkan terlebih dahulu jika ingin membuat yang baru.";
        $_SESSION['flash_type'] = "warning";
        header("Location: buat_stampel.php");
        exit;
    }

    // Nonaktifkan semua E-Stampel lama (sebagai backup safety)
    mysqli_query($conn, "UPDATE e_stampel SET status='nonaktif' WHERE status='aktif'");

    // Generate token unik
    $token = bin2hex(random_bytes(32));
    
    // Insert E-Stampel baru
    $ins = $conn->prepare("
        INSERT INTO e_stampel
        (nama_perusahaan, alamat, kota, provinsi, kontak, email, token, dibuat_oleh, created_at, status)
        VALUES (?,?,?,?,?,?,?,?,NOW(),'aktif')
    ");
    $ins->bind_param(
        "sssssssi",
        $perusahaan['nama_perusahaan'],
        $perusahaan['alamat'],
        $perusahaan['kota'],
        $perusahaan['provinsi'],
        $perusahaan['kontak'],
        $perusahaan['email'],
        $token,
        $user_id
    );
    
    if ($ins->execute()) {
        $_SESSION['flash_message'] = "E-Stampel berhasil dibuat! Silakan download QR Code.";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Gagal membuat E-Stampel: " . $conn->error;
        $_SESSION['flash_type'] = "danger";
    }

    header("Location: buat_stampel.php");
    exit;
}

// Ambil data E-Stampel
$qStampel = mysqli_query($conn, "SELECT * FROM e_stampel ORDER BY created_at DESC");

// E-Stampel Aktif
$stampel_aktif = null;
// Riwayat E-Stampel
$riwayat_stampel = [];

while ($row = mysqli_fetch_assoc($qStampel)) {
    if ($row['status'] === 'aktif' && !$stampel_aktif) {
        $stampel_aktif = $row;
    } else {
        $riwayat_stampel[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>E-Stampel - Sistem Rumah Sakit</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<style>
.stampel-card {
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    overflow: hidden;
}

.stampel-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 25px;
}

.qr-container {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    display: inline-block;
}

.qr-code-img {
    border: 3px solid #28a745;
    border-radius: 10px;
    padding: 10px;
    background: white;
}

.info-badge {
    background: rgba(255,255,255,0.2);
    padding: 8px 15px;
    border-radius: 20px;
    display: inline-block;
    margin: 5px;
}

.stampel-status {
    position: absolute;
    top: 20px;
    right: 20px;
}

.download-btn {
    padding: 12px 30px;
    font-size: 16px;
    border-radius: 25px;
    transition: all 0.3s ease;
}

.download-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.security-note {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    border-left: 4px solid #28a745;
    padding: 15px;
    border-radius: 8px;
    margin-top: 20px;
}

.riwayat-card {
    border-left: 4px solid #6c757d;
    margin-bottom: 15px;
}

.riwayat-card.nonaktif {
    opacity: 0.7;
}

.perusahaan-info {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
}

.perusahaan-info table th {
    padding: 10px 12px;
    vertical-align: middle;
}

.perusahaan-info table td {
    padding: 10px 12px;
    vertical-align: middle;
}

@media print {
    .no-print { display: none !important; }
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
    <h1><i class="fas fa-stamp"></i> E-Stampel (Stempel Elektronik)</h1>
</div>

<div class="section-body">

<!-- Flash Message -->
<?php if(isset($_SESSION['flash_message'])): ?>
<div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> alert-dismissible fade show" role="alert">
    <i class="fas fa-info-circle"></i> <?= htmlspecialchars($_SESSION['flash_message']) ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<?php 
unset($_SESSION['flash_message']);
unset($_SESSION['flash_type']);
endif; 
?>

<!-- Info Perusahaan -->
<div class="card mb-4">
<div class="card-header bg-info text-white">
    <h4 class="text-white"><i class="fas fa-building"></i> Informasi Perusahaan/Instansi</h4>
</div>
<div class="card-body perusahaan-info">
    <div class="row">
        <div class="col-md-6">
            <table class="table table-sm">
                <tr>
                    <th width="40%" class="border-top-0"><i class="fas fa-building text-primary"></i> Nama Perusahaan</th>
                    <td class="border-top-0"><strong><?= htmlspecialchars($perusahaan['nama_perusahaan']) ?></strong></td>
                </tr>
                <tr>
                    <th><i class="fas fa-map-marker-alt text-danger"></i> Alamat</th>
                    <td><?= htmlspecialchars($perusahaan['alamat']) ?></td>
                </tr>
                <tr>
                    <th><i class="fas fa-city text-info"></i> Kota/Provinsi</th>
                    <td><?= htmlspecialchars($perusahaan['kota']) ?>, <?= htmlspecialchars($perusahaan['provinsi']) ?></td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table table-sm">
                <tr>
                    <th width="40%" class="border-top-0"><i class="fas fa-phone text-success"></i> Kontak</th>
                    <td class="border-top-0"><?= htmlspecialchars($perusahaan['kontak']) ?></td>
                </tr>
                <tr>
                    <th><i class="fas fa-envelope text-warning"></i> Email</th>
                    <td><?= htmlspecialchars($perusahaan['email']) ?></td>
                </tr>
                <?php if (!empty($perusahaan['logo'])): ?>
                <tr>
                    <th><i class="fas fa-image text-primary"></i> Logo</th>
                    <td>
                        <img src="<?= htmlspecialchars($perusahaan['logo']) ?>" 
                             alt="Logo" 
                             style="max-height: 50px;"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                        <span class="text-muted" style="display:none;">File tidak ditemukan</span>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
</div>

<?php if (!$stampel_aktif): ?>

<!-- Belum ada E-Stampel -->
<div class="card stampel-card">
<div class="card-body text-center py-5">
    <i class="fas fa-stamp fa-5x text-muted mb-4"></i>
    <h4>Belum Ada E-Stampel Aktif</h4>
    <p class="text-muted mb-4">
        E-Stampel adalah stempel elektronik untuk perusahaan/instansi yang dapat digunakan 
        untuk memvalidasi dokumen resmi dengan QR Code terverifikasi.
    </p>
    
    <form method="POST" onsubmit="return confirm('Generate E-Stampel untuk perusahaan ini?');">
        <div class="mb-3">
            <div class="alert alert-info d-inline-block">
                <i class="fas fa-info-circle"></i> 
                E-Stampel akan dibuat berdasarkan data perusahaan di atas
            </div>
        </div>
        <button type="submit" name="generate_stampel" class="btn btn-success btn-lg">
            <i class="fas fa-plus-circle"></i> Buat E-Stampel Sekarang
        </button>
    </form>
</div>
</div>

<?php else: ?>

<!-- E-Stampel Aktif -->
<div class="card stampel-card">
<div class="stampel-header position-relative">
    <div class="stampel-status">
        <span class="badge badge-light badge-lg">
            <i class="fas fa-check-circle"></i> AKTIF
        </span>
    </div>
    
    <h4 class="mb-3"><i class="fas fa-stamp"></i> E-Stampel Perusahaan</h4>
    
    <div class="info-badge">
        <i class="fas fa-calendar-alt"></i> 
        Dibuat: <?= date('d F Y, H:i', strtotime($stampel_aktif['created_at'])) ?> WIB
    </div>
    
    <?php if (!empty($stampel_aktif['file_hash'])): ?>
    <div class="info-badge">
        <i class="fas fa-shield-alt"></i> 
        Dilindungi Hash
    </div>
    <?php endif; ?>
</div>

<div class="card-body">
    <div class="row">
        
        <!-- Info Perusahaan di E-Stampel -->
        <div class="col-md-6">
            <h5 class="mb-3"><i class="fas fa-building"></i> Informasi E-Stampel</h5>
            <table class="table table-bordered table-sm">
                <tr>
                    <th width="40%"><i class="fas fa-building text-primary"></i> Perusahaan</th>
                    <td><strong><?= htmlspecialchars($stampel_aktif['nama_perusahaan']) ?></strong></td>
                </tr>
                <tr>
                    <th><i class="fas fa-map-marker-alt text-danger"></i> Alamat</th>
                    <td><?= htmlspecialchars($stampel_aktif['alamat']) ?></td>
                </tr>
                <tr>
                    <th><i class="fas fa-city text-info"></i> Kota</th>
                    <td><?= htmlspecialchars($stampel_aktif['kota']) ?>, <?= htmlspecialchars($stampel_aktif['provinsi']) ?></td>
                </tr>
                <tr>
                    <th><i class="fas fa-phone text-success"></i> Kontak</th>
                    <td><?= htmlspecialchars($stampel_aktif['kontak']) ?></td>
                </tr>
                <tr>
                    <th><i class="fas fa-envelope text-warning"></i> Email</th>
                    <td><?= htmlspecialchars($stampel_aktif['email']) ?></td>
                </tr>
                <tr>
                    <th><i class="fas fa-key text-danger"></i> Token</th>
                    <td>
                        <code style="font-size: 10px; word-break: break-all;">
                            <?= htmlspecialchars(substr($stampel_aktif['token'], 0, 32)) ?>...
                        </code>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- QR Code -->
        <div class="col-md-6 text-center">
            <h5 class="mb-3"><i class="fas fa-qrcode"></i> QR Code E-Stampel</h5>
            
            <div class="qr-container">
                <img src="generate_qr_stampel.php?token=<?= $stampel_aktif['token'] ?>" 
                     class="qr-code-img" 
                     width="200" 
                     alt="QR Code E-Stampel">
                <p class="mt-3 mb-0 text-muted">
                    <small>
                        <i class="fas fa-shield-alt"></i> 
                        E-Stampel Terverifikasi
                    </small>
                </p>
            </div>
            
            <div class="mt-4">
                <a href="generate_qr_stampel.php?token=<?= $stampel_aktif['token'] ?>&download=1"
                   class="btn btn-success download-btn">
                    <i class="fas fa-download"></i> Download QR Code
                </a>
            </div>
        </div>
    </div>
    
    <!-- Fitur Keamanan -->
    <div class="security-note mt-4">
        <h6><i class="fas fa-shield-alt"></i> Fitur Keamanan E-Stampel:</h6>
        <div class="row">
            <div class="col-md-6">
                <ul class="mb-0">
                    <li><i class="fas fa-check text-success"></i> Token unik 64 karakter</li>
                    <li><i class="fas fa-check text-success"></i> Terenkripsi SHA-256</li>
                    <li><i class="fas fa-check text-success"></i> Validasi identitas perusahaan</li>
                </ul>
            </div>
            <div class="col-md-6">
                <ul class="mb-0">
                    <li><i class="fas fa-check text-success"></i> QR Code terverifikasi</li>
                    <li><i class="fas fa-check text-success"></i> Deteksi modifikasi file</li>
                    <li><i class="fas fa-check text-success"></i> Timestamp tercatat</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Tombol Aksi -->
    <div class="mt-4 text-center no-print">
        <hr>
        <p class="text-muted mb-3">
            <i class="fas fa-info-circle"></i> 
            Gunakan QR Code ini untuk membubuhkan stempel elektronik pada dokumen
        </p>
        
        <form method="POST" style="display: inline-block;" 
              onsubmit="return confirm('Yakin ingin menonaktifkan E-Stampel ini? E-Stampel yang dinonaktifkan tidak dapat digunakan lagi.');">
            <input type="hidden" name="stampel_id" value="<?= $stampel_aktif['id'] ?>">
            <button type="submit" name="nonaktifkan_stampel" class="btn btn-danger">
                <i class="fas fa-ban"></i> Nonaktifkan E-Stampel
            </button>
        </form>
    </div>
</div>
</div>

<?php endif; ?>

<!-- Riwayat E-Stampel -->
<?php if (count($riwayat_stampel) > 0): ?>
<div class="card mt-4">
<div class="card-header">
    <h4><i class="fas fa-history"></i> Riwayat E-Stampel</h4>
</div>
<div class="card-body">
    <?php foreach ($riwayat_stampel as $riwayat): ?>
    <div class="card riwayat-card <?= $riwayat['status'] === 'nonaktif' ? 'nonaktif' : '' ?>">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h6 class="mb-2">
                        <i class="fas fa-stamp"></i> 
                        <?= htmlspecialchars($riwayat['nama_perusahaan']) ?>
                    </h6>
                    <p class="text-muted mb-0">
                        <small>
                            <i class="fas fa-calendar"></i> 
                            Dibuat: <?= date('d F Y, H:i', strtotime($riwayat['created_at'])) ?> WIB
                            | <i class="fas fa-city"></i> <?= htmlspecialchars($riwayat['kota']) ?>
                            <?php if (!empty($riwayat['file_hash'])): ?>
                            | <i class="fas fa-shield-alt text-success"></i> Hash: <?= substr($riwayat['file_hash'], 0, 16) ?>...
                            <?php endif; ?>
                        </small>
                    </p>
                </div>
                <div class="col-md-4 text-right">
                    <?php if ($riwayat['status'] === 'aktif'): ?>
                        <span class="badge badge-success badge-lg">
                            <i class="fas fa-check-circle"></i> AKTIF
                        </span>
                    <?php else: ?>
                        <span class="badge badge-secondary badge-lg">
                            <i class="fas fa-ban"></i> NONAKTIF
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<!-- Panduan -->
<div class="card mt-4">
<div class="card-header bg-primary text-white">
    <h4 class="text-white"><i class="fas fa-question-circle"></i> Cara Menggunakan E-Stampel</h4>
</div>
<div class="card-body">
    <div class="row">
        <div class="col-md-6">
            <h6><i class="fas fa-check-circle text-success"></i> Langkah-langkah:</h6>
            <ol>
                <li>Generate E-Stampel jika belum ada</li>
                <li>Buka menu <strong>"Bubuhkan E-Stampel"</strong></li>
                <li>Upload dokumen (PDF/Word) yang akan distempel</li>
                <li>Pilih posisi stempel di dokumen</li>
                <li>Klik "Bubuhkan Stampel" - QR Code akan otomatis masuk ke dokumen</li>
                <li>Download dokumen yang sudah distempel</li>
            </ol>
        </div>
        
        <div class="col-md-6">
            <h6><i class="fas fa-lightbulb text-warning"></i> Perbedaan E-Stampel vs TTE:</h6>
            <table class="table table-sm table-bordered">
                <tr>
                    <th width="30%">E-Stampel</th>
                    <td>Untuk <strong>perusahaan/instansi</strong>, menampilkan data organisasi</td>
                </tr>
                <tr>
                    <th>TTE</th>
                    <td>Untuk <strong>individu/karyawan</strong>, menampilkan data pribadi</td>
                </tr>
            </table>
            
            <h6 class="mt-3"><i class="fas fa-shield-alt text-info"></i> Kegunaan:</h6>
            <ul>
                <li>Validasi dokumen resmi perusahaan</li>
                <li>Surat resmi keluar dari instansi</li>
                <li>Dokumen legal yang memerlukan stempel</li>
                <li>Pengganti stempel fisik yang lebih aman</li>
            </ul>
        </div>
    </div>
    
    <div class="alert alert-info mt-3 mb-0">
        <i class="fas fa-info-circle"></i> 
        <strong>Catatan:</strong> E-Stampel bersifat unik untuk satu perusahaan/instansi. 
        Hanya boleh ada satu E-Stampel aktif dalam satu waktu untuk menjaga integritas validasi.
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
// Auto hide flash message
setTimeout(function() {
    $('.alert-dismissible').fadeOut('slow');
}, 5000);
</script>

</body>
</html>