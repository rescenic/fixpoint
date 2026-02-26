<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

/* =========================
   CEK LOGIN
========================= */
$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    echo "<script>alert('Anda belum login.');location.href='login.php';</script>";
    exit;
}

$current_file = basename(__FILE__);

/* =========================
   CEK AKSES MENU
========================= */
$stmt = $conn->prepare("
    SELECT 1 FROM akses_menu 
    JOIN menu ON akses_menu.menu_id = menu.id 
    WHERE akses_menu.user_id = ? AND menu.file_menu = ?
");
$stmt->bind_param("is", $user_id, $current_file);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
    echo "<script>alert('Anda tidak memiliki akses.');location.href='dashboard.php';</script>";
    exit;
}

/* =========================
   DATA USER SDM
========================= */
$qUser = $conn->prepare("SELECT nik, nama FROM users WHERE id=?");
$qUser->bind_param("i", $user_id);
$qUser->execute();
$user = $qUser->get_result()->fetch_assoc();

/* =========================
   PROSES ACC SDM
========================= */
if (isset($_POST['status_sdm'], $_POST['id_izin'], $_POST['catatan_sdm'])) {

    $id_izin = (int)$_POST['id_izin'];
    $status_sdm = $_POST['status_sdm'];
    $catatan_sdm = trim($_POST['catatan_sdm']);
    $waktu_acc_sdm = date('Y-m-d H:i:s');

    if (!in_array($status_sdm, ['disetujui','ditolak'])) {
        $_SESSION['flash_message'] = "❌ Status tidak valid.";
        header("Location: acc_pulang_cepat_sdm.php");
        exit;
    }

    if ($catatan_sdm == '') {
        $_SESSION['flash_message'] = "❌ Catatan SDM wajib diisi.";
        header("Location: acc_pulang_cepat_sdm.php");
        exit;
    }

    $qUpdate = $conn->prepare("
        UPDATE izin_pulang_cepat
        SET status_sdm = ?, 
            waktu_acc_sdm = ?, 
            acc_oleh_sdm = ?, 
            catatan_sdm = ?
        WHERE id = ?
    ");
    $qUpdate->bind_param(
        "ssisi",
        $status_sdm,
        $waktu_acc_sdm,
        $user_id,
        $catatan_sdm,
        $id_izin
    );
    $qUpdate->execute();

    $_SESSION['flash_message'] =
        $qUpdate->affected_rows > 0
        ? "✅ Status ACC SDM berhasil diperbarui."
        : "❌ Gagal memperbarui status SDM.";

    header("Location: acc_pulang_cepat_sdm.php");
    exit;
}

/* =========================
   FILTER & PAGINATION
========================= */
$filterNama   = $_GET['nama']   ?? '';
$filterNik    = $_GET['nik']    ?? '';
$filterDari   = $_GET['dari']   ?? date('Y-m-d');
$filterSampai = $_GET['sampai'] ?? date('Y-m-d');

$limit = 7;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

/* === HITUNG DATA === */
$sqlCount = "SELECT COUNT(*) total FROM izin_pulang_cepat WHERE tanggal BETWEEN ? AND ?";
$params = [$filterDari, $filterSampai];
$types = "ss";

if ($filterNama) {
    $sqlCount .= " AND nama LIKE ?";
    $params[] = "%$filterNama%";
    $types .= "s";
}
if ($filterNik) {
    $sqlCount .= " AND nik LIKE ?";
    $params[] = "%$filterNik%";
    $types .= "s";
}

$qCount = $conn->prepare($sqlCount);
$qCount->bind_param($types, ...$params);
$qCount->execute();
$totalData = $qCount->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalData / $limit);

/* === DATA === */
$sql = "SELECT * FROM izin_pulang_cepat WHERE tanggal BETWEEN ? AND ?";
$params = [$filterDari, $filterSampai];
$types = "ss";

if ($filterNama) {
    $sql .= " AND nama LIKE ?";
    $params[] = "%$filterNama%";
    $types .= "s";
}
if ($filterNik) {
    $sql .= " AND nik LIKE ?";
    $params[] = "%$filterNik%";
    $types .= "s";
}

