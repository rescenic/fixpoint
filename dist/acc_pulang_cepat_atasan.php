<?php
session_start();
include 'koneksi.php';
include 'send_wa.php';
date_default_timezone_set('Asia/Jakarta');

/* ===============================
   CEK LOGIN
================================ */
$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    echo "<script>alert('Anda belum login.'); window.location.href='login.php';</script>";
    exit;
}

$current_file = basename(__FILE__);

/* ===============================
   CEK AKSES MENU
================================ */
$query = "SELECT 1 FROM akses_menu 
          JOIN menu ON akses_menu.menu_id = menu.id 
          WHERE akses_menu.user_id = ? AND menu.file_menu = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("is", $user_id, $current_file);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows == 0) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini.'); window.location.href='dashboard.php';</script>";
    exit;
}

/* ===============================
   DATA USER (ATASAN)
================================ */
$qUser = $conn->prepare("SELECT nik, nama FROM users WHERE id = ?");
$qUser->bind_param("i", $user_id);
$qUser->execute();
$user = $qUser->get_result()->fetch_assoc();

/* ===============================
   PROSES ACC ATASAN
================================ */
if (isset($_POST['status_atasan'], $_POST['id_izin'])) {

    $id_izin = intval($_POST['id_izin']);
    $status_atasan = $_POST['status_atasan'];
    $waktu_acc_atasan = date('Y-m-d H:i:s');

    if (!in_array($status_atasan, ['disetujui','ditolak'])) {
        $_SESSION['flash_message'] = "Status tidak valid.";
        header("Location: acc_pulang_cepat_atasan.php");
        exit;
    }

    // Ambil detail izin
    $qIzinDetail = $conn->prepare("
        SELECT u.nama, u.no_hp,
               izin.tanggal, izin.keperluan, izin.jam_pulang
        FROM izin_pulang_cepat izin
        JOIN users u ON izin.user_id = u.id
        WHERE izin.id = ?
    ");
    $qIzinDetail->bind_param("i", $id_izin);
    $qIzinDetail->execute();
    $resIzinDetail = $qIzinDetail->get_result();

    if ($resIzinDetail->num_rows == 0) {
        $_SESSION['flash_message'] = "❌ Data izin tidak ditemukan.";
        header("Location: acc_pulang_cepat_atasan.php");
        exit;
    }

    $rowIzin = $resIzinDetail->fetch_assoc();

    // Update status ACC atasan
    $qUpdate = $conn->prepare("
        UPDATE izin_pulang_cepat
        SET status_atasan = ?, 
            waktu_acc_atasan = ?, 
            acc_oleh_atasan = ?
        WHERE id = ?
    ");
    $qUpdate->bind_param(
        "ssii",
        $status_atasan,
        $waktu_acc_atasan,
        $user_id,
        $id_izin
    );

    if ($qUpdate->execute()) {

        $_SESSION['flash_message'] = "✅ Status ACC atasan berhasil diperbarui.";

        // Kirim WA ke karyawan
        if (!empty($rowIzin['no_hp'])) {
            $pesanWA  = "🏠 *JAWABAN IZIN PULANG CEPAT*\n";
            $pesanWA .= "Tanggal       : " . date('d-m-Y', strtotime($rowIzin['tanggal'])) . "\n";
            $pesanWA .= "Jam Pulang    : " . $rowIzin['jam_pulang'] . " WIB\n";
            $pesanWA .= "Status Atasan : " . ucfirst($status_atasan) . "\n";
            $pesanWA .= "Alasan        : " . $rowIzin['keperluan'] . "\n";
            $pesanWA .= "Atasan        : " . $user['nama'];

            sendWA($rowIzin['no_hp'], $pesanWA);
        }

    } else {
        $_SESSION['flash_message'] = "❌ Gagal memperbarui status ACC.";
    }

    header("Location: acc_pulang_cepat_atasan.php");
    exit;
}

/* ===============================
   FILTER & PAGINATION
================================ */
$tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-d');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

$where = "WHERE atasan_langsung = ? AND tanggal BETWEEN ? AND ?";

// Hitung total data
$countQuery = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM izin_pulang_cepat $where
");
$countQuery->bind_param("sss", $user['nama'], $tgl_awal, $tgl_akhir);
$countQuery->execute();
$totalData = $countQuery->get_result()->fetch_assoc()['total'];

$limit = 10;
$page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;
$totalPages = ceil($totalData / $limit);

// Ambil data izin
$qIzin = $conn->prepare("
    SELECT * FROM izin_pulang_cepat $where
    ORDER BY tanggal DESC, created_at DESC
    LIMIT ? OFFSET ?
");
$qIzin->bind_param(
    "sssii",
    $user['nama'],
    $tgl_awal,
    $tgl_akhir,
    $limit,
    $offset
);
$qIzin->execute();
$data_izin = $qIzin->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>ACC Izin Pulang Cepat Atasan</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<style>
.flash-center{
    position:fixed;top:20%;left:50%;
    transform:translate(-50%,-50%);
    z-index:1050;min-width:300px;
}
.izin-table{font-size:13px;white-space:nowrap;}
.izin-table th,.izin-table td{padding:6px 10px;}
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

<?php if (isset($_SESSION['flash_message'])): ?>
<div class="alert alert-info flash-center" id="flashMsg">
<?= htmlspecialchars($_SESSION['flash_message']) ?>
</div>
<?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<div class="card">
<div class="card-header">
<h4>Daftar Izin Pulang Cepat – Approval Atasan</h4>
</div>

<div class="card-body">

<form method="GET" class="form-inline mb-3">
<label class="mr-2">Periode:</label>
<input type="date" name="tgl_awal" value="<?= $tgl_awal ?>" class="form-control mr-2">
<input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" class="form-control mr-2">
<button class="btn btn-primary">
<i class="fas fa-filter"></i> Filter
</button>
</form>

<div class="table-responsive">
<table class="table table-bordered izin-table">
<thead class="thead-dark">
<tr class="text-center">
<th>No</th>
<th>Nama</th>
<th>NIK</th>
<th>Jabatan</th>
<th>Tanggal</th>
<th>Jam Pulang</th>
<th>Keperluan</th>
<th>Status Atasan</th>
<th>Status SDM</th>
<th>Aksi</th>
</tr>
</thead>
<tbody>

<?php if ($data_izin && $data_izin->num_rows > 0): ?>
<?php $no=$offset+1; while($izin=$data_izin->fetch_assoc()): ?>
<tr>
<td><?= $no++ ?></td>
<td><?= htmlspecialchars($izin['nama']) ?></td>
<td><?= htmlspecialchars($izin['nik']) ?></td>
<td><?= htmlspecialchars($izin['jabatan']) ?></td>
<td><?= date('d-m-Y',strtotime($izin['tanggal'])) ?></td>
<td><?= $izin['jam_pulang'] ?></td>
<td><?= nl2br(htmlspecialchars($izin['keperluan'])) ?></td>

<td class="text-center">
<?php
$badgeA = ($izin['status_atasan']=='disetujui')?'success':(($izin['status_atasan']=='ditolak')?'danger':'secondary');
echo "<span class='badge badge-$badgeA'>".ucfirst($izin['status_atasan'])."</span><br>";
echo "<small>".($izin['waktu_acc_atasan']?date('d-m-Y H:i',strtotime($izin['waktu_acc_atasan'])):'-')."</small>";
?>
</td>

<td class="text-center">
<?php
$badgeS = ($izin['status_sdm']=='disetujui')?'success':(($izin['status_sdm']=='ditolak')?'danger':'secondary');
echo "<span class='badge badge-$badgeS'>".ucfirst($izin['status_sdm'])."</span>";
?>
</td>

<td class="text-center">
<?php if($izin['status_atasan']=='pending'): ?>
<form method="POST" style="display:inline">
<input type="hidden" name="id_izin" value="<?= $izin['id'] ?>">
<button name="status_atasan" value="disetujui"
class="btn btn-sm btn-success"
onclick="return confirm('Setujui izin pulang cepat ini?')">
<i class="fas fa-check"></i>
</button>
</form>

<form method="POST" style="display:inline">
<input type="hidden" name="id_izin" value="<?= $izin['id'] ?>">
<button name="status_atasan" value="ditolak"
class="btn btn-sm btn-danger"
onclick="return confirm('Tolak izin pulang cepat ini?')">
<i class="fas fa-times"></i>
</button>
</form>
<?php else: ?>
-
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="10" class="text-center">Tidak ada data.</td></tr>
<?php endif; ?>

</tbody>
</table>
</div>

<?php if ($totalPages > 1): ?>
<nav>
<ul class="pagination justify-content-center">
<?php for($i=1;$i<=$totalPages;$i++): ?>
<li class="page-item <?=($i==$page)?'active':''?>">
<a class="page-link" href="?tgl_awal=<?=$tgl_awal?>&tgl_akhir=<?=$tgl_akhir?>&page=<?=$i?>">
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

<script src="assets/modules/jquery.min.js"></script>
<script src="assets/modules/popper.js"></script>
<script src="assets/modules/bootstrap/js/bootstrap.min.js"></script>
<script src="assets/modules/nicescroll/jquery.nicescroll.min.js"></script>
<script src="assets/modules/moment.min.js"></script>
<script src="assets/js/stisla.js"></script>
<script src="assets/js/scripts.js"></script>
<script src="assets/js/custom.js"></script>
<script>
$(function(){ setTimeout(()=>$("#flashMsg").fadeOut(),3000); });
</script>
</body>
</html>
