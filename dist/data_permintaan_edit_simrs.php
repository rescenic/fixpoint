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

// ===== CEK AKSES MENU =====
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

// ================= FILTER =================
$tgl_awal  = $_GET['tgl_awal'] ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';
$status    = $_GET['status'] ?? '';
$page      = max(1, intval($_GET['page'] ?? 1));
$limit     = 10;
$offset    = ($page - 1) * $limit;

$where = "1=1";
if ($tgl_awal && $tgl_akhir) {
    $where .= " AND DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'";
}
if ($status) {
    $where .= " AND p.status = '$status'";
}

// ================= TOTAL DATA =================
$qCount = $conn->query("
    SELECT COUNT(*) AS total
    FROM permintaan_edit_data p
    WHERE $where
");
$totalData = $qCount->fetch_assoc()['total'];
$totalPage = ceil($totalData / $limit);

// ================= LOAD DATA =================
$data = $conn->query("
    SELECT p.*, u.nik, u.nama, u.jabatan, u.unit_kerja
    FROM permintaan_edit_data p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE $where
    ORDER BY p.tanggal DESC
    LIMIT $limit OFFSET $offset
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Rekap Permintaan Edit Data SIMRS</title>

<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">

<style>
.table-scroll{
    overflow-x:auto;
    white-space:nowrap;
}
.table td,.table th{
    white-space:nowrap;
    vertical-align:middle;
    font-size:13px;
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

<div class="card">
<div class="card-header">
<h4>📊 Rekap Permintaan Edit Data SIMRS</h4>
</div>

<div class="card-body">

<!-- ================= FILTER ================= -->
<form method="GET" class="mb-3">
<div class="row">
<div class="col-md-3">
<label>Tanggal Awal</label>
<input type="date" name="tgl_awal" class="form-control" value="<?= $tgl_awal ?>">
</div>
<div class="col-md-3">
<label>Tanggal Akhir</label>
<input type="date" name="tgl_akhir" class="form-control" value="<?= $tgl_akhir ?>">
</div>
<div class="col-md-3">
<label>Status</label>
<select name="status" class="form-control">
<option value="">- Semua -</option>
<?php
$statusList = [
"Menunggu Persetujuan Atasan",
"Disetujui Atasan",
"Ditolak Atasan"
];
foreach ($statusList as $s):
$sel = ($status==$s) ? "selected" : "";
?>
<option value="<?= $s ?>" <?= $sel ?>><?= $s ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-3 d-flex align-items-end">
<button class="btn btn-primary mr-2">
<i class="fas fa-filter"></i> Filter
</button>
<a href="data_permintaan_edit_simrs.php" class="btn btn-secondary">Reset</a>
</div>
</div>
</form>

<!-- ================= TABLE ================= -->
<div class="table-scroll">
<table class="table table-bordered table-sm">
<thead class="bg-dark text-white text-center">
<tr>
<th>No</th>
<th>Tanggal</th>
<th>No Surat</th>
<th>NIK</th>
<th>Nama</th>
<th>Jabatan</th>
<th>Unit</th>
<th>Data Lama</th>
<th>Data Baru</th>
<th>Alasan</th>
<th>Status</th>
<th>Cetak</th>
</tr>
</thead>
<tbody>

<?php
$no = $offset + 1;
$statusColor = [
"Menunggu Persetujuan Atasan"=>"badge badge-warning",
"Disetujui Atasan"=>"badge badge-success",
"Ditolak Atasan"=>"badge badge-danger"
];

while($r = $data->fetch_assoc()):
?>
<tr>
<td class="text-center"><?= $no++ ?></td>
<td><?= date('d-m-Y H:i', strtotime($r['tanggal'])) ?></td>
<td><?= $r['nomor_surat'] ?></td>
<td><?= $r['nik'] ?></td>
<td><?= $r['nama'] ?></td>
<td><?= $r['jabatan'] ?></td>
<td><?= $r['unit_kerja'] ?></td>
<td><?= $r['data_lama'] ?></td>
<td><?= $r['data_baru'] ?></td>
<td><?= $r['alasan'] ?></td>
<td class="text-center">
<span class="<?= $statusColor[$r['status']] ?? 'badge badge-secondary' ?>">
<?= $r['status'] ?>
</span>
</td>
<td class="text-center">
<a href="print_edit_data_simrs.php?id=<?= $r['id'] ?>"
   target="_blank"
   class="btn btn-sm btn-secondary">
<i class="fas fa-print"></i>
</a>
</td>
</tr>
<?php endwhile; ?>

</tbody>
</table>
</div>

<!-- ================= PAGINATION ================= -->
<nav class="mt-3">
<ul class="pagination justify-content-center">
<?php
$queryStr = "&tgl_awal=$tgl_awal&tgl_akhir=$tgl_akhir&status=$status";
?>
<li class="page-item <?= ($page<=1)?'disabled':'' ?>">
<a class="page-link" href="?page=<?= $page-1 . $queryStr ?>">‹</a>
</li>

<?php for($i=1;$i<=$totalPage;$i++): ?>
<li class="page-item <?= ($page==$i)?'active':'' ?>">
<a class="page-link" href="?page=<?= $i . $queryStr ?>"><?= $i ?></a>
</li>
<?php endfor; ?>

<li class="page-item <?= ($page>=$totalPage)?'disabled':'' ?>">
<a class="page-link" href="?page=<?= $page+1 . $queryStr ?>">›</a>
</li>
</ul>
</nav>

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
