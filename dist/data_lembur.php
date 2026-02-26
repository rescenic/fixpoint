<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    echo "<script>alert('Anda belum login');location.href='login.php';</script>";
    exit;
}

$current_file = basename(__FILE__);
$stmt = $conn->prepare("SELECT 1 FROM akses_menu JOIN menu ON akses_menu.menu_id = menu.id WHERE akses_menu.user_id=? AND menu.file_menu=?");
$stmt->bind_param("is", $user_id, $current_file);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
    echo "<script>alert('Anda tidak memiliki akses');location.href='dashboard.php';</script>";
    exit;
}

$filterNama   = $_GET['nama']      ?? '';
$filterNik    = $_GET['nik']       ?? '';
$filterUnit   = $_GET['unit']      ?? '';
$filterStatus = $_GET['status']    ?? '';
$tgl_awal     = $_GET['tgl_awal']  ?? date('Y-m-01');
$tgl_akhir    = $_GET['tgl_akhir'] ?? date('Y-m-d');

$limit  = 10;
$page   = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$where  = "WHERE DATE(lp.lembur_mulai) BETWEEN ? AND ?";
$params = [$tgl_awal, $tgl_akhir];
$types  = "ss";

if ($filterNama)   { $where .= " AND lp.nama LIKE ?"; $params[] = "%$filterNama%"; $types .= "s"; }
if ($filterNik)    { $where .= " AND lp.nik LIKE ?";  $params[] = "%$filterNik%";  $types .= "s"; }
if ($filterUnit)   { $where .= " AND lp.unit LIKE ?"; $params[] = "%$filterUnit%"; $types .= "s"; }
if ($filterStatus === 'pending')   $where .= " AND (lp.status_atasan='pending' OR lp.status_sdm='pending')";
elseif ($filterStatus === 'disetujui') $where .= " AND lp.status_atasan='disetujui' AND lp.status_sdm='disetujui'";
elseif ($filterStatus === 'ditolak')   $where .= " AND (lp.status_atasan='ditolak' OR lp.status_sdm='ditolak')";

$qCount = $conn->prepare("SELECT COUNT(*) total FROM lembur_pengajuan lp LEFT JOIN lembur_laporan ll ON ll.lembur_id = lp.id $where");
$qCount->bind_param($types, ...$params);
$qCount->execute();
$totalData = $qCount->get_result()->fetch_assoc()['total'];
$totalPage = ceil($totalData / $limit);

$sqlData = "SELECT lp.*, ll.id AS laporan_id, ll.jenis_pekerjaan_detail, ll.aktual_pelaksanaan, ll.keterangan AS ket_laporan, u_atasan.nama AS nama_atasan, u_sdm.nama AS nama_sdm FROM lembur_pengajuan lp LEFT JOIN lembur_laporan ll ON ll.lembur_id = lp.id LEFT JOIN users u_atasan ON u_atasan.id = lp.acc_oleh_atasan LEFT JOIN users u_sdm ON u_sdm.id = lp.acc_oleh_sdm $where ORDER BY lp.created_at DESC LIMIT ? OFFSET ?";
$qData = $conn->prepare($sqlData);
$qData->bind_param($types . "ii", ...[...$params, $limit, $offset]);
$qData->execute();
$data_lembur = $qData->get_result();

$qSum = $conn->prepare("SELECT COUNT(*) AS total, SUM(total_jam) AS total_jam, SUM(CASE WHEN status_atasan='disetujui' AND status_sdm='disetujui' THEN 1 ELSE 0 END) AS approved, SUM(CASE WHEN status_atasan='pending' OR status_sdm='pending' THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN status_atasan='ditolak' OR status_sdm='ditolak' THEN 1 ELSE 0 END) AS ditolak FROM lembur_pengajuan lp $where");
$qSum->bind_param($types, ...$params);
$qSum->execute();
$summary = $qSum->get_result()->fetch_assoc();

$qUnit = mysqli_query($conn, "SELECT DISTINCT unit FROM lembur_pengajuan ORDER BY unit ASC");

