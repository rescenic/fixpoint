<?php
include 'security.php';
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'];
$current_file = basename(__FILE__);

// ================= CEK AKSES =================
$qAkses = mysqli_query($conn,"
  SELECT 1 FROM akses_menu 
  JOIN menu ON akses_menu.menu_id = menu.id 
  WHERE akses_menu.user_id = '$user_id'
  AND menu.file_menu = '$current_file'
");
if (mysqli_num_rows($qAkses) == 0) {
  echo "<script>alert('Anda tidak memiliki akses.');location.href='dashboard.php';</script>";
  exit;
}

// ================= USER LOGIN =================
$user = mysqli_fetch_assoc(mysqli_query($conn,"SELECT nama FROM users WHERE id='$user_id'"));
$nama_login = $user['nama'];

// ================= SIMPAN DISPOSISI =================
if (isset($_POST['simpan_disposisi'])) {
  $surat_id  = (int)$_POST['surat_id'];
  $instruksi = mysqli_real_escape_string($conn,$_POST['instruksi']);
  $catatan   = mysqli_real_escape_string($conn,$_POST['catatan']);

  mysqli_query($conn,"
    INSERT INTO disposisi
    (surat_masuk_id, instruksi, catatan, tanggal_disposisi, disposisi_oleh)
    VALUES
    ('$surat_id','$instruksi','$catatan',NOW(),'$user_id')
  ");

  header("Location: disposisi.php");
  exit;
}

// ================= FILTER =================
$tgl_dari   = $_GET['tgl_dari'] ?? date('Y-m-d');
$tgl_sampai = $_GET['tgl_sampai'] ?? date('Y-m-d');

// ================= PAGINATION =================
$limit  = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// ================= HITUNG =================
$qTotal = mysqli_fetch_assoc(mysqli_query($conn,"
  SELECT COUNT(*) total
  FROM surat_masuk
  WHERE disposisi_ke='$nama_login'
  AND DATE(tgl_terima) BETWEEN '$tgl_dari' AND '$tgl_sampai'
"));
$total_pages = ceil($qTotal['total'] / $limit);

// ================= DATA =================
$data_surat = mysqli_query($conn,"
  SELECT sm.*,
    (SELECT COUNT(*) FROM disposisi d WHERE d.surat_masuk_id=sm.id) jml_disposisi
  FROM surat_masuk sm
  WHERE sm.disposisi_ke='$nama_login'
  AND DATE(sm.tgl_terima) BETWEEN '$tgl_dari' AND '$tgl_sampai'
  ORDER BY sm.tgl_terima DESC
  LIMIT $offset,$limit
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Disposisi Surat</title>

<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">

<style>
/* =======================
   TABEL RAPI & 1 BARIS
======================= */
.table-wrapper {
  overflow-x: auto;
}

.table-nowrap th,
.table-nowrap td {
  white-space: nowrap;
  vertical-align: middle;
}

.col-no       { width: 50px; text-align:center; }
.col-nosurat  { max-width: 180px; }
.col-tgl      { width: 110px; }
.col-pengirim { max-width: 200px; }
.col-perihal  { max-width: 280px; }
.col-status   { width: 140px; text-align:center; }
.col-aksi     { width: 140px; text-align:center; }

/* potong teks panjang */
.text-ellipsis {
  overflow: hidden;
  text-overflow: ellipsis;
}

/* modal aman */
.modal-backdrop { z-index:1040!important }
.modal { z-index:1050!important }
</style>
</head>

<body>
<div id="app">
<div class="main-wrapper main-wrapper-1">
<?php include 'navbar.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="main-content">
<section class="section">
<div class="card">

<div class="card-header">
<h4><i class="fas fa-inbox"></i> Disposisi Surat Masuk</h4>
<small class="text-muted">Untuk: <b><?= htmlspecialchars($nama_login) ?></b></small>
</div>

<div class="card-body">

<form class="form-inline mb-3">
<input type="date" name="tgl_dari" value="<?= $tgl_dari ?>" class="form-control mr-2">
<input type="date" name="tgl_sampai" value="<?= $tgl_sampai ?>" class="form-control mr-2">
<button class="btn btn-primary"><i class="fas fa-filter"></i> Tampilkan</button>
</form>

<div class="table-wrapper">
<table class="table table-bordered table-striped table-nowrap">
<thead class="thead-dark">
<tr>
<th class="col-no">No</th>
<th class="col-nosurat">No Surat</th>
<th class="col-tgl">Tgl Terima</th>
<th class="col-pengirim">Pengirim</th>
<th class="col-perihal">Perihal</th>
<th class="col-status">Status</th>
<th class="col-aksi">Aksi</th>
</tr>
</thead>
<tbody>

<?php $no=$offset+1; while($s=mysqli_fetch_assoc($data_surat)): ?>
<tr>
<td class="col-no"><?= $no++ ?></td>

<td class="col-nosurat text-ellipsis" title="<?= htmlspecialchars($s['no_surat']) ?>">
<?= htmlspecialchars($s['no_surat']) ?>
</td>

<td class="col-tgl"><?= date('d-m-Y',strtotime($s['tgl_terima'])) ?></td>

<td class="col-pengirim text-ellipsis" title="<?= htmlspecialchars($s['pengirim']) ?>">
<?= htmlspecialchars($s['pengirim']) ?>
</td>

<td class="col-perihal text-ellipsis" title="<?= htmlspecialchars($s['perihal']) ?>">
<?= htmlspecialchars($s['perihal']) ?>
</td>

<td class="col-status">
<?= $s['jml_disposisi']>0
? '<span class="badge badge-success">Sudah</span>'
: '<span class="badge badge-warning">Belum</span>' ?>
</td>

<td class="col-aksi">
<button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#disposisi<?= $s['id'] ?>">
<i class="fas fa-edit"></i>
</button>

<?php if ($s['jml_disposisi']>0): ?>
<a href="lihat_surat_disposisi.php?id=<?= $s['id'] ?>&mode=view" target="_blank"
class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>

<a href="lihat_surat_disposisi.php?id=<?= $s['id'] ?>&mode=print" target="_blank"
class="btn btn-sm btn-secondary"><i class="fas fa-print"></i></a>
<?php endif; ?>
</td>
</tr>

<!-- MODAL DISPOSISI -->
<div class="modal fade" id="disposisi<?= $s['id'] ?>">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<form method="POST">
<div class="modal-header">
<h5 class="modal-title">Isi Disposisi</h5>
<button class="close" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
<input type="hidden" name="surat_id" value="<?= $s['id'] ?>">
<textarea name="instruksi" class="form-control mb-2" placeholder="Instruksi" required></textarea>
<textarea name="catatan" class="form-control" placeholder="Catatan"></textarea>
</div>
<div class="modal-footer">
<button class="btn btn-secondary" data-dismiss="modal">Batal</button>
<button class="btn btn-success" name="simpan_disposisi">Simpan</button>
</div>
</form>
</div>
</div>
</div>

<?php endwhile; ?>
</tbody>
</table>
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
