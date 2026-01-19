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

  $_SESSION['flash_message'] = 'Disposisi berhasil disimpan!';
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

// Simpan data untuk modal
$modal_data = [];
mysqli_data_seek($data_surat, 0);
while($s = mysqli_fetch_assoc($data_surat)) {
    $modal_data[] = $s;
}
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

/* ===== MODAL FIX - BACKDROP & Z-INDEX ===== */
.modal-backdrop {
  z-index: 1040 !important;
  background-color: rgba(0, 0, 0, 0.5) !important;
}

.modal {
  z-index: 1050 !important;
}

.modal.show {
  display: block !important;
}

.modal-dialog {
  margin: 1.75rem auto;
}

/* Modal ukuran lebih besar - 90% layar */
.modal-disposisi {
  max-width: 90%;
  width: 900px;
}

@media (max-width: 992px) {
  .modal-disposisi {
    max-width: 95%;
  }
}

/* Modal header dengan gradient */
.modal-disposisi .modal-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 20px 25px;
}

.modal-disposisi .modal-header .modal-title {
  font-size: 18px;
  font-weight: 600;
}

.modal-disposisi .modal-header .close {
  color: white;
  opacity: 0.9;
  font-size: 32px;
  text-shadow: none;
}

.modal-disposisi .modal-header .close:hover {
  opacity: 1;
}

/* Modal body */
.modal-disposisi .modal-body {
  padding: 25px;
}

.modal-disposisi .form-group label {
  font-weight: 600;
  font-size: 13px;
  color: #333;
  margin-bottom: 8px;
}

.modal-disposisi .form-control {
  font-size: 13px;
  border-radius: 6px;
  border: 1px solid #d1d5db;
}

.modal-disposisi .form-control:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
}

.modal-disposisi textarea.form-control {
  min-height: 100px;
}

/* Info box dalam modal */
.info-box {
  background-color: #f0f8ff;
  border: 1px solid #2196F3;
  border-radius: 6px;
  padding: 15px;
  margin-bottom: 20px;
}

.info-box .label {
  font-weight: 600;
  color: #004085;
  margin-right: 8px;
}

.info-box .value {
  color: #004085;
}

/* Modal footer */
.modal-disposisi .modal-footer {
  padding: 20px 25px;
  background-color: #f8f9fa;
}

