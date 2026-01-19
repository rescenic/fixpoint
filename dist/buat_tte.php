<?php
session_start();
include 'koneksi.php';
require_once 'tte_hash_helper.php';
date_default_timezone_set('Asia/Jakarta');


$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    echo "<script>alert('Anda belum login.'); window.location.href='login.php';</script>";
    exit;
}


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


if (isset($_POST['nonaktifkan_tte'])) {
    $tte_id = $_POST['tte_id'] ?? 0;
    
    $update = $conn->prepare("UPDATE tte_user SET status='nonaktif' WHERE id=? AND user_id=?");
    $update->bind_param("ii", $tte_id, $user_id);
    
    if ($update->execute()) {
        $_SESSION['flash_message'] = "TTE berhasil dinonaktifkan.";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Gagal menonaktifkan TTE.";
        $_SESSION['flash_type'] = "danger";
    }
    
    header("Location: buat_tte.php");
    exit;
}


if (isset($_POST['generate_tte'])) {

    // Cek apakah sudah ada TTE aktif
    $cek = $conn->prepare("SELECT id FROM tte_user WHERE user_id=? AND status='aktif'");
    $cek->bind_param("i", $user_id);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        $_SESSION['flash_message'] = "TTE Anda sudah aktif.";
        $_SESSION['flash_type'] = "warning";
        header("Location: buat_tte.php");
        exit;
    }


    $qUser = $conn->prepare("
        SELECT 
            u.nama, 
            u.nik as nik_karyawan,
            u.jabatan, 
            u.unit_kerja,
            ip.no_ktp
        FROM users u
        LEFT JOIN informasi_pribadi ip ON u.id = ip.user_id
        WHERE u.id = ?
    ");
    $qUser->bind_param("i", $user_id);
    $qUser->execute();
    $user = $qUser->get_result()->fetch_assoc();

    if (!$user) {
        $_SESSION['flash_message'] = "Data user tidak ditemukan.";
        $_SESSION['flash_type'] = "danger";
        header("Location: buat_tte.php");
        exit;
    }
    

    if (empty($user['nik_karyawan'])) {
        $_SESSION['flash_message'] = "NIK karyawan tidak ditemukan. Silakan hubungi admin.";
        $_SESSION['flash_type'] = "warning";
        header("Location: buat_tte.php");
        exit;
    }


    $token = bin2hex(random_bytes(32));

    // Insert TTE baru dengan NIK karyawan DAN nomor KTP
    $ins = $conn->prepare("
        INSERT INTO tte_user
        (user_id, nama, nik, no_ktp, jabatan, unit, token, created_at, status)
        VALUES (?,?,?,?,?,?,?,NOW(),'aktif')
    ");
    $ins->bind_param(
        "issssss",
        $user_id,
        $user['nama'],
        $user['nik_karyawan'],      
        $user['no_ktp'],           
        $user['jabatan'],
        $user['unit_kerja'],
        $token
    );
    
    if ($ins->execute()) {
        $_SESSION['flash_message'] = "TTE berhasil dibuat! Silakan download QR Code Anda.";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Gagal membuat TTE: " . $conn->error;
        $_SESSION['flash_type'] = "danger";
    }

    header("Location: buat_tte.php");
    exit;
}


$qTte = $conn->prepare("SELECT * FROM tte_user WHERE user_id=? ORDER BY created_at DESC");
$qTte->bind_param("i", $user_id);
$qTte->execute();
$dataTte = $qTte->get_result();

// TTE Aktif
$tte_aktif = null;
// Riwayat TTE
$riwayat_tte = [];

while ($row = $dataTte->fetch_assoc()) {
    if ($row['status'] === 'aktif' && !$tte_aktif) {
        $tte_aktif = $row;
    } else {
        $riwayat_tte[] = $row;
    }
}

// Ambil data user untuk modal konfirmasi
$qUserData = $conn->prepare("
    SELECT 
        u.nama, 
        u.nik as nik_karyawan,
        u.jabatan, 
        u.unit_kerja,
        ip.no_ktp
    FROM users u
    LEFT JOIN informasi_pribadi ip ON u.id = ip.user_id
    WHERE u.id = ?
");
$qUserData->bind_param("i", $user_id);
$qUserData->execute();
$userData = $qUserData->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TTE Saya - FixPoint</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.tte-card {
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    overflow: hidden;
}

.tte-header {
    background: linear-gradient(135deg, #6777ef 0%, #4169e1 100%);
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
    border: 3px solid #6777ef;
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

.tte-status {
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
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    border-left: 4px solid #ffc107;
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
    <h1><i class="fas fa-signature"></i> Tanda Tangan Elektronik (TTE) Saya</h1>
    
</div>

<div class="section-body">


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

<?php if (!$tte_aktif): ?>


<div class="card">
<div class="card-header">
    <h4><i class="fas fa-plus-circle"></i> Generate TTE Baru</h4>
</div>
<div class="card-body">
    <div class="alert alert-info">
        <h5><i class="fas fa-info-circle"></i> Apa itu TTE?</h5>
        <p class="mb-0">
            Tanda Tangan Elektronik (TTE) adalah tanda tangan digital yang sah secara hukum. 
            Dengan TTE, Anda dapat menandatangani dokumen secara elektronik tanpa perlu tanda tangan basah.
        </p>
    </div>
    
    <div class="text-center">
        <button type="button" class="btn btn-primary btn-lg" onclick="showKonfirmasiTTE()">
            <i class="fas fa-plus-circle"></i> Buat TTE Sekarang
        </button>
    </div>
</div>
</div>

<?php else: ?>


<div class="card tte-card">
<div class="tte-header position-relative">
    <div class="tte-status">
        <span class="badge badge-success badge-lg">
            <i class="fas fa-check-circle"></i> AKTIF
        </span>
    </div>
    
    <h4 class="mb-3"><i class="fas fa-signature"></i> TTE Anda</h4>
    
    <div class="info-badge">
        <i class="fas fa-calendar-alt"></i> 
        Dibuat: <?= date('d F Y, H:i', strtotime($tte_aktif['created_at'])) ?> WIB
    </div>
    
    <?php if (!empty($tte_aktif['file_hash'])): ?>
    <div class="info-badge">
        <i class="fas fa-shield-alt"></i> 
        Dilindungi Hash
    </div>
    <?php endif; ?>
</div>

<div class="card-body">
    <div class="row">
        
        <div class="col-md-6">
            <h5 class="mb-3"><i class="fas fa-user-circle"></i> Informasi Pemilik TTE</h5>
            <table class="table table-bordered table-sm">
                <tr>
                    <th width="40%"><i class="fas fa-user text-primary"></i> Nama</th>
                    <td><strong><?= htmlspecialchars($tte_aktif['nama']) ?></strong></td>
                </tr>
                <tr>
                    <th><i class="fas fa-id-badge text-info"></i> NIK Karyawan</th>
                    <td><?= htmlspecialchars($tte_aktif['nik']) ?></td>
                </tr>
                <?php if (!empty($tte_aktif['no_ktp'])): ?>
                <tr>
                    <th><i class="fas fa-id-card text-success"></i> No. KTP</th>
                    <td><?= htmlspecialchars($tte_aktif['no_ktp']) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><i class="fas fa-briefcase text-info"></i> Jabatan</th>
                    <td><?= htmlspecialchars($tte_aktif['jabatan']) ?></td>
                </tr>
                <tr>
                    <th><i class="fas fa-building text-warning"></i> Unit Kerja</th>
                    <td><?= htmlspecialchars($tte_aktif['unit']) ?></td>
                </tr>
                <tr>
                    <th><i class="fas fa-key text-danger"></i> Token</th>
                    <td>
                        <code style="font-size: 10px; word-break: break-all;">
                            <?= htmlspecialchars(substr($tte_aktif['token'], 0, 32)) ?>...
                        </code>
                    </td>
                </tr>
            </table>
        </div>
        
   
        <div class="col-md-6 text-center">
            <h5 class="mb-3"><i class="fas fa-qrcode"></i> QR Code TTE</h5>
            
            <div class="qr-container">
                <img src="generate_qr.php?token=<?= $tte_aktif['token'] ?>" 
                     class="qr-code-img" 
                     width="200" 
                     alt="QR Code TTE">
                <p class="mt-3 mb-0 text-muted">
                    <small>
                        <i class="fas fa-shield-alt"></i> 
                        TTE Non-Sertifikasi FixPoint
                    </small>
                </p>
            </div>
            
            <div class="mt-4">
                <a href="generate_qr.php?token=<?= $tte_aktif['token'] ?>&download=1"
                   class="btn btn-success download-btn">
                    <i class="fas fa-download"></i> Download QR Code
                </a>
            </div>
        </div>
    </div>
    
  
    <div class="security-note mt-4">
        <h6><i class="fas fa-shield-alt"></i> Fitur Keamanan:</h6>
        <div class="row">
            <div class="col-md-6">
                <ul class="mb-0">
                    <li><i class="fas fa-check text-success"></i> Token unik 64 karakter</li>
                    <li><i class="fas fa-check text-success"></i> Terenkripsi SHA-256</li>
                    <li><i class="fas fa-check text-success"></i> Dilindungi dari duplikasi</li>
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
    

    <div class="mt-4 text-center no-print">
        <hr>
        <p class="text-muted mb-3">
            <i class="fas fa-info-circle"></i> 
            Gunakan QR Code ini untuk menandatangani dokumen Anda
        </p>
        
        <form method="POST" style="display: inline-block;" 
              onsubmit="return confirm('Yakin ingin menonaktifkan TTE ini? TTE yang dinonaktifkan tidak dapat digunakan lagi.');">
            <input type="hidden" name="tte_id" value="<?= $tte_aktif['id'] ?>">
            <button type="button" class="btn btn-danger" disabled>
    <i class="fas fa-ban"></i> Nonaktifkan TTE
</button>

        </form>
    </div>
</div>
</div>

<?php endif; ?>


<?php if (count($riwayat_tte) > 0): ?>
<div class="card mt-4">
<div class="card-header">
    <h4><i class="fas fa-history"></i> Riwayat TTE</h4>
</div>
<div class="card-body">
    <?php foreach ($riwayat_tte as $riwayat): ?>
    <div class="card riwayat-card <?= $riwayat['status'] === 'nonaktif' ? 'nonaktif' : '' ?>">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h6 class="mb-2">
                        <i class="fas fa-signature"></i> 
                        <?= htmlspecialchars($riwayat['nama']) ?>
                    </h6>
                    <p class="text-muted mb-0">
                        <small>
                            <i class="fas fa-calendar"></i> 
                            Dibuat: <?= date('d F Y, H:i', strtotime($riwayat['created_at'])) ?> WIB
                            | <i class="fas fa-id-badge"></i> NIK: <?= htmlspecialchars($riwayat['nik']) ?>
                            <?php if (!empty($riwayat['no_ktp'])): ?>
                            | <i class="fas fa-id-card"></i> KTP: <?= htmlspecialchars($riwayat['no_ktp']) ?>
                            <?php endif; ?>
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


<div class="card mt-4">
<div class="card-header bg-primary text-white">
    <h4 class="text-white"><i class="fas fa-question-circle"></i> Cara Menggunakan TTE</h4>
</div>
<div class="card-body">
    <div class="row">
        <div class="col-md-6">
            <h6><i class="fas fa-check-circle text-success"></i> Langkah-langkah:</h6>
            <ol>
                <li>Generate TTE jika belum punya</li>
                <li>Buka menu <strong>"Bubuhkan TTE"</strong></li>
                <li>Upload dokumen (PDF/Word) yang akan ditandatangani</li>
                <li>Pilih posisi TTE di dokumen</li>
                <li>Klik "Bubuhkan TTE" - TTE akan otomatis masuk ke dokumen</li>
                <li>Download dokumen yang sudah ditandatangani</li>
            </ol>
        </div>
        
        <div class="col-md-6">
            <h6><i class="fas fa-lightbulb text-warning"></i> Tips Keamanan:</h6>
            <ul>
                <li>Jangan bagikan QR Code TTE Anda ke orang lain</li>
                <li>Simpan file asli yang sudah di-TTE dengan baik</li>
                <li>Verifikasi dokumen TTE secara berkala di menu Cek TTE</li>
            </ul>
        </div>
    </div>
    
    <div class="alert alert-warning mt-3 mb-0">
        <i class="fas fa-exclamation-triangle"></i> 
        <strong>Penting:</strong> TTE yang sudah dinonaktifkan tidak dapat diaktifkan kembali. 
        Jika ingin menggunakan TTE lagi, Anda harus generate TTE baru.
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

setTimeout(function() {
    $('.alert-dismissible').fadeOut('slow');
}, 5000);

function showKonfirmasiTTE() {
    // Data dari PHP
    const nama = <?= json_encode($userData['nama'] ?? '') ?>;
    const nik = <?= json_encode($userData['nik_karyawan'] ?? '') ?>;
    const ktp = <?= json_encode($userData['no_ktp'] ?? '') ?>;
    const jabatan = <?= json_encode($userData['jabatan'] ?? '') ?>;
    const unit = <?= json_encode($userData['unit_kerja'] ?? '') ?>;
    const dataLengkap = <?= json_encode(!empty($userData['nama']) && 
                   !empty($userData['nik_karyawan']) && 
                   !empty($userData['no_ktp']) && 
                   !empty($userData['jabatan']) && 
                   !empty($userData['unit_kerja'])) ?>;
    
    // Buat badge untuk status
    function getBadge(value) {
        if (value && value.trim() !== '') {
            return `<span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 5px;">
                        <i class="fas fa-check"></i> Terisi
                    </span>`;
        } else {
            return `<span style="color: #dc3545;"><i class="fas fa-times"></i> Belum diisi</span>`;
        }
    }
    
    function getValueOrEmpty(value) {
        return value && value.trim() !== '' ? `<strong>${value}</strong>` : '';
    }
    
    let htmlContent = `
        <div style="text-align: left; padding: 5px;">
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin-bottom: 15px; border-radius: 5px; font-size: 13px;">
                <strong><i class="fas fa-info-circle"></i> Penting!</strong><br>
                Pastikan semua data sudah terisi dengan benar sebelum membuat TTE.
            </div>
            
            <h6 style="margin-bottom: 12px; font-size: 14px;"><i class="fas fa-clipboard-check"></i> Data yang Diperlukan:</h6>
            
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 13px;">
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 8px; width: 40%; font-weight: bold;">
                        <i class="fas fa-user" style="color: #6777ef;"></i> Nama
                    </td>
                    <td style="padding: 8px;">
                        ${getValueOrEmpty(nama)}
                        ${getBadge(nama)}
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 8px; font-weight: bold;">
                        <i class="fas fa-id-badge" style="color: #17a2b8;"></i> NIK Karyawan
                    </td>
                    <td style="padding: 8px;">
                        ${getValueOrEmpty(nik)}
                        ${getBadge(nik)}
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 8px; font-weight: bold;">
                        <i class="fas fa-id-card" style="color: #28a745;"></i> No. KTP
                    </td>
                    <td style="padding: 8px;">
                        ${getValueOrEmpty(ktp)}
                        ${getBadge(ktp)}
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 8px; font-weight: bold;">
                        <i class="fas fa-briefcase" style="color: #17a2b8;"></i> Jabatan
                    </td>
                    <td style="padding: 8px;">
                        ${getValueOrEmpty(jabatan)}
                        ${getBadge(jabatan)}
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 8px; font-weight: bold;">
                        <i class="fas fa-building" style="color: #ffc107;"></i> Unit Kerja
                    </td>
                    <td style="padding: 8px;">
                        ${getValueOrEmpty(unit)}
                        ${getBadge(unit)}
                    </td>
                </tr>
            </table>
            
            ${!dataLengkap ? `
            <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 10px; border-radius: 5px; font-size: 13px;">
                <strong><i class="fas fa-exclamation-triangle"></i> Data Belum Lengkap!</strong><br>
                Silakan lengkapi data di menu <strong>Profil Saya</strong> terlebih dahulu.
            </div>
            ` : `
            <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 10px; border-radius: 5px; font-size: 13px;">
                <strong><i class="fas fa-check-circle"></i> Data Sudah Lengkap!</strong><br>
                Anda dapat melanjutkan proses pembuatan TTE.
            </div>
            `}
        </div>
    `;
    
    if (dataLengkap) {
        Swal.fire({
            title: '<i class="fas fa-exclamation-triangle"></i> Konfirmasi Data TTE',
            html: htmlContent,
            icon: null,
            showCancelButton: true,
            confirmButtonColor: '#6777ef',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check"></i> Ya, Buat TTE',
            cancelButtonText: '<i class="fas fa-times"></i> Batal',
            width: '550px',
            customClass: {
                popup: 'swal-wide'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Submit form
                let form = document.createElement('form');
                form.method = 'POST';
                form.action = 'buat_tte.php';
                
                let input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'generate_tte';
                input.value = '1';
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        });
    } else {
        Swal.fire({
            title: '<i class="fas fa-exclamation-triangle"></i> Konfirmasi Data TTE',
            html: htmlContent,
            icon: null,
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-edit"></i> Lengkapi Data',
            cancelButtonText: '<i class="fas fa-times"></i> Tutup',
            width: '550px'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'profile2.php';
            }
        });
    }
}
</script>

</body>
</html>