$sql .= " ORDER BY tanggal DESC, created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$qIzin = $conn->prepare($sql);
$qIzin->bind_param($types, ...$params);
$qIzin->execute();
$data_izin = $qIzin->get_result();

/* =========================
   PAGINATION URL
========================= */
function pageUrl($p,$n,$k,$d,$s){
    return 'acc_pulang_cepat_sdm.php?' . http_build_query([
        'page'=>$p,'nama'=>$n,'nik'=>$k,'dari'=>$d,'sampai'=>$s
    ]);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>ACC Izin Pulang Cepat SDM</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<style>
.flash-center{position:fixed;top:20%;left:50%;transform:translate(-50%,-50%);z-index:1050}
.izin-table{font-size:13px;white-space:nowrap}
.izin-table th,.izin-table td{padding:6px 10px}
.catatan-option{border:2px solid #e3e6f0;border-radius:8px;padding:10px;margin-bottom:8px;cursor:pointer}
.catatan-option.selected{border-color:#6777ef;background:#f0f2ff}
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
<div class="alert alert-info flash-center" id="flashMsg">
<?= htmlspecialchars($_SESSION['flash_message']) ?>
</div>
<?php unset($_SESSION['flash_message']); endif; ?>

<div class="card">
<div class="card-header">
<h4>Daftar Izin Pulang Cepat – Approval SDM</h4>
</div>
<div class="card-body">

<form method="get" class="form-inline mb-3">
<input type="text" name="nama" class="form-control mr-2" placeholder="Nama" value="<?=htmlspecialchars($filterNama)?>">
<input type="text" name="nik" class="form-control mr-2" placeholder="NIK" value="<?=htmlspecialchars($filterNik)?>">
<input type="date" name="dari" class="form-control mr-2" value="<?=$filterDari?>">
<input type="date" name="sampai" class="form-control mr-2" value="<?=$filterSampai?>">
<button class="btn btn-primary mr-2"><i class="fas fa-search"></i> Cari</button>
<a href="acc_pulang_cepat_sdm.php" class="btn btn-secondary">Reset</a>
</form>

<div class="table-responsive">
<table class="table table-bordered izin-table">
<thead class="thead-dark text-center">
<tr>
<th>No</th><th>Nama</th><th>NIK</th><th>Jabatan</th>
<th>Tanggal</th><th>Jam Pulang</th><th>Keperluan</th>
<th>Status Atasan</th><th>Status SDM</th><th>Catatan SDM</th><th>Aksi</th>
</tr>
</thead>
<tbody>

<?php if($data_izin->num_rows>0):
$no=$offset+1; while($r=$data_izin->fetch_assoc()): ?>
<tr>
<td class="text-center"><?=$no++?></td>
<td><?=htmlspecialchars($r['nama'])?></td>
<td><?=htmlspecialchars($r['nik'])?></td>
<td><?=htmlspecialchars($r['jabatan'])?></td>
<td><?=date('d-m-Y',strtotime($r['tanggal']))?></td>
<td><?=$r['jam_pulang']?></td>
<td><?=htmlspecialchars($r['keperluan'])?></td>

<td class="text-center">
<span class="badge badge-<?=($r['status_atasan']=='disetujui')?'success':(($r['status_atasan']=='ditolak')?'danger':'warning')?>">
<?=ucfirst($r['status_atasan'])?>
</span>
</td>

<td class="text-center">
<span class="badge badge-<?=($r['status_sdm']=='disetujui')?'success':(($r['status_sdm']=='ditolak')?'danger':'secondary')?>">
<?=ucfirst($r['status_sdm'])?>
</span>
</td>

<td class="text-center"><?= $r['catatan_sdm'] ?: '-' ?></td>

<td class="text-center">
<?php if($r['status_sdm']=='pending'): ?>
<button class="btn btn-sm btn-success btn-acc"
data-id="<?=$r['id']?>" data-nama="<?=htmlspecialchars($r['nama'])?>">
<i class="fas fa-check"></i>
</button>
<button class="btn btn-sm btn-danger btn-tolak"
data-id="<?=$r['id']?>" data-nama="<?=htmlspecialchars($r['nama'])?>">
<i class="fas fa-times"></i>
</button>
<?php else: ?>-<?php endif; ?>
</td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="11" class="text-center">Tidak ada data.</td></tr>
<?php endif; ?>

</tbody>
</table>
</div>

<!-- PAGINATION -->
<?php if($totalPages>1): ?>
<ul class="pagination justify-content-center mt-3">
<?php for($i=1;$i<=$totalPages;$i++): ?>
<li class="page-item <?=($i==$page)?'active':''?>">
<a class="page-link" href="<?=pageUrl($i,$filterNama,$filterNik,$filterDari,$filterSampai)?>">
<?=$i?>
</a>
</li>
<?php endfor; ?>
</ul>
<?php endif; ?>

</div>
</div>
</div>
</section>
</div>
</div>
</div>

<!-- ================= MODAL APPROVAL ================= -->
<div class="modal fade" id="modalApproval" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<form method="POST">
<div class="modal-header bg-success text-white">
<h5 class="modal-title">Setujui Izin Pulang Cepat</h5>
<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
<input type="hidden" name="id_izin" id="approval_id">
<input type="hidden" name="status_sdm" value="disetujui">

<div class="form-group">
<label>Nama</label>
<input type="text" id="approval_nama" class="form-control" readonly>
</div>

<label>Catatan SDM <span class="text-danger">*</span></label>
<div class="catatan-option" onclick="selectCatatan(this,'c1')">
<input type="radio" name="catatan_sdm" id="c1" value="Sudah ACC Atasan" required> Sudah ACC Atasan
</div>
<div class="catatan-option" onclick="selectCatatan(this,'c2')">
<input type="radio" name="catatan_sdm" id="c2" value="Atasan Tidak Hadir / Libur"> Atasan Tidak Hadir / Libur
</div>
<div class="catatan-option" onclick="selectCatatan(this,'c3')">
<input type="radio" name="catatan_sdm" id="c3" value="Atasan Tidak Di Tempat"> Atasan Tidak Di Tempat
</div>
</div>
<div class="modal-footer">
<button class="btn btn-secondary" data-dismiss="modal">Batal</button>
<button class="btn btn-success"><i class="fas fa-check"></i> Setujui</button>
</div>
</form>
</div>
</div>
</div>

<!-- ================= MODAL TOLAK ================= -->
<div class="modal fade" id="modalTolak" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<form method="POST">
<div class="modal-header bg-danger text-white">
<h5 class="modal-title">Tolak Izin Pulang Cepat</h5>
<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
<input type="hidden" name="id_izin" id="tolak_id">
<input type="hidden" name="status_sdm" value="ditolak">
<div class="form-group">
<label>Nama</label>
<input type="text" id="tolak_nama" class="form-control" readonly>
</div>
<div class="form-group">
<label>Alasan Penolakan</label>
<textarea name="catatan_sdm" class="form-control" required></textarea>
</div>
</div>
<div class="modal-footer">
<button class="btn btn-secondary" data-dismiss="modal">Batal</button>
<button class="btn btn-danger"><i class="fas fa-times"></i> Tolak</button>
</div>
</form>
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
$(function(){
setTimeout(()=>$("#flashMsg").fadeOut(),3000);

$('.btn-acc').click(function(){
$('#approval_id').val($(this).data('id'));
$('#approval_nama').val($(this).data('nama'));
$('#modalApproval').modal('show');
});

$('.btn-tolak').click(function(){
$('#tolak_id').val($(this).data('id'));
$('#tolak_nama').val($(this).data('nama'));
$('#modalTolak').modal('show');
});
});

function selectCatatan(el,id){
$('.catatan-option').removeClass('selected');
$(el).addClass('selected');
$('#'+id).prop('checked',true);
}
</script>
</body>
</html>
