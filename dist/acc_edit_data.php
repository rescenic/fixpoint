<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    echo "<script>alert('Silakan login terlebih dahulu');location.href='login.php';</script>";
    exit;
}

$current_file = basename(__FILE__);


$qAkses = $conn->prepare("
    SELECT 1 FROM akses_menu 
    JOIN menu ON akses_menu.menu_id = menu.id
    WHERE akses_menu.user_id = ? AND menu.file_menu = ?
");
$qAkses->bind_param("is", $user_id, $current_file);
$qAkses->execute();
$cekAkses = $qAkses->get_result();
if ($cekAkses->num_rows == 0) {
    echo "<script>alert('Anda tidak memiliki akses');location.href='dashboard.php';</script>";
    exit;
}


if (isset($_POST['aksi'])) {
    $id      = intval($_POST['id']);
    $aksi    = $_POST['aksi'];
    $catatan = trim($_POST['catatan'] ?? "");

    if ($aksi == "tolak" && $catatan == "") {
        $_SESSION['flash_message'] = "⚠ Catatan wajib diisi jika menolak!";
        header("Location: acc_edit_data.php");
        exit;
    }

    $status = ($aksi == "setuju") ? "Disetujui Atasan" : "Ditolak Atasan";

    $stmt = $conn->prepare("
        UPDATE permintaan_edit_data
        SET status = ?, catatan_atasan = ?, tanggal_acc = NOW()
        WHERE id = ? AND atasan_id = ?
    ");
    $stmt->bind_param("ssii", $status, $catatan, $id, $user_id);
    $stmt->execute();

    $_SESSION['flash_message'] = "✔ Permintaan berhasil diproses";
    header("Location: acc_edit_data.php");
    exit;
}


$data = $conn->query("
    SELECT * FROM permintaan_edit_data
    WHERE atasan_id = '$user_id'
    ORDER BY FIELD(status,
        'Menunggu Persetujuan Atasan',
        'Disetujui Atasan',
        'Ditolak Atasan'
    ), tanggal DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Persetujuan Edit Data SIMRS</title>

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
    z-index:3000;
    background:#28a745;
    color:#fff;
    padding:15px 25px;
    border-radius:8px;
    font-weight:bold;
}


.modal{ z-index:2000 !important; }
.modal-backdrop{ z-index:1990 !important; }
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
<h4>✅ Persetujuan Edit Data SIMRS</h4>
</div>

<div class="card-body">
<div class="table-responsive">
<table class="table table-bordered table-sm">
<thead class="bg-dark text-white text-center">
<tr>
<th>No</th>
<th>Nama</th>
<th>Unit</th>
<th>No Surat</th>
<th>Permintaan</th>
<th>Status</th>
<th>Aksi</th>
</tr>
</thead>
<tbody>

<?php
$no=1;
$rows = [];
while($r = $data->fetch_assoc()):
$rows[] = $r;

$statusColor = [
    "Menunggu Persetujuan Atasan"=>"badge badge-warning",
    "Disetujui Atasan"=>"badge badge-success",
    "Ditolak Atasan"=>"badge badge-danger"
];
?>
<tr>
<td><?= $no++ ?></td>
<td><?= $r['nama'] ?></td>
<td><?= $r['unit_kerja'] ?></td>
<td><?= $r['nomor_surat'] ?></td>
<td>
<b>Data Lama:</b>
<div style="white-space:pre-line"><?= $r['data_lama'] ?></div>
<hr>
<b>Data Baru:</b>
<div style="white-space:pre-line"><?= $r['data_baru'] ?></div>
<hr>
<b>Alasan:</b>
<div style="white-space:pre-line"><?= $r['alasan'] ?></div>
</td>
<td class="text-center">
<span class="<?= $statusColor[$r['status']] ?>">
<?= $r['status'] ?>
</span>
</td>
<td class="text-center">
<?php if($r['status']=="Menunggu Persetujuan Atasan"): ?>
<button class="btn btn-sm btn-success" data-toggle="modal"
        data-target="#modalAcc<?= $r['id'] ?>">
<i class="fas fa-check"></i>
</button>
<button class="btn btn-sm btn-danger" data-toggle="modal"
        data-target="#modalTolak<?= $r['id'] ?>">
<i class="fas fa-times"></i>
</button>
<?php else: ?> - <?php endif; ?>
</td>
</tr>
<?php endwhile; ?>

</tbody>
</table>
</div>
</div>
</div>

</div>
</section>
</div>
</div>
</div>


<?php foreach($rows as $r): ?>

<!-- MODAL ACC -->
<div class="modal fade" id="modalAcc<?= $r['id'] ?>" tabindex="-1">
<div class="modal-dialog modal-dialog-centered modal-sm">
<form method="POST">
<input type="hidden" name="id" value="<?= $r['id'] ?>">
<input type="hidden" name="aksi" value="setuju">

<div class="modal-content">
<div class="modal-header bg-success text-white">
<h6 class="modal-title">Setujui Permintaan</h6>
<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body text-center">
Yakin menyetujui permintaan ini?
</div>
<div class="modal-footer justify-content-center">
<button class="btn btn-success btn-sm px-4">
<i class="fas fa-check"></i> Ya
</button>
</div>
</div>
</form>
</div>
</div>

<!-- MODAL TOLAK -->
<div class="modal fade" id="modalTolak<?= $r['id'] ?>" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<form method="POST">
<input type="hidden" name="id" value="<?= $r['id'] ?>">
<input type="hidden" name="aksi" value="tolak">

<div class="modal-content">
<div class="modal-header bg-danger text-white">
<h6 class="modal-title">Tolak Permintaan</h6>
<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
<textarea name="catatan" class="form-control"
placeholder="Alasan penolakan..." required></textarea>
</div>
<div class="modal-footer justify-content-center">
<button class="btn btn-danger btn-sm px-4">
<i class="fas fa-times"></i> Tolak
</button>
</div>
</div>
</form>
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
</body>
</html>