function pageUrl($p,$nama,$nik,$unit,$status,$a,$b){ return 'data_lembur.php?'.http_build_query(['page'=>$p,'nama'=>$nama,'nik'=>$nik,'unit'=>$unit,'status'=>$status,'tgl_awal'=>$a,'tgl_akhir'=>$b]); }
function printUrl($nama,$nik,$unit,$status,$a,$b){ return 'cetak_lembur.php?'.http_build_query(['nama'=>$nama,'nik'=>$nik,'unit'=>$unit,'status'=>$status,'tgl_awal'=>$a,'tgl_akhir'=>$b]); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Data Lembur Karyawan</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<style>
.lembur-table { font-size: 12px; }
.lembur-table th { vertical-align: middle !important; text-align: center; padding: 7px 8px; white-space: nowrap; }
.lembur-table td { vertical-align: middle !important; padding: 6px 8px; }

/* FIX UTAMA: Nama karyawan tidak menumpuk */
.td-nama           { white-space: nowrap; }
.td-nama .nama-utama { font-weight: 600; font-size: 12px; }
.td-nama .nama-sub   { font-size: 10px; color: #888; }

.td-waktu { white-space: nowrap; text-align: center; font-size: 11px; }
.approval-cell { font-size: 11px; line-height: 1.5; }
.badge-status { font-size: 10px; padding: 3px 7px; }

/* Summary */
.card-summary { border-left: 4px solid; border-radius: 6px; }
.card-summary.total   { border-color: #6c757d; }
.card-summary.approve { border-color: #28a745; }
.card-summary.pending { border-color: #ffc107; }
.card-summary.tolak   { border-color: #dc3545; }
.summary-val { font-size: 1.6rem; font-weight: 700; }

.flash-center {
    position: fixed; top: 20%; left: 50%;
    transform: translate(-50%, -50%);
    z-index: 1050; min-width: 280px; text-align: center;
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
<div class="alert alert-info flash-center" id="flashMsg"><?= $_SESSION['flash_message'] ?></div>
<?php unset($_SESSION['flash_message']); endif; ?>

<!-- SUMMARY CARDS -->
<div class="row mb-3">
    <div class="col-6 col-md-3 mb-2">
        <div class="card card-summary total p-3 h-100">
            <div class="text-muted small mb-1"><i class="fas fa-list-alt mr-1"></i>Total Pengajuan</div>
            <div class="summary-val text-secondary"><?= $summary['total'] ?></div>
            <div class="small text-muted"><?= number_format($summary['total_jam'] ?? 0, 1) ?> Jam</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card card-summary approve p-3 h-100">
            <div class="text-muted small mb-1"><i class="fas fa-check-circle mr-1"></i>Disetujui</div>
            <div class="summary-val text-success"><?= $summary['approved'] ?></div>
            <div class="small text-muted">Atasan &amp; SDM</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card card-summary pending p-3 h-100">
            <div class="text-muted small mb-1"><i class="fas fa-clock mr-1"></i>Menunggu</div>
            <div class="summary-val text-warning"><?= $summary['pending'] ?></div>
            <div class="small text-muted">Belum di-ACC</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card card-summary tolak p-3 h-100">
            <div class="text-muted small mb-1"><i class="fas fa-times-circle mr-1"></i>Ditolak</div>
            <div class="summary-val text-danger"><?= $summary['ditolak'] ?></div>
            <div class="small text-muted">Atasan / SDM</div>
        </div>
    </div>
</div>

<!-- CARD UTAMA -->
<div class="card">
<div class="card-header d-flex justify-content-between align-items-center">
    <h4 class="mb-0"><i class="fas fa-table mr-2"></i>Data Lembur Karyawan</h4>
    <div>
        <a href="<?= printUrl($filterNama,$filterNik,$filterUnit,$filterStatus,$tgl_awal,$tgl_akhir) ?>"
           target="_blank" class="btn btn-sm btn-danger mr-1">
            <i class="fas fa-file-pdf mr-1"></i> Cetak PDF
        </a>
        <a href="data_lembur.php?export=1&<?= http_build_query(['nama'=>$filterNama,'nik'=>$filterNik,'unit'=>$filterUnit,'status'=>$filterStatus,'tgl_awal'=>$tgl_awal,'tgl_akhir'=>$tgl_akhir]) ?>"
           class="btn btn-sm btn-success">
            <i class="fas fa-file-excel mr-1"></i> Export Excel
        </a>
    </div>
</div>

<div class="card-body">

<!-- FILTER -->
<form method="get" class="mb-3">
<div class="row">
    <div class="col-md-2 mb-2">
        <input type="text" name="nama" class="form-control form-control-sm" placeholder="Nama" value="<?= htmlspecialchars($filterNama) ?>">
    </div>
    <div class="col-md-2 mb-2">
        <input type="text" name="nik" class="form-control form-control-sm" placeholder="NIK" value="<?= htmlspecialchars($filterNik) ?>">
    </div>
    <div class="col-md-2 mb-2">
        <select name="unit" class="form-control form-control-sm">
            <option value="">-- Semua Unit --</option>
            <?php while($u = mysqli_fetch_assoc($qUnit)): ?>
            <option value="<?= htmlspecialchars($u['unit']) ?>" <?= ($filterUnit==$u['unit'])?'selected':'' ?>><?= htmlspecialchars($u['unit']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="col-md-2 mb-2">
        <select name="status" class="form-control form-control-sm">
            <option value="">-- Semua Status --</option>
            <option value="disetujui" <?= $filterStatus=='disetujui'?'selected':'' ?>>Disetujui</option>
            <option value="pending"   <?= $filterStatus=='pending'?'selected':'' ?>>Pending</option>
            <option value="ditolak"   <?= $filterStatus=='ditolak'?'selected':'' ?>>Ditolak</option>
        </select>
    </div>
    <div class="col-md-2 mb-2">
        <input type="date" name="tgl_awal"  class="form-control form-control-sm" value="<?= $tgl_awal ?>">
    </div>
    <div class="col-md-2 mb-2">
        <input type="date" name="tgl_akhir" class="form-control form-control-sm" value="<?= $tgl_akhir ?>">
    </div>
</div>
<button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
<a href="data_lembur.php" class="btn btn-secondary btn-sm ml-1"><i class="fas fa-redo"></i> Reset</a>
</form>

<p class="text-muted small mb-2">Menampilkan <strong><?= $totalData ?></strong> data</p>

<!-- TABEL -->
<div class="table-responsive">
<table class="table table-bordered table-hover lembur-table">
<thead class="thead-dark">
<tr>
    <th rowspan="2" style="vertical-align:middle; width:40px">No</th>
    <th rowspan="2" style="vertical-align:middle">No Surat</th>
    <th rowspan="2" style="vertical-align:middle">Nama Karyawan</th>
    <th rowspan="2" style="vertical-align:middle">Unit</th>
    <th rowspan="2" style="vertical-align:middle">Waktu Lembur</th>
    <th rowspan="2" style="vertical-align:middle; width:70px">Total Jam</th>
    <th rowspan="2" style="vertical-align:middle">Jenis Pekerjaan</th>
    <th colspan="2" class="text-center">Approval</th>
    <th rowspan="2" style="vertical-align:middle">Catatan SDM</th>
    <th rowspan="2" style="vertical-align:middle; width:80px">Laporan</th>
    <th rowspan="2" style="vertical-align:middle; width:55px">Aksi</th>
</tr>
<tr>
    <th style="min-width:120px">Atasan</th>
    <th style="min-width:120px">SDM</th>
</tr>
</thead>
<tbody>
<?php
$no = $offset + 1;
if ($data_lembur->num_rows > 0):
while($r = $data_lembur->fetch_assoc()):
    $ba = $r['status_atasan']=='disetujui'?'success':($r['status_atasan']=='ditolak'?'danger':'warning');
    $bs = $r['status_sdm']=='disetujui'?'success':($r['status_sdm']=='ditolak'?'danger':'warning');
?>
<tr>
    <td class="text-center"><?= $no++ ?></td>
    <td style="white-space:nowrap"><small><?= htmlspecialchars($r['no_surat']) ?></small></td>

    <!-- FIX: Nama tidak menumpuk pakai nowrap + struktur sederhana -->
    <td class="td-nama">
        <span class="nama-utama"><?= htmlspecialchars($r['nama']) ?></span><br>
        <span class="nama-sub"><?= htmlspecialchars($r['nik']) ?></span>
        <?php if($r['jabatan']): ?>
        <span class="nama-sub"> &bull; <?= htmlspecialchars($r['jabatan']) ?></span>
        <?php endif; ?>
    </td>

    <td style="white-space:nowrap"><small><?= htmlspecialchars($r['unit']) ?></small></td>

    <td class="td-waktu">
        <?= date('d-m-Y H:i', strtotime($r['lembur_mulai'])) ?><br>
        <small class="text-muted">s/d</small><br>
        <?= date('d-m-Y H:i', strtotime($r['lembur_selesai'])) ?>
    </td>

    <td class="text-center">
        <strong><?= $r['total_jam'] ?></strong><br>
        <small class="text-muted">Jam</small>
    </td>

    <td style="max-width:150px">
        <small><?= htmlspecialchars($r['jenis_pekerjaan']) ?></small>
        <?php if($r['dasar_pengajuan']): ?>
        <br><small class="text-muted" style="font-size:10px"><i class="fas fa-info-circle"></i> <?= htmlspecialchars(substr($r['dasar_pengajuan'],0,50)).(strlen($r['dasar_pengajuan'])>50?'...':'') ?></small>
        <?php endif; ?>
    </td>

    <td class="approval-cell text-center">
        <span class="badge badge-<?= $ba ?> badge-status d-block mb-1"><?= ucfirst($r['status_atasan']) ?></span>
        <?php if($r['waktu_acc_atasan']): ?><small class="text-muted d-block"><?= date('d-m-Y',strtotime($r['waktu_acc_atasan'])) ?></small><?php endif; ?>
        <?php if($r['nama_atasan']): ?><small class="text-muted d-block" style="font-size:10px">oleh: <?= htmlspecialchars($r['nama_atasan']) ?></small><?php endif; ?>
    </td>

    <td class="approval-cell text-center">
        <span class="badge badge-<?= $bs ?> badge-status d-block mb-1"><?= ucfirst($r['status_sdm']) ?></span>
        <?php if($r['waktu_acc_sdm']): ?><small class="text-muted d-block"><?= date('d-m-Y',strtotime($r['waktu_acc_sdm'])) ?></small><?php endif; ?>
        <?php if($r['nama_sdm']): ?><small class="text-muted d-block" style="font-size:10px">oleh: <?= htmlspecialchars($r['nama_sdm']) ?></small><?php endif; ?>
    </td>

    <td><small><?= $r['catatan_sdm'] ? htmlspecialchars($r['catatan_sdm']) : '<span class="text-muted">-</span>' ?></small></td>

    <td class="text-center">
        <?php if($r['laporan_id']): ?>
            <span class="badge badge-success badge-status d-block mb-1"><i class="fas fa-check"></i> Ada</span>
            <button class="btn btn-xs btn-outline-info btn-detail-laporan" style="font-size:10px;padding:2px 6px"
                    data-jenis="<?= htmlspecialchars($r['jenis_pekerjaan_detail']??'') ?>"
                    data-aktual="<?= htmlspecialchars($r['aktual_pelaksanaan']??'') ?>"
                    data-ket="<?= htmlspecialchars($r['ket_laporan']??'') ?>">
                <i class="fas fa-eye"></i> Lihat
            </button>
        <?php else: ?>
            <span class="badge badge-secondary badge-status">Belum</span>
        <?php endif; ?>
    </td>

    <td class="text-center">
        <button class="btn btn-info btn-sm btn-detail"
                data-no="<?= htmlspecialchars($r['no_surat']) ?>"
                data-nama="<?= htmlspecialchars($r['nama']) ?>"
                data-nik="<?= htmlspecialchars($r['nik']) ?>"
                data-jabatan="<?= htmlspecialchars($r['jabatan']??'') ?>"
                data-unit="<?= htmlspecialchars($r['unit']) ?>"
                data-mulai="<?= date('d-m-Y H:i',strtotime($r['lembur_mulai'])) ?>"
                data-selesai="<?= date('d-m-Y H:i',strtotime($r['lembur_selesai'])) ?>"
                data-jam="<?= $r['total_jam'] ?>"
                data-jenis="<?= htmlspecialchars($r['jenis_pekerjaan']) ?>"
                data-dasar="<?= htmlspecialchars($r['dasar_pengajuan']) ?>"
                data-aktual="<?= htmlspecialchars($r['aktual_pekerjaan']??'') ?>"
                data-tgl="<?= date('d-m-Y',strtotime($r['tanggal_pengajuan'])) ?>">
            <i class="fas fa-eye"></i>
        </button>
    </td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="12" class="text-center py-4 text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Tidak ada data lembur ditemukan</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- PAGINATION -->
<?php if($totalPage > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center flex-wrap">
    <?php if($page>1): ?><li class="page-item"><a class="page-link" href="<?= pageUrl($page-1,$filterNama,$filterNik,$filterUnit,$filterStatus,$tgl_awal,$tgl_akhir) ?>">&laquo;</a></li><?php endif; ?>
    <?php for($i=max(1,$page-2);$i<=min($totalPage,$page+2);$i++): ?>
    <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="<?= pageUrl($i,$filterNama,$filterNik,$filterUnit,$filterStatus,$tgl_awal,$tgl_akhir) ?>"><?= $i ?></a></li>
    <?php endfor; ?>
    <?php if($page<$totalPage): ?><li class="page-item"><a class="page-link" href="<?= pageUrl($page+1,$filterNama,$filterNik,$filterUnit,$filterStatus,$tgl_awal,$tgl_akhir) ?>">&raquo;</a></li><?php endif; ?>
</ul></nav>
<?php endif; ?>

</div></div>
</div></section></div></div></div>

<!-- MODAL DETAIL PENGAJUAN -->
<div class="modal fade" id="modalDetail">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-info text-white">
    <h5 class="modal-title"><i class="fas fa-file-alt mr-2"></i>Detail Pengajuan Lembur</h5>
    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
<div class="row">
    <div class="col-md-6">
        <table class="table table-sm table-borderless">
            <tr><td width="130"><strong>No Surat</strong></td><td id="d_no"></td></tr>
            <tr><td><strong>Tanggal</strong></td><td id="d_tgl"></td></tr>
            <tr><td><strong>Nama</strong></td><td id="d_nama"></td></tr>
            <tr><td><strong>NIK</strong></td><td id="d_nik"></td></tr>
            <tr><td><strong>Jabatan</strong></td><td id="d_jabatan"></td></tr>
            <tr><td><strong>Unit Kerja</strong></td><td id="d_unit"></td></tr>
        </table>
    </div>
    <div class="col-md-6">
        <table class="table table-sm table-borderless">
            <tr><td width="130"><strong>Mulai</strong></td><td id="d_mulai"></td></tr>
            <tr><td><strong>Selesai</strong></td><td id="d_selesai"></td></tr>
            <tr><td><strong>Total Jam</strong></td><td id="d_jam"></td></tr>
            <tr><td><strong>Jenis</strong></td><td id="d_jenis"></td></tr>
        </table>
    </div>
</div>
<hr>
<div class="row">
    <div class="col-md-6"><p><strong>Dasar Pengajuan:</strong></p><p id="d_dasar" class="text-muted"></p></div>
    <div class="col-md-6"><p><strong>Aktual Pekerjaan:</strong></p><p id="d_aktual" class="text-muted"></p></div>
</div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-dismiss="modal">Tutup</button></div>
</div></div></div>

<!-- MODAL DETAIL LAPORAN -->
<div class="modal fade" id="modalDetailLaporan">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-success text-white">
    <h5 class="modal-title"><i class="fas fa-clipboard-check mr-2"></i>Detail Laporan Lembur</h5>
    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
    <table class="table table-sm table-borderless">
        <tr><td width="160"><strong>Jenis Pekerjaan</strong></td><td id="dl_jenis"></td></tr>
        <tr><td><strong>Aktual Pelaksanaan</strong></td><td id="dl_aktual"></td></tr>
        <tr><td><strong>Keterangan</strong></td><td id="dl_ket"></td></tr>
    </table>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-dismiss="modal">Tutup</button></div>
</div></div></div>

<script src="assets/modules/jquery.min.js"></script>
<script src="assets/modules/popper.js"></script>
<script src="assets/modules/bootstrap/js/bootstrap.min.js"></script>
<script src="assets/modules/nicescroll/jquery.nicescroll.min.js"></script>
<script src="assets/modules/moment.min.js"></script>
<script src="assets/js/stisla.js"></script>
<script src="assets/js/scripts.js"></script>
<script src="assets/js/custom.js"></script>
<script>
setTimeout(function(){ $('#flashMsg').fadeOut(500); }, 3000);

$(document).on('click', '.btn-detail', function(){
    var d = $(this).data();
    $('#d_no').text(d.no); $('#d_tgl').text(d.tgl); $('#d_nama').text(d.nama);
    $('#d_nik').text(d.nik); $('#d_jabatan').text(d.jabatan||'-'); $('#d_unit').text(d.unit);
    $('#d_mulai').text(d.mulai); $('#d_selesai').text(d.selesai); $('#d_jam').text(d.jam+' Jam');
    $('#d_jenis').text(d.jenis); $('#d_dasar').text(d.dasar||'-'); $('#d_aktual').text(d.aktual||'-');
    $('#modalDetail').modal('show');
});

$(document).on('click', '.btn-detail-laporan', function(){
    var d = $(this).data();
    $('#dl_jenis').text(d.jenis||'-'); $('#dl_aktual').text(d.aktual||'-'); $('#dl_ket').text(d.ket||'-');
    $('#modalDetailLaporan').modal('show');
});
</script>
</body>
</html>