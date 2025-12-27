<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    echo "<script>alert('Anda belum login.'); window.location.href='login.php';</script>";
    exit;
}

$current_file = basename(__FILE__);


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

// Ambil data user SDM
$qUser = $conn->prepare("SELECT nik, nama FROM users WHERE id = ?");
$qUser->bind_param("i", $user_id);
$qUser->execute();
$resUser = $qUser->get_result();
$user = $resUser->fetch_assoc();


if (isset($_POST['simpan_kembali']) && isset($_POST['id_izin'])) {
    $id_izin = intval($_POST['id_izin']);
    $keterangan_kembali = trim($_POST['keterangan_kembali']);
    $jam_kembali_real = date('Y-m-d H:i:s');

    if ($keterangan_kembali == '') {
        $_SESSION['flash_message'] = "❌ Keterangan kembali wajib diisi.";
        header("Location: acc_keluar_sdm.php");
        exit;
    }

    $qUpdate = $conn->prepare("
        UPDATE izin_keluar 
        SET jam_kembali_real = ?, 
            keterangan_kembali = ?
        WHERE id = ?
    ");
    $qUpdate->bind_param("ssi", $jam_kembali_real, $keterangan_kembali, $id_izin);
    $qUpdate->execute();

    $_SESSION['flash_message'] = $qUpdate->affected_rows > 0
        ? "✅ Jam kembali & keterangan berhasil disimpan."
        : "❌ Gagal menyimpan data kembali.";

    header("Location: acc_keluar_sdm.php");
    exit;
}

// ==========================
// PROSES UPDATE ACC SDM (BARU - DENGAN CATATAN)
// ==========================
if (isset($_POST['status_sdm']) && isset($_POST['id_izin']) && isset($_POST['catatan_sdm'])) {
    $id_izin = intval($_POST['id_izin']);
    $status_sdm = $_POST['status_sdm'];
    $catatan_sdm = trim($_POST['catatan_sdm']);
    $waktu_acc_sdm = date('Y-m-d H:i:s');

    if (!in_array($status_sdm, ['disetujui','ditolak'])) {
        $_SESSION['flash_message'] = "❌ Status tidak valid.";
        header("Location: acc_keluar_sdm.php");
        exit;
    }

    if (empty($catatan_sdm)) {
        $_SESSION['flash_message'] = "❌ Catatan wajib dipilih.";
        header("Location: acc_keluar_sdm.php");
        exit;
    }

    // Update status SDM dengan catatan (TANPA CEK STATUS ATASAN)
    $qUpdate = $conn->prepare("
        UPDATE izin_keluar 
        SET status_sdm = ?, 
            waktu_acc_sdm = ?, 
            acc_oleh_sdm = ?,
            catatan_sdm = ?
        WHERE id = ?
    ");
    $qUpdate->bind_param("ssisi", $status_sdm, $waktu_acc_sdm, $user_id, $catatan_sdm, $id_izin);
    $qUpdate->execute();

    $_SESSION['flash_message'] = $qUpdate->affected_rows > 0 
        ? "✅ Status ACC SDM berhasil diperbarui." 
        : "❌ Gagal memperbarui status SDM.";
    
    header("Location: acc_keluar_sdm.php");
    exit;
}

// Filter pencarian & periode
$filterNama   = $_GET['nama'] ?? '';
$filterNik    = $_GET['nik'] ?? '';
$filterDari   = $_GET['dari'] ?? date('Y-m-d'); 
$filterSampai = $_GET['sampai'] ?? date('Y-m-d'); 

// ==========================
// PAGINATION SETTING
// ==========================
$limit = 7;
$page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page  = ($page < 1) ? 1 : $page;
$offset = ($page - 1) * $limit;

// Query untuk menghitung total data
$sqlCount = "SELECT COUNT(*) as total FROM izin_keluar WHERE tanggal BETWEEN ? AND ?";
$paramsCount = [$filterDari, $filterSampai];
$typesCount = "ss";

if (!empty($filterNama)) {
    $sqlCount .= " AND nama LIKE ?";
    $paramsCount[] = "%$filterNama%";
    $typesCount .= "s";
}

if (!empty($filterNik)) {
    $sqlCount .= " AND nik LIKE ?";
    $paramsCount[] = "%$filterNik%";
    $typesCount .= "s";
}

$qCount = $conn->prepare($sqlCount);
$qCount->bind_param($typesCount, ...$paramsCount);
$qCount->execute();
$resCount = $qCount->get_result();
$totalData = $resCount->fetch_assoc()['total'];
$totalPages = ceil($totalData / $limit);

// Query data dengan pagination
$sql = "SELECT * FROM izin_keluar WHERE tanggal BETWEEN ? AND ?";
$params = [$filterDari, $filterSampai];
$types = "ss";

if (!empty($filterNama)) {
    $sql .= " AND nama LIKE ?";
    $params[] = "%$filterNama%";
    $types .= "s";
}

if (!empty($filterNik)) {
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

// Function untuk generate URL dengan parameter
function buildPaginationUrl($page, $filterNama, $filterNik, $filterDari, $filterSampai) {
    $params = [
        'page' => $page,
        'nama' => $filterNama,
        'nik' => $filterNik,
        'dari' => $filterDari,
        'sampai' => $filterSampai
    ];
    return 'acc_keluar_sdm.php?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>ACC Izin Keluar SDM</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<style>
.flash-center { 
    position: fixed; top: 20%; left: 50%; transform: translate(-50%, -50%); 
    z-index: 1050; min-width: 300px; max-width: 90%; text-align: center; 
    padding: 15px; border-radius: 8px; font-weight: 500; 
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}
.izin-table { font-size: 13px; white-space: nowrap; }
.izin-table th, .izin-table td { padding: 6px 10px; vertical-align: middle; }
.pagination-info { font-size: 14px; color: #666; margin-bottom: 10px; }
.pagination { margin-top: 20px; }
.pagination .page-item.active .page-link { background-color: #6777ef; border-color: #6777ef; }
.pagination .page-link { color: #6777ef; }

/* Style untuk radio button catatan */
.catatan-option {
    border: 2px solid #e3e6f0;
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.3s;
}
.catatan-option:hover {
    border-color: #6777ef;
    background-color: #f8f9fc;
}
.catatan-option input[type="radio"] {
    margin-right: 10px;
}
.catatan-option.selected {
    border-color: #6777ef;
    background-color: #f0f2ff;
}
.catatan-label {
    display: inline-block;
    cursor: pointer;
    margin-bottom: 0;
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
<?= htmlspecialchars($_SESSION['flash_message']) ?>
</div>
<?php unset($_SESSION['flash_message']); endif; ?>

<div class="card">
<div class="card-header">
<h4 class="mb-0">Daftar Izin Keluar - Approval SDM</h4>
</div>
<div class="card-body">

<!-- Form Filter -->
<form class="form-inline mb-3" method="get">
<input type="text" class="form-control mr-2" name="nama" placeholder="Nama" value="<?= htmlspecialchars($filterNama) ?>">
<input type="text" class="form-control mr-2" name="nik" placeholder="NIK" value="<?= htmlspecialchars($filterNik) ?>">
<input type="date" class="form-control mr-2" name="dari" value="<?= htmlspecialchars($filterDari) ?>">
<input type="date" class="form-control mr-2" name="sampai" value="<?= htmlspecialchars($filterSampai) ?>">
<button type="submit" class="btn btn-primary mr-2"><i class="fas fa-search"></i> Cari</button>
<a href="acc_keluar_sdm.php" class="btn btn-secondary mr-2">Reset</a>
<a href="cetak_pdf_sdm.php?dari=<?= $filterDari ?>&sampai=<?= $filterSampai ?>&nama=<?= urlencode($filterNama) ?>&nik=<?= urlencode($filterNik) ?>" target="_blank" class="btn btn-success"><i class="fas fa-print"></i> Cetak PDF</a>
</form>

<!-- Informasi Pagination -->
<div class="pagination-info">
    Menampilkan <?= min($offset + 1, $totalData) ?> - <?= min($offset + $limit, $totalData) ?> dari <?= $totalData ?> data
</div>

<div class="table-responsive">
<table class="table table-bordered izin-table">
<thead class="thead-dark text-center">
<tr>
<th>No</th><th>Nama</th><th>NIK</th><th>Jabatan</th><th>Tanggal</th>
<th>Jam Keluar</th><th>Jam Kembali</th><th>Jam Kembali Real</th>
<th>Keperluan</th><th>Status Atasan</th><th>Status SDM</th><th>Catatan SDM</th><th>Aksi</th>
</tr>
</thead>
<tbody>
<?php if($data_izin && $data_izin->num_rows>0):
$no = $offset + 1;
while($izin=$data_izin->fetch_assoc()): ?>
<tr>
<td class="text-center"><?= $no++ ?></td>
<td><?= htmlspecialchars($izin['nama']) ?></td>
<td><?= htmlspecialchars($izin['nik']) ?></td>
<td><?= htmlspecialchars($izin['jabatan']) ?></td>
<td><?= date('d-m-Y', strtotime($izin['tanggal'])) ?></td>
<td><?= htmlspecialchars($izin['jam_keluar']) ?></td>
<td><?= htmlspecialchars($izin['jam_kembali']) ?></td>
<td class="text-center">
<?php if (!empty($izin['jam_kembali_real'])): ?>
<?= date('d-m-Y H:i', strtotime($izin['jam_kembali_real'])) ?>
<?php elseif ($izin['status_sdm'] == 'disetujui'): ?>
<button type="button"
        class="btn btn-sm btn-info btn-kembali"
        data-id="<?= $izin['id'] ?>"
        data-nama="<?= htmlspecialchars($izin['nama']) ?>"
        data-jam="<?= $izin['jam_kembali'] ?>">
    <i class="fas fa-clock"></i>
</button>
<?php else: ?>
<span>-</span>
<?php endif; ?>
</td>
<td><?= htmlspecialchars($izin['keperluan']) ?></td>
<td class="text-center">
<?php
$badgeAtasan='secondary';
if($izin['status_atasan']=='disetujui') $badgeAtasan='success';
elseif($izin['status_atasan']=='pending') $badgeAtasan='warning';
echo "<span class='badge badge-$badgeAtasan'>".ucfirst($izin['status_atasan'])."</span><br>";
echo "<small>".($izin['waktu_acc_atasan']?date('d-m-Y H:i',strtotime($izin['waktu_acc_atasan'])):'-')."</small>";
?>
</td>
<td class="text-center">
<?php
$badge='secondary';
if($izin['status_sdm']=='disetujui') $badge='success';
elseif($izin['status_sdm']=='ditolak') $badge='danger';
echo "<span class='badge badge-$badge'>".ucfirst($izin['status_sdm'])."</span><br>";
echo "<small>".($izin['waktu_acc_sdm']?date('d-m-Y H:i',strtotime($izin['waktu_acc_sdm'])):'-')."</small>";
?>
</td>
<td class="text-center">
<?php 
if (!empty($izin['catatan_sdm'])) {
    echo '<small class="text-muted">'.htmlspecialchars($izin['catatan_sdm']).'</small>';
} else {
    echo '-';
}
?>
</td>
<td class="text-center">
<?php if($izin['status_sdm']=='pending'): ?>
<button type="button" 
        class="btn btn-sm btn-success btn-acc"
        data-id="<?= $izin['id'] ?>"
        data-nama="<?= htmlspecialchars($izin['nama']) ?>"
        data-status-atasan="<?= $izin['status_atasan'] ?>">
    <i class="fas fa-check"></i>
</button>
<button type="button" 
        class="btn btn-sm btn-danger btn-tolak"
        data-id="<?= $izin['id'] ?>"
        data-nama="<?= htmlspecialchars($izin['nama']) ?>">
    <i class="fas fa-times"></i>
</button>
<?php else: ?><span>-</span>
<?php endif; ?>
</td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="13" class="text-center">Tidak ada data izin keluar.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Pagination -->
<?php if($totalPages > 1): ?>
<nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= buildPaginationUrl($page - 1, $filterNama, $filterNik, $filterDari, $filterSampai) ?>">
                <span>&laquo;</span>
            </a>
        </li>

        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);

        if ($page <= 3) $end = min(5, $totalPages);
        if ($page >= $totalPages - 2) $start = max(1, $totalPages - 4);

        if ($start > 1) {
            echo '<li class="page-item"><a class="page-link" href="' . buildPaginationUrl(1, $filterNama, $filterNik, $filterDari, $filterSampai) . '">1</a></li>';
            if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }

        for ($i = $start; $i <= $end; $i++) {
            $active = ($i == $page) ? 'active' : '';
            echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . buildPaginationUrl($i, $filterNama, $filterNik, $filterDari, $filterSampai) . '">' . $i . '</a></li>';
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            echo '<li class="page-item"><a class="page-link" href="' . buildPaginationUrl($totalPages, $filterNama, $filterNik, $filterDari, $filterSampai) . '">' . $totalPages . '</a></li>';
        }
        ?>

        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= buildPaginationUrl($page + 1, $filterNama, $filterNik, $filterDari, $filterSampai) ?>">
                <span>&raquo;</span>
            </a>
        </li>
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

<!-- ================= MODAL APPROVAL (SETUJUI) ================= -->
<div class="modal fade" id="modalApproval" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="fas fa-check-circle"></i> Setujui Izin Keluar</h5>
          <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id_izin" id="approval_id">
          <input type="hidden" name="status_sdm" value="disetujui">

          <div class="alert alert-info">
            <strong>Nama:</strong> <span id="approval_nama"></span><br>
            <strong>Status Atasan:</strong> <span id="approval_status_atasan"></span>
          </div>

          <div class="form-group">
            <label class="font-weight-bold">Pilih Catatan Approval <span class="text-danger">*</span></label>
            
            <div class="catatan-option" onclick="selectCatatan(this, 'cat1')">
              <input type="radio" name="catatan_sdm" value="Sudah ACC Atasan" id="cat1" required>
              <label class="catatan-label" for="cat1">
                <i class="fas fa-check-double text-success"></i> Sudah ACC Atasan
              </label>
            </div>

            <div class="catatan-option" onclick="selectCatatan(this, 'cat2')">
              <input type="radio" name="catatan_sdm" value="Atasan Tidak Hadir / Libur" id="cat2" required>
              <label class="catatan-label" for="cat2">
                <i class="fas fa-calendar-times text-warning"></i> Atasan Tidak Hadir / Libur
              </label>
            </div>

            <div class="catatan-option" onclick="selectCatatan(this, 'cat3')">
              <input type="radio" name="catatan_sdm" value="Atasan Tidak Hadir / Cuti" id="cat3" required>
              <label class="catatan-label" for="cat3">
                <i class="fas fa-umbrella-beach text-info"></i> Atasan Tidak Hadir / Cuti
              </label>
            </div>

            <div class="catatan-option" onclick="selectCatatan(this, 'cat4')">
              <input type="radio" name="catatan_sdm" value="Atasan Tidak Di Tempat" id="cat4" required>
              <label class="catatan-label" for="cat4">
                <i class="fas fa-user-slash text-secondary"></i> Atasan Tidak Di Tempat
              </label>
            </div>
          </div>

          <small class="text-muted">
            <i class="fas fa-info-circle"></i> Pilih salah satu alasan untuk menyetujui izin ini
          </small>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success">
            <i class="fas fa-check"></i> Setujui Izin
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ================= MODAL TOLAK ================= -->
<div class="modal fade" id="modalTolak" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title"><i class="fas fa-times-circle"></i> Tolak Izin Keluar</h5>
          <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id_izin" id="tolak_id">
          <input type="hidden" name="status_sdm" value="ditolak">

          <div class="alert alert-warning">
            <strong>Nama:</strong> <span id="tolak_nama"></span>
          </div>

          <div class="form-group">
            <label class="font-weight-bold">Alasan Penolakan <span class="text-danger">*</span></label>
            <textarea name="catatan_sdm" 
                      class="form-control" 
                      rows="3" 
                      placeholder="Contoh: Tidak ada pengganti, jadwal sudah penuh, dll"
                      required></textarea>
          </div>

          <small class="text-muted">
            <i class="fas fa-exclamation-triangle"></i> Tuliskan alasan penolakan dengan jelas
          </small>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-times"></i> Tolak Izin
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ================= MODAL UPDATE JAM KEMBALI ================= -->
<div class="modal fade" id="modalKembali" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-md modal-dialog-centered" role="document">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Update Jam Kembali</h5>
          <button type="button" class="close" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id_izin" id="modal_id_izin">

          <div class="form-group">
            <label>Nama</label>
            <input type="text" id="modal_nama" class="form-control" readonly>
          </div>

          <div class="form-group">
            <label>Jam Kembali Estimasi</label>
            <input type="text" id="modal_jam" class="form-control" readonly>
          </div>

          <div class="form-group">
            <label>Keterangan Kembali <span class="text-danger">*</span></label>
            <textarea name="keterangan_kembali"
                      class="form-control"
                      rows="3"
                      placeholder="Contoh: Sudah kembali ke unit kerja"
                      required></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" name="simpan_kembali" class="btn btn-primary">
            <i class="fas fa-save"></i> Simpan
          </button>
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
$(document).ready(function(){
    setTimeout(function() { $("#flashMsg").fadeOut("slow"); }, 3000);
});

// Modal Jam Kembali
$(document).on('click', '.btn-kembali', function () {
    var id   = $(this).data('id');
    var nama = $(this).data('nama');
    var jam  = $(this).data('jam');

    $('#modal_id_izin').val(id);
    $('#modal_nama').val(nama);
    $('#modal_jam').val(jam);

    $('#modalKembali').modal('show');
});

// Modal Approval (Setujui)
$(document).on('click', '.btn-acc', function () {
    var id = $(this).data('id');
    var nama = $(this).data('nama');
    var statusAtasan = $(this).data('status-atasan');
    
    $('#approval_id').val(id);
    $('#approval_nama').text(nama);
    
    var badgeClass = 'badge-warning';
    var statusText = statusAtasan.charAt(0).toUpperCase() + statusAtasan.slice(1);
    
    if (statusAtasan === 'disetujui') {
        badgeClass = 'badge-success';
    } else if (statusAtasan === 'ditolak') {
        badgeClass = 'badge-danger';
    }
    
    $('#approval_status_atasan').html('<span class="badge ' + badgeClass + '">' + statusText + '</span>');
    
    // Reset radio buttons
    $('input[name="catatan_sdm"]').prop('checked', false);
    $('.catatan-option').removeClass('selected');
    
    $('#modalApproval').modal('show');
});

// Modal Tolak
$(document).on('click', '.btn-tolak', function () {
    var id = $(this).data('id');
    var nama = $(this).data('nama');
    
    $('#tolak_id').val(id);
    $('#tolak_nama').text(nama);
    
    $('#modalTolak').modal('show');
});

// Function untuk select catatan dengan visual feedback
function selectCatatan(element, radioId) {
    // Remove selected class from all options
    $('.catatan-option').removeClass('selected');
    
    // Add selected class to clicked option
    $(element).addClass('selected');
    
    // Check the radio button
    $('#' + radioId).prop('checked', true);
}
</script>

</body>
</html>