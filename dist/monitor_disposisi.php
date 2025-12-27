<?php
include 'security.php';
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'];
$current_file = basename(__FILE__);

// =======================
// CEK AKSES MENU
// =======================
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

// =======================
// FILTER TANGGAL (DEFAULT HARI INI)
// =======================
$tgl_dari   = $_GET['tgl_dari']   ?? date('Y-m-d');
$tgl_sampai = $_GET['tgl_sampai'] ?? date('Y-m-d');

// =======================
// PAGINATION
// =======================
$limit  = 10;
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// =======================
// HITUNG TOTAL DATA
// =======================
$qTotal = mysqli_query($conn,"
  SELECT COUNT(*) AS total
  FROM surat_masuk
  WHERE tgl_terima BETWEEN '$tgl_dari' AND '$tgl_sampai'
");
$total_data  = mysqli_fetch_assoc($qTotal)['total'];
$total_page  = ceil($total_data / $limit);

// =======================
// AMBIL DATA SURAT + JUMLAH DISPOSISI
// =======================
$data = mysqli_query($conn,"
  SELECT sm.*,
         (SELECT COUNT(*) FROM disposisi d WHERE d.surat_masuk_id = sm.id) AS jml_disposisi
  FROM surat_masuk sm
  WHERE sm.tgl_terima BETWEEN '$tgl_dari' AND '$tgl_sampai'
  ORDER BY sm.tgl_terima DESC
  LIMIT $offset,$limit
");

$modals = "";
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Monitor Disposisi</title>

<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">

<style>
.table th { background:#000;color:#fff;text-align:center;white-space:nowrap; }
.table td { vertical-align:middle;white-space:nowrap; }
.modal-backdrop { z-index:1040!important; }
.modal { z-index:1050!important; }
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
    <h4><i class="fas fa-clipboard-list"></i> Monitor Disposisi Surat</h4>
  </div>

  <div class="card-body">

    <!-- FILTER -->
    <form method="GET" class="form-inline mb-3">
      <label class="mr-2">Dari</label>
      <input type="date" name="tgl_dari" value="<?= $tgl_dari ?>" class="form-control mr-3">

      <label class="mr-2">Sampai</label>
      <input type="date" name="tgl_sampai" value="<?= $tgl_sampai ?>" class="form-control mr-3">

      <button class="btn btn-primary mr-2">
        <i class="fas fa-filter"></i> Tampilkan
      </button>

      <a href="monitor_disposisi.php" class="btn btn-secondary">
        <i class="fas fa-redo"></i> Reset
      </a>
    </form>

    <!-- TABLE -->
    <div class="table-responsive">
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>No</th>
            <th>No Surat</th>
            <th>Tgl Terima</th>
            <th>Pengirim</th>
            <th>Perihal</th>
            <th>Disposisi Ke</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>

<?php if (mysqli_num_rows($data) > 0): ?>
<?php $no=$offset+1; while($s=mysqli_fetch_assoc($data)): ?>

<tr>
  <td class="text-center"><?= $no++ ?></td>
  <td><?= htmlspecialchars($s['no_surat']) ?></td>
  <td><?= date('d-m-Y',strtotime($s['tgl_terima'])) ?></td>
  <td><?= htmlspecialchars($s['pengirim']) ?></td>
  <td><?= htmlspecialchars($s['perihal']) ?></td>
  <td><?= htmlspecialchars($s['disposisi_ke'] ?: '-') ?></td>
  <td class="text-center">
    <?php if ($s['jml_disposisi'] > 0): ?>
      <span class="badge badge-success">Sudah Ditindaklanjuti</span>
    <?php else: ?>
      <span class="badge badge-warning">Menunggu</span>
    <?php endif; ?>
  </td>
  <td class="text-center">
    <button class="btn btn-sm btn-info"
            data-toggle="modal"
            data-target="#lihat<?= $s['id'] ?>">
      <i class="fas fa-eye"></i> Detail
    </button>
  </td>
</tr>

<?php
// =======================
// RIWAYAT DISPOSISI
// =======================
$qDisp = mysqli_query($conn,"
  SELECT d.*, u.nama
  FROM disposisi d
  JOIN users u ON d.disposisi_oleh = u.id
  WHERE d.surat_masuk_id = '{$s['id']}'
  ORDER BY d.tanggal_disposisi DESC
");

$isi = '';
while ($d = mysqli_fetch_assoc($qDisp)) {
  $isi .= "
  <tr>
    <td>".date('d-m-Y H:i',strtotime($d['tanggal_disposisi']))."</td>
    <td>".htmlspecialchars($d['nama'])."</td>
    <td>".nl2br(htmlspecialchars($d['instruksi']))."</td>
    <td>".nl2br(htmlspecialchars($d['catatan'] ?: '-'))."</td>
  </tr>";
}

$modals .= '
<div class="modal fade" id="lihat'.$s['id'].'" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">
          <i class="fas fa-eye"></i>
          Detail Disposisi – '.$s['no_surat'].'
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>

      <div class="modal-body table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="thead-dark">
            <tr>
              <th>Tanggal</th>
              <th>Oleh</th>
              <th>Instruksi</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody>
            '.($isi ?: '<tr><td colspan="4" class="text-center text-muted">Belum ada disposisi</td></tr>').'
          </tbody>
        </table>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">
          Tutup
        </button>
      </div>
    </div>
  </div>
</div>';
?>

<?php endwhile; ?>
<?php else: ?>
<tr>
  <td colspan="8" class="text-center text-muted">
    Tidak ada data.
  </td>
</tr>
<?php endif; ?>

        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($total_page > 1): ?>
    <nav>
      <ul class="pagination justify-content-center mt-3">
        <?php for($i=1;$i<=$total_page;$i++): ?>
          <li class="page-item <?= ($i==$page)?'active':'' ?>">
            <a class="page-link"
               href="?page=<?= $i ?>&tgl_dari=<?= $tgl_dari ?>&tgl_sampai=<?= $tgl_sampai ?>">
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

<!-- MODAL -->
<?= $modals ?>

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
