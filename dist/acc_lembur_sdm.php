<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

/* ===============================
   CEK LOGIN
================================ */
$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    echo "<script>alert('Anda belum login');location.href='login.php';</script>";
    exit;
}

$current_file = basename(__FILE__);

/* ===============================
   CEK AKSES MENU
================================ */
$stmt = $conn->prepare("
    SELECT 1 FROM akses_menu
    JOIN menu ON akses_menu.menu_id = menu.id
    WHERE akses_menu.user_id=? AND menu.file_menu=?
");
$stmt->bind_param("is", $user_id, $current_file);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
    echo "<script>alert('Anda tidak memiliki akses');location.href='dashboard.php';</script>";
    exit;
}

/* ===============================
   DATA USER SDM
================================ */
$qUser = $conn->prepare("SELECT nama FROM users WHERE id=?");
$qUser->bind_param("i", $user_id);
$qUser->execute();
$user_sdm = $qUser->get_result()->fetch_assoc();

/* ===============================
   PROSES ACC / TOLAK SDM
================================ */
if (isset($_POST['id_lembur'], $_POST['status_sdm'], $_POST['catatan_sdm'])) {

    $id_lembur   = intval($_POST['id_lembur']);
    $status_sdm  = $_POST['status_sdm'];
    $catatan     = trim($_POST['catatan_sdm']);

    if (!in_array($status_sdm, ['disetujui','ditolak'])) {
        $_SESSION['flash_message'] = "❌ Status tidak valid.";
        header("Location: acc_lembur_sdm.php");
        exit;
    }

    if ($catatan == '') {
        $_SESSION['flash_message'] = "❌ Catatan SDM wajib diisi.";
        header("Location: acc_lembur_sdm.php");
        exit;
    }

    $update = $conn->prepare("
        UPDATE lembur_pengajuan
        SET status_sdm=?,
            waktu_acc_sdm=NOW(),
            acc_oleh_sdm=?,
            catatan_sdm=?
        WHERE id=?
    ");
    $update->bind_param("sisi", $status_sdm, $user_id, $catatan, $id_lembur);
    $update->execute();

    $_SESSION['flash_message'] =
        ($update->affected_rows > 0)
        ? "✅ Status lembur berhasil diperbarui."
        : "❌ Gagal memperbarui status lembur.";

    header("Location: acc_lembur_sdm.php");
    exit;
}

/* ===============================
   FILTER & PAGINATION
================================ */
$filterNama   = $_GET['nama'] ?? '';
$filterNik    = $_GET['nik'] ?? '';
$tgl_awal     = $_GET['tgl_awal'] ?? date('Y-m-d');
$tgl_akhir    = $_GET['tgl_akhir'] ?? date('Y-m-d');

$limit  = 7;
$page   = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

/* ===============================
   HITUNG TOTAL DATA
================================ */
$sqlCount = "SELECT COUNT(*) total FROM lembur_pengajuan WHERE DATE(lembur_mulai) BETWEEN ? AND ?";
$params = [$tgl_awal, $tgl_akhir];
$types  = "ss";

if ($filterNama) {
    $sqlCount .= " AND nama LIKE ?";
    $params[] = "%$filterNama%";
    $types   .= "s";
}
if ($filterNik) {
    $sqlCount .= " AND nik LIKE ?";
    $params[] = "%$filterNik%";
    $types   .= "s";
}

$qCount = $conn->prepare($sqlCount);
$qCount->bind_param($types, ...$params);
$qCount->execute();
$totalData = $qCount->get_result()->fetch_assoc()['total'];
$totalPage = ceil($totalData / $limit);

/* ===============================
   DATA LEMBUR
================================ */
$sql = "SELECT * FROM lembur_pengajuan WHERE DATE(lembur_mulai) BETWEEN ? AND ?";
$params = [$tgl_awal, $tgl_akhir];
$types  = "ss";

if ($filterNama) {
    $sql .= " AND nama LIKE ?";
    $params[] = "%$filterNama%";
    $types   .= "s";
}
if ($filterNik) {
    $sql .= " AND nik LIKE ?";
    $params[] = "%$filterNik%";
    $types   .= "s";
}

$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types   .= "ii";

$qData = $conn->prepare($sql);
$qData->bind_param($types, ...$params);
$qData->execute();
$data_lembur = $qData->get_result();

/* ===============================
   URL PAGINATION
================================ */
function pageUrl($p,$n,$nik,$a,$b){
    return 'acc_lembur_sdm.php?'.http_build_query([
        'page'=>$p,
        'nama'=>$n,
        'nik'=>$nik,
        'tgl_awal'=>$a,
        'tgl_akhir'=>$b
    ]);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>ACC Lembur SDM</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<style>
.flash-center {
    position: fixed; top: 20%; left: 50%;
    transform: translate(-50%,-50%);
    z-index: 1050;
}
.lembur-table { font-size: 13px; white-space: nowrap; }
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
<?= $_SESSION['flash_message']; ?>
</div>
<?php unset($_SESSION['flash_message']); endif; ?>

<div class="card">
<div class="card-header">
<h4>Approval Lembur – SDM</h4>
</div>

<div class="card-body">

<!-- FILTER -->
<form class="form-inline mb-3">
<input type="text" name="nama" class="form-control mr-2" placeholder="Nama" value="<?= htmlspecialchars($filterNama) ?>">
<input type="text" name="nik" class="form-control mr-2" placeholder="NIK" value="<?= htmlspecialchars($filterNik) ?>">
<input type="date" name="tgl_awal" class="form-control mr-2" value="<?= $tgl_awal ?>">
<input type="date" name="tgl_akhir" class="form-control mr-2" value="<?= $tgl_akhir ?>">
<button class="btn btn-primary mr-2"><i class="fas fa-search"></i></button>
<a href="acc_lembur_sdm.php" class="btn btn-secondary">Reset</a>
</form>

<div class="table-responsive">
<table class="table table-bordered lembur-table">
<thead class="thead-dark text-center">
<tr>
<th>No</th>
<th>No Surat</th>
<th>Nama</th>
<th>NIK</th>
<th>Waktu Lembur</th>
<th>Total Jam</th>
<th>Status Atasan</th>
<th>Status SDM</th>
<th>Catatan SDM</th>
<th>Aksi</th>
</tr>
</thead>
<tbody>

<?php if($data_lembur->num_rows>0):
$no=$offset+1;
while($r=$data_lembur->fetch_assoc()): ?>
<tr>
<td class="text-center"><?= $no++ ?></td>
<td><?= $r['no_surat'] ?></td>
<td><?= htmlspecialchars($r['nama']) ?></td>
<td><?= htmlspecialchars($r['nik']) ?></td>
<td>
<?= date('d-m-Y H:i',strtotime($r['lembur_mulai'])) ?><br>
s/d<br>
<?= date('d-m-Y H:i',strtotime($r['lembur_selesai'])) ?>
</td>
<td class="text-center"><?= $r['total_jam'] ?> Jam</td>

<td class="text-center">
<?php
$ba='secondary';
if($r['status_atasan']=='disetujui') $ba='success';
elseif($r['status_atasan']=='ditolak') $ba='danger';
elseif($r['status_atasan']=='pending') $ba='warning';
echo "<span class='badge badge-$ba'>".ucfirst($r['status_atasan'])."</span>";
?>
</td>

<td class="text-center">
<?php
$bs='secondary';
if($r['status_sdm']=='disetujui') $bs='success';
elseif($r['status_sdm']=='ditolak') $bs='danger';
elseif($r['status_sdm']=='pending') $bs='warning';
echo "<span class='badge badge-$bs'>".ucfirst($r['status_sdm'])."</span>";
?>
</td>

<td><?= $r['catatan_sdm'] ?: '-' ?></td>

<td class="text-center">
<?php if($r['status_sdm']=='pending'): ?>
<button class="btn btn-success btn-sm btn-acc"
        data-id="<?= $r['id'] ?>"
        data-nama="<?= htmlspecialchars($r['nama']) ?>">
<i class="fas fa-check"></i>
</button>

<button class="btn btn-danger btn-sm btn-tolak"
        data-id="<?= $r['id'] ?>"
        data-nama="<?= htmlspecialchars($r['nama']) ?>">
<i class="fas fa-times"></i>
</button>
<?php else: ?>
<span>-</span>
<?php endif; ?>
</td>

</tr>
<?php endwhile; else: ?>
<tr><td colspan="10" class="text-center">Tidak ada data lembur</td></tr>
<?php endif; ?>

</tbody>
</table>
</div>

<!-- PAGINATION -->
<?php if($totalPage>1): ?>
<nav>
<ul class="pagination justify-content-center">
<?php for($i=1;$i<=$totalPage;$i++): ?>
<li class="page-item <?= ($i==$page)?'active':'' ?>">
<a class="page-link" href="<?= pageUrl($i,$filterNama,$filterNik,$tgl_awal,$tgl_akhir) ?>">
<?= $i ?>
</a>
</li>
<?php endfor; ?>
</ul>
</nav>
<?php endif; ?>

</div>
</div>
</div>
</section>
</div>
</div>
</div>

<!-- ================= MODAL ACC ================= -->
<div class="modal fade" id="modalAcc">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<form method="post">
<div class="modal-header bg-success text-white">
<h5 class="modal-title">Setujui Lembur</h5>
<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
<input type="hidden" name="id_lembur" id="acc_id">
<input type="hidden" name="status_sdm" value="disetujui">
<p>Setujui lembur atas nama <b id="acc_nama"></b>?</p>
<label>Catatan SDM</label>
<textarea name="catatan_sdm" class="form-control" required></textarea>
</div>
<div class="modal-footer">
<button class="btn btn-secondary" data-dismiss="modal">Batal</button>
<button class="btn btn-success">Setujui</button>
</div>
</form>
</div>
</div>
</div>

<!-- ================= MODAL TOLAK ================= -->
<div class="modal fade" id="modalTolak">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<form method="post">
<div class="modal-header bg-danger text-white">
<h5 class="modal-title">Tolak Lembur</h5>
<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
<input type="hidden" name="id_lembur" id="tolak_id">
<input type="hidden" name="status_sdm" value="ditolak">
<p>Tolak lembur atas nama <b id="tolak_nama"></b>?</p>
<label>Alasan Penolakan</label>
<textarea name="catatan_sdm" class="form-control" required></textarea>
</div>
<div class="modal-footer">
<button class="btn btn-secondary" data-dismiss="modal">Batal</button>
<button class="btn btn-danger">Tolak</button>
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
setTimeout(()=>$('#flashMsg').fadeOut(),3000);

$('.btn-acc').click(function(){
    $('#acc_id').val($(this).data('id'));
    $('#acc_nama').text($(this).data('nama'));
    $('#modalAcc').modal('show');
});

$('.btn-tolak').click(function(){
    $('#tolak_id').val($(this).data('id'));
    $('#tolak_nama').text($(this).data('nama'));
    $('#modalTolak').modal('show');
});
</script>

</body>
</html>