/* Flash message */
.flash-message {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  min-width: 300px;
  animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
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

<?php if(isset($_SESSION['flash_message'])): ?>
<div class="alert alert-success alert-dismissible flash-message" id="flashMsg">
  <button type="button" class="close" data-dismiss="alert">&times;</button>
  <i class="fas fa-check-circle"></i> <?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?>
</div>
<?php endif; ?>

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

<?php 
if(count($modal_data) == 0): 
?>
<tr>
  <td colspan="7" class="text-center">Tidak ada data surat untuk disposisi</td>
</tr>
<?php 
else:
$no=$offset+1; 
foreach($modal_data as $s): 
?>
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
<button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#disposisi<?= $s['id'] ?>">
<i class="fas fa-edit"></i> Disposisi
</button>

<?php if ($s['jml_disposisi']>0): ?>
<a href="lihat_surat_disposisi.php?id=<?= $s['id'] ?>&mode=view" target="_blank"
class="btn btn-sm btn-info" title="Lihat"><i class="fas fa-eye"></i></a>

<a href="lihat_surat_disposisi.php?id=<?= $s['id'] ?>&mode=print" target="_blank"
class="btn btn-sm btn-secondary" title="Print"><i class="fas fa-print"></i></a>
<?php endif; ?>
</td>
</tr>

<?php endforeach; endif; ?>
</tbody>
</table>
</div>

<!-- Pagination -->
<?php if($total_pages > 1): ?>
<nav>
  <ul class="pagination">
    <?php for($i=1; $i<=$total_pages; $i++): ?>
    <li class="page-item <?= ($i==$page)?'active':'' ?>">
      <a class="page-link" href="?page=<?= $i ?>&tgl_dari=<?= $tgl_dari ?>&tgl_sampai=<?= $tgl_sampai ?>"><?= $i ?></a>
    </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

</div>
</div>
</section>
</div>
</div>
</div>

<!-- ===== MODAL DISPOSISI (DI LUAR LOOP) ===== -->
<?php foreach($modal_data as $s): ?>
<div class="modal fade" id="disposisi<?= $s['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="modalLabel<?= $s['id'] ?>" aria-hidden="true">
<div class="modal-dialog modal-disposisi modal-dialog-centered" role="document">
<div class="modal-content">
<form method="POST" action="">
<div class="modal-header">
<h5 class="modal-title" id="modalLabel<?= $s['id'] ?>">
  <i class="fas fa-file-signature"></i> Isi Disposisi Surat
</h5>
<button type="button" class="close" data-dismiss="modal" aria-label="Close">
  <span aria-hidden="true">&times;</span>
</button>
</div>

<div class="modal-body">
<input type="hidden" name="surat_id" value="<?= $s['id'] ?>">

<!-- Info Surat -->
<div class="info-box">
  <div class="row">
    <div class="col-md-6">
      <div class="mb-2">
        <span class="label">No Surat:</span>
        <span class="value"><?= htmlspecialchars($s['no_surat']) ?></span>
      </div>
      <div class="mb-2">
        <span class="label">Tgl Terima:</span>
        <span class="value"><?= date('d-m-Y', strtotime($s['tgl_terima'])) ?></span>
      </div>
    </div>
    <div class="col-md-6">
      <div class="mb-2">
        <span class="label">Pengirim:</span>
        <span class="value"><?= htmlspecialchars($s['pengirim']) ?></span>
      </div>
      <div class="mb-2">
        <span class="label">Perihal:</span>
        <span class="value"><?= htmlspecialchars($s['perihal']) ?></span>
      </div>
    </div>
  </div>
</div>

<!-- Form Input -->
<div class="form-group">
  <label for="instruksi<?= $s['id'] ?>">Instruksi <span class="text-danger">*</span></label>
  <textarea name="instruksi" id="instruksi<?= $s['id'] ?>" class="form-control" placeholder="Contoh: Tindak lanjuti segera..." required></textarea>
  <small class="form-text text-muted">Berikan instruksi yang jelas terkait surat ini</small>
</div>

<div class="form-group">
  <label for="catatan<?= $s['id'] ?>">Catatan (Opsional)</label>
  <textarea name="catatan" id="catatan<?= $s['id'] ?>" class="form-control" placeholder="Catatan tambahan..."></textarea>
  <small class="form-text text-muted">Catatan tambahan jika diperlukan</small>
</div>

</div>

<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-dismiss="modal">
  <i class="fas fa-times"></i> Batal
</button>
<button type="submit" name="simpan_disposisi" class="btn btn-success">
  <i class="fas fa-save"></i> Simpan Disposisi
</button>
</div>
</form>
</div>
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

<script>
$(document).ready(function() {
  // Auto hide flash message
  setTimeout(function(){ 
    $("#flashMsg").fadeOut("slow"); 
  }, 3000);
  
  // Fix modal backdrop issue
  $('.modal').on('show.bs.modal', function(e) {
    // Pastikan backdrop muncul dengan benar
    $(this).appendTo('body');
  });
  
  // Fix z-index when modal shown
  $('.modal').on('shown.bs.modal', function() {
    // Set z-index yang tepat
    var zIndex = 1050 + (10 * $('.modal:visible').length);
    $(this).css('z-index', zIndex);
    
    // Update backdrop z-index
    setTimeout(function() {
      $('.modal-backdrop').not('.modal-stack').css('z-index', zIndex - 1).addClass('modal-stack');
    }, 0);
  });
  
  // Reset z-index when modal hidden
  $('.modal').on('hidden.bs.modal', function() {
    if ($('.modal:visible').length > 0) {
      // Restore body padding
      $('body').addClass('modal-open');
    }
  });
  
  // Prevent body scroll when modal open
  $('.modal').on('show.bs.modal', function() {
    $('body').addClass('modal-open');
  });
});
</script>
</body>
</html>