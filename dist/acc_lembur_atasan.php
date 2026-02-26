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
   DATA ATASAN
================================ */
$qUser = $conn->prepare("SELECT nama FROM users WHERE id=?");
$qUser->bind_param("i", $user_id);
$qUser->execute();
$atasan = $qUser->get_result()->fetch_assoc();

/* ===============================
   PROSES ACC / TOLAK
================================ */
if (isset($_POST['id_lembur'], $_POST['status_atasan'])) {

    $id_lembur = intval($_POST['id_lembur']);
    $status    = $_POST['status_atasan'];

    if (!in_array($status, ['disetujui','ditolak'])) {
        $_SESSION['flash_message'] = "Status tidak valid.";
        header("Location: acc_lembur_atasan.php");
        exit;
    }

    $update = $conn->prepare("
        UPDATE lembur_pengajuan
        SET status_atasan=?,
            waktu_acc_atasan=NOW(),
            acc_oleh_atasan=?
        WHERE id=?
    ");
    $update->bind_param("sii", $status, $user_id, $id_lembur);
    $update->execute();

    $_SESSION['flash_message'] =
        ($update->affected_rows > 0)
        ? "✅ Status lembur berhasil diperbarui."
        : "❌ Gagal memperbarui status.";

    header("Location: acc_lembur_atasan.php");
    exit;
}

/* ===============================
   FILTER & PAGINATION
================================ */
$tgl_awal  = $_GET['tgl_awal'] ?? date('Y-m-d');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

$limit  = 10;
$page   = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

/* Hitung total */
$count = $conn->prepare("
    SELECT COUNT(*) total
    FROM lembur_pengajuan lp
    JOIN users u ON lp.user_id = u.id
    WHERE u.atasan_id = ?
    AND DATE(lp.lembur_mulai) BETWEEN ? AND ?
");
$count->bind_param("iss", $user_id, $tgl_awal, $tgl_akhir);
$count->execute();
$total_data = $count->get_result()->fetch_assoc()['total'];
$total_page = ceil($total_data / $limit);

/* Data lembur */
$data = $conn->prepare("
    SELECT lp.*
    FROM lembur_pengajuan lp
    JOIN users u ON lp.user_id = u.id
    WHERE u.atasan_id = ?
    AND DATE(lp.lembur_mulai) BETWEEN ? AND ?
    ORDER BY lp.created_at DESC
    LIMIT ? OFFSET ?
");
$data->bind_param("issii", $user_id, $tgl_awal, $tgl_akhir, $limit, $offset);
$data->execute();
$data_lembur = $data->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>ACC Lembur Atasan</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<style>
.lembur-table { font-size: 13px; white-space: nowrap; }
.lembur-table th, .lembur-table td { padding: 6px 10px; }
.flash-center {
    position: fixed; top: 20%; left: 50%;
    transform: translate(-50%, -50%);
    z-index: 1050;
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

<?php if(isset($_SESSION['flash_message'])): ?>
<div class="alert alert-info flash-center" id="flashMsg">
<?= $_SESSION['flash_message']; ?>
</div>
<?php unset($_SESSION['flash_message']); endif; ?>

<div class="card">
<div class="card-header">
<h4>Approval Lembur – Atasan</h4>
</div>

<div class="card-body">

<form class="form-inline mb-3" method="get">
<label class="mr-2">Periode</label>
<input type="date" name="tgl_awal" class="form-control mr-2" value="<?= $tgl_awal ?>">
<input type="date" name="tgl_akhir" class="form-control mr-2" value="<?= $tgl_akhir ?>">
<button class="btn btn-primary">
<i class="fas fa-filter"></i> Filter
</button>
</form>

<div class="table-responsive">
<table class="table table-bordered lembur-table">
<thead class="thead-dark text-center">
<tr>
<th>No</th>
<th>No Surat</th>
<th>Nama</th>
<th>Unit</th>
<th>Waktu Lembur</th>
<th>Total Jam</th>
<th>Status</th>
<th>Aksi</th>
</tr>
</thead>
<tbody>
<?php if($data_lembur->num_rows > 0): 
$no = $offset + 1;
while($r = $data_lembur->fetch_assoc()): ?>
<tr>
<td class="text-center"><?= $no++ ?></td>
<td><?= $r['no_surat'] ?></td>
<td><?= htmlspecialchars($r['nama']) ?></td>
<td><?= htmlspecialchars($r['unit']) ?></td>
<td>
<?= date('d-m-Y H:i',strtotime($r['lembur_mulai'])) ?><br>
s/d<br>
<?= date('d-m-Y H:i',strtotime($r['lembur_selesai'])) ?>
</td>
<td class="text-center"><?= $r['total_jam'] ?> Jam</td>
<td class="text-center">
<?php
$badge='secondary';
if($r['status_atasan']=='disetujui') $badge='success';
elseif($r['status_atasan']=='ditolak') $badge='danger';
elseif($r['status_atasan']=='pending') $badge='warning';
echo "<span class='badge badge-$badge'>".ucfirst($r['status_atasan'])."</span>";
?>
</td>
<td class="text-center">
<?php if($r['status_atasan']=='pending'): ?>
<form method="post" style="display:inline">
<input type="hidden" name="id_lembur" value="<?= $r['id'] ?>">
<button name="status_atasan" value="disetujui"
        class="btn btn-sm btn-success"
        onclick="return confirm('Setujui lembur ini?')">
<i class="fas fa-check"></i>
</button>
</form>

<form method="post" style="display:inline">
<input type="hidden" name="id_lembur" value="<?= $r['id'] ?>">
<button name="status_atasan" value="ditolak"
        class="btn btn-sm btn-danger"
        onclick="return confirm('Tolak lembur ini?')">
<i class="fas fa-times"></i>
</button>
</form>
<?php else: ?>
<span>-</span>
<?php endif; ?>
</td>
</tr>
<?php endwhile; else: ?>
<tr>
<td colspan="8" class="text-center">Tidak ada pengajuan lembur</td>
</tr>
<?php endif; ?>
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
</script>

</body>
</html>
