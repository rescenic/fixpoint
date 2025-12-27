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
$qUser = $conn->query("SELECT nama, jabatan, unit_kerja, atasan_id FROM users WHERE id='$user_id'");
$userData = $qUser->fetch_assoc();

$nama       = $userData['nama'] ?? "-";
$jabatan    = $userData['jabatan'] ?? "-";
$unit       = $userData['unit_kerja'] ?? "-";
$atasan_id  = $userData['atasan_id'] ?? 0;

// ====== SIMPAN DATA ======
if (isset($_POST['simpan'])) {
    $data_lama  = trim($_POST['data_lama']);
    $data_baru  = trim($_POST['data_baru']);
    $alasan     = trim($_POST['alasan']);

    if ($data_lama == "" || $data_baru == "" || $alasan == "") {
        $_SESSION['flash_message'] = "⚠ Semua field wajib diisi!";
    } else {

        // === GENERATE NOMOR SURAT ===
        $romawi = [
            1=>"I",2=>"II",3=>"III",4=>"IV",5=>"V",6=>"VI",
            7=>"VII",8=>"VIII",9=>"IX",10=>"X",11=>"XI",12=>"XII"
        ];

        $bulan = date('n');
        $tahun = date('Y');

        $qLast = $conn->query("SELECT nomor_surat FROM permintaan_edit_data ORDER BY id DESC LIMIT 1");
        $lastNum = 1;
        if ($qLast && $qLast->num_rows > 0) {
            $last = $qLast->fetch_assoc();
            preg_match('/(\d+)\//', $last['nomor_surat'], $m);
            if (isset($m[1])) $lastNum = intval($m[1]) + 1;
        }

        $nomor_surat = str_pad($lastNum, 4, '0', STR_PAD_LEFT)
                     . "/PEDS/RSPH/" . $romawi[$bulan] . "/$tahun";

        // === SIMPAN ===
        $stmt = $conn->prepare("
            INSERT INTO permintaan_edit_data
            (user_id, atasan_id, nama, jabatan, unit_kerja, nomor_surat,
             data_lama, data_baru, alasan, status)
            VALUES (?,?,?,?,?,?,?,?,?, 'Menunggu Persetujuan Atasan')
        ");
        $stmt->bind_param(
            "iisssssss",
            $user_id, $atasan_id, $nama, $jabatan, $unit,
            $nomor_surat, $data_lama, $data_baru, $alasan
        );

        if ($stmt->execute()) {
            $_SESSION['flash_message'] = "✔ Permintaan edit data berhasil dikirim ke atasan!";
        } else {
            $_SESSION['flash_message'] = "❌ Gagal menyimpan data.";
        }
    }

    echo "<script>location.href='edit_data_simrs.php';</script>";
    exit;
}

// ====== LOAD DATA ======
$data_query = $conn->query("
    SELECT * FROM permintaan_edit_data
    WHERE user_id='$user_id'
    ORDER BY id DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Permintaan Edit Data SIMRS</title>

<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">

<style>
.flash-center{
    position:fixed;
    top:15%;
    left:50%;
    transform:translate(-50%,-50%);
    z-index:1050;
    padding:15px 25px;
    border-radius:8px;
    background:#ffc107;
    font-weight:bold;
}
.form-group label{font-weight:600;font-size:14px}
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
<div class="flash-center" id="flashMsg">
<?= $_SESSION['flash_message']; ?>
</div>
<script>
setTimeout(()=>document.getElementById('flashMsg').style.display='none',3000);
</script>
<?php unset($_SESSION['flash_message']); endif; ?>

<div class="card">
<div class="card-header">
<h4>✏️ Form Permintaan Edit Data SIMRS</h4>
</div>

<div class="card-body">
<ul class="nav nav-tabs">
<li class="nav-item">
<a class="nav-link active" data-toggle="tab" href="#input">Input Data</a>
</li>
<li class="nav-item">
<a class="nav-link" data-toggle="tab" href="#data">Data Tersimpan</a>
</li>
</ul>

<div class="tab-content mt-3">

<!-- INPUT -->
<div class="tab-pane fade show active" id="input">
<form method="POST">

<div class="row">
<div class="col-md-4">
<label>Nama</label>
<input class="form-control" value="<?= $nama ?>" readonly>
</div>
<div class="col-md-4">
<label>Jabatan</label>
<input class="form-control" value="<?= $jabatan ?>" readonly>
</div>
<div class="col-md-4">
<label>Unit Kerja</label>
<input class="form-control" value="<?= $unit ?>" readonly>
</div>
</div>

<div class="form-group mt-3">
<label>Data Lama</label>
<textarea name="data_lama" class="form-control" rows="3" required></textarea>
</div>

<div class="form-group">
<label>Data Baru</label>
<textarea name="data_baru" class="form-control" rows="3" required></textarea>
</div>

<div class="form-group">
<label>Alasan Perubahan</label>
<textarea name="alasan" class="form-control" rows="4" required></textarea>
</div>

<div class="text-right">
<button class="btn btn-primary" name="simpan">
<i class="fas fa-paper-plane"></i> Kirim
</button>
</div>

</form>
</div>

<!-- DATA -->
<div class="tab-pane fade" id="data">
<div class="table-responsive">
<table class="table table-bordered table-sm">
<thead class="bg-dark text-white text-center">
<tr>
<th>No</th>
<th>No Surat</th>
<th>Tanggal</th>
<th>Status</th>
<th>Cetak</th>
</tr>
</thead>
<tbody>
<?php $no=1; while($r=$data_query->fetch_assoc()): ?>
<tr>
<td><?= $no++ ?></td>
<td><?= $r['nomor_surat'] ?></td>
<td><?= $r['tanggal'] ?></td>
<td class="text-center">
<span class="badge badge-info"><?= $r['status'] ?></span>
</td>
<td class="text-center">
<a href="print_edit_data_simrs.php?id=<?= $r['id'] ?>" target="_blank"
   class="btn btn-sm btn-secondary">
<i class="fas fa-print"></i>
</a>
</td>
</tr>
<?php endwhile; ?>
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
