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

$qUser = $conn->prepare("SELECT nik, nama FROM users WHERE id = ?");
$qUser->bind_param("i", $user_id);
$qUser->execute();
$user = $qUser->get_result()->fetch_assoc();

// ============================================================
// PROSES: Update ACC SDM (Setujui / Tolak)
// ============================================================
if (isset($_POST['status_sdm']) && isset($_POST['id_izin']) && isset($_POST['catatan_sdm'])) {
    $id_izin     = intval($_POST['id_izin']);
    $status_sdm  = $_POST['status_sdm'];
    $catatan_sdm = trim($_POST['catatan_sdm']);
    $waktu_acc   = date('Y-m-d H:i:s');

    if (!in_array($status_sdm, ['disetujui', 'ditolak'])) {
        $_SESSION['flash_message'] = "❌ Status tidak valid.";
        header("Location: acc_keluar_sdm.php");
        exit;
    }

    if (empty($catatan_sdm)) {
        $_SESSION['flash_message'] = "❌ Catatan wajib dipilih / diisi.";
        header("Location: acc_keluar_sdm.php");
        exit;
    }

    $qUpdate = $conn->prepare("
        UPDATE izin_keluar 
        SET status_sdm    = ?, 
            waktu_acc_sdm = ?, 
            acc_oleh_sdm  = ?,
            catatan_sdm   = ?
        WHERE id = ?
    ");
    $qUpdate->bind_param("ssisi", $status_sdm, $waktu_acc, $user_id, $catatan_sdm, $id_izin);
    $qUpdate->execute();

    $_SESSION['flash_message'] = $qUpdate->affected_rows > 0
        ? "✅ Status ACC SDM berhasil diperbarui."
        : "❌ Gagal memperbarui status SDM.";

    header("Location: acc_keluar_sdm.php");
    exit;
}

// ============================================================
// Filter & Pagination
// ============================================================
$filterNama   = $_GET['nama']   ?? '';
$filterNik    = $_GET['nik']    ?? '';
$filterDari   = $_GET['dari']   ?? date('Y-m-d');
$filterSampai = $_GET['sampai'] ?? date('Y-m-d');

$limit  = 7;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Count
$sqlCount  = "SELECT COUNT(*) as total FROM izin_keluar WHERE tanggal BETWEEN ? AND ?";
$paramsC   = [$filterDari, $filterSampai];
$typesC    = "ss";
if (!empty($filterNama)) { $sqlCount .= " AND nama LIKE ?"; $paramsC[] = "%$filterNama%"; $typesC .= "s"; }
if (!empty($filterNik))  { $sqlCount .= " AND nik  LIKE ?"; $paramsC[] = "%$filterNik%";  $typesC .= "s"; }

$qCount = $conn->prepare($sqlCount);
$qCount->bind_param($typesC, ...$paramsC);
$qCount->execute();
$totalData  = $qCount->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalData / $limit);

// Data
$sql    = "SELECT * FROM izin_keluar WHERE tanggal BETWEEN ? AND ?";
$params = [$filterDari, $filterSampai];
$types  = "ss";
if (!empty($filterNama)) { $sql .= " AND nama LIKE ?"; $params[] = "%$filterNama%"; $types .= "s"; }
if (!empty($filterNik))  { $sql .= " AND nik  LIKE ?"; $params[] = "%$filterNik%";  $types .= "s"; }
$sql .= " ORDER BY tanggal DESC, created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit; $params[] = $offset; $types .= "ii";

$qIzin = $conn->prepare($sql);
$qIzin->bind_param($types, ...$params);
$qIzin->execute();
$data_izin = $qIzin->get_result();

function buildPaginationUrl($page, $filterNama, $filterNik, $filterDari, $filterSampai) {
    return 'acc_keluar_sdm.php?' . http_build_query([
        'page'   => $page, 'nama'   => $filterNama,
        'nik'    => $filterNik,   'dari'   => $filterDari,
        'sampai' => $filterSampai
    ]);
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
.pagination .page-item.active .page-link { background-color: #6777ef; border-color: #6777ef; }
.pagination .page-link { color: #6777ef; }

.catatan-option {
    border: 2px solid #e3e6f0; border-radius: 8px;
    padding: 12px 15px; margin-bottom: 10px;
    cursor: pointer; transition: all 0.3s;
}
.catatan-option:hover { border-color: #6777ef; background: #f8f9fc; }
.catatan-option.selected { border-color: #6777ef; background: #f0f2ff; }
.catatan-label { display: inline-block; cursor: pointer; margin-bottom: 0; }

/* Jam kembali sudah terisi oleh karyawan */
.jam-kembali-info {
    font-size: 0.8rem;
    line-height: 1.4;
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

<?php if (isset($_SESSION['flash_message'])): ?>
<div class="alert alert-info flash-center" id="flashMsg">
    <?= htmlspecialchars($_SESSION['flash_message']) ?>
</div>
<?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<div class="card">
<div class="card-header">
    <h4 class="mb-0">
        <i class="fas fa-clipboard-check mr-2"></i>Daftar Izin Keluar — Approval SDM
    </h4>
</div>
<div class="card-body">

<!-- Filter -->
<form class="form-inline mb-3" method="GET">
    <input type="text"  class="form-control mr-2" name="nama"   placeholder="Nama"  value="<?= htmlspecialchars($filterNama) ?>">
    <input type="text"  class="form-control mr-2" name="nik"    placeholder="NIK"   value="<?= htmlspecialchars($filterNik) ?>">
    <input type="date"  class="form-control mr-2" name="dari"   value="<?= htmlspecialchars($filterDari) ?>">
    <input type="date"  class="form-control mr-2" name="sampai" value="<?= htmlspecialchars($filterSampai) ?>">
    <button type="submit" class="btn btn-primary mr-2">
        <i class="fas fa-search"></i> Cari
    </button>
    <a href="acc_keluar_sdm.php" class="btn btn-secondary mr-2">Reset</a>
    <a href="cetak_pdf_sdm.php?dari=<?= $filterDari ?>&sampai=<?= $filterSampai ?>&nama=<?= urlencode($filterNama) ?>&nik=<?= urlencode($filterNik) ?>"
       target="_blank" class="btn btn-success">
        <i class="fas fa-print"></i> Cetak PDF
    </a>
</form>

<div class="pagination-info">
    Menampilkan <?= min($offset + 1, $totalData) ?> - <?= min($offset + $limit, $totalData) ?> dari <?= $totalData ?> data
</div>

<div class="table-responsive">
<table class="table table-bordered izin-table">
<thead class="thead-dark text-center">
<tr>
    <th>No</th>
    <th>Nama</th>
    <th>NIK</th>
    <th>Jabatan</th>
    <th>Tanggal</th>
    <th>Jam Keluar</th>
    <th>Jam Kembali<br>(Estimasi)</th>
    <th>Jam Kembali<br>(Real)</th>
    <th>Keperluan</th>
    <th>Status Atasan</th>
    <th>Status SDM</th>
    <th>Catatan SDM</th>
    <th>Aksi</th>
</tr>
</thead>
<tbody>
<?php if ($data_izin && $data_izin->num_rows > 0):
    $no = $offset + 1;
    while ($izin = $data_izin->fetch_assoc()): ?>
<tr>
    <td class="text-center"><?= $no++ ?></td>
    <td><?= htmlspecialchars($izin['nama']) ?></td>
    <td><?= htmlspecialchars($izin['nik']) ?></td>
    <td><?= htmlspecialchars($izin['jabatan']) ?></td>
    <td><?= date('d-m-Y', strtotime($izin['tanggal'])) ?></td>
    <td class="text-center"><?= htmlspecialchars($izin['jam_keluar']) ?></td>
    <td class="text-center"><?= $izin['jam_kembali'] ?: '-' ?></td>

    <!-- Jam Kembali Real — hanya tampil info, tidak ada tombol update (sudah pindah ke izin_keluar.php) -->
    <td class="text-center">
        <?php if (!empty($izin['jam_kembali_real'])): ?>
            <span class="badge badge-success px-2 py-1">
                <i class="fas fa-check-circle mr-1"></i>
                <?= date('H:i', strtotime($izin['jam_kembali_real'])) ?>
            </span>
            <br>
            <small class="text-muted jam-kembali-info">
                <?= date('d-m-Y', strtotime($izin['jam_kembali_real'])) ?>
            </small>
            <?php if (!empty($izin['keterangan_kembali'])): ?>
                <br>
                <small class="text-info jam-kembali-info">
                    <i class="fas fa-comment-dots mr-1"></i><?= htmlspecialchars($izin['keterangan_kembali']) ?>
                </small>
            <?php endif; ?>
        <?php elseif ($izin['status_sdm'] === 'disetujui'): ?>
            <!-- Sudah disetujui tapi belum konfirmasi kembali oleh karyawan -->
            <span class="badge badge-warning">
                <i class="fas fa-clock mr-1"></i>Menunggu Konfirmasi
            </span>
            <br>
            <small class="text-muted" style="font-size:0.75rem;">Karyawan belum<br>konfirmasi kembali</small>
        <?php else: ?>
            <span class="text-muted">-</span>
        <?php endif; ?>
    </td>

    <td><?= htmlspecialchars($izin['keperluan']) ?></td>

    <!-- Status Atasan -->
    <td class="text-center">
        <?php
        $ca = ['disetujui'=>'success','ditolak'=>'danger','dibatalkan'=>'secondary','pending'=>'warning'];
        $ba = $ca[$izin['status_atasan']] ?? 'secondary';
        echo "<span class='badge badge-$ba'>".ucfirst($izin['status_atasan'])."</span><br>";
        echo "<small>".($izin['waktu_acc_atasan'] ? date('d-m-Y H:i', strtotime($izin['waktu_acc_atasan'])) : '-')."</small>";
        ?>
    </td>

    <!-- Status SDM -->
    <td class="text-center">
        <?php
        $bs = $ca[$izin['status_sdm']] ?? 'secondary';
        echo "<span class='badge badge-$bs'>".ucfirst($izin['status_sdm'])."</span><br>";
        echo "<small>".($izin['waktu_acc_sdm'] ? date('d-m-Y H:i', strtotime($izin['waktu_acc_sdm'])) : '-')."</small>";
        ?>
    </td>

    <!-- Catatan SDM -->
    <td class="text-center">
        <?php if (!empty($izin['catatan_sdm'])): ?>
            <small class="text-muted"><?= htmlspecialchars($izin['catatan_sdm']) ?></small>
        <?php else: echo '-'; endif; ?>
    </td>

    <!-- Aksi -->
    <td class="text-center">
        <?php if ($izin['status_sdm'] === 'pending'): ?>
            <button type="button"
                    class="btn btn-sm btn-success btn-acc mb-1"
                    data-id="<?= $izin['id'] ?>"
                    data-nama="<?= htmlspecialchars($izin['nama']) ?>"
                    data-status-atasan="<?= $izin['status_atasan'] ?>">
                <i class="fas fa-check"></i> Setujui
            </button>
            <button type="button"
                    class="btn btn-sm btn-danger btn-tolak mb-1"
                    data-id="<?= $izin['id'] ?>"
                    data-nama="<?= htmlspecialchars($izin['nama']) ?>">
                <i class="fas fa-times"></i> Tolak
            </button>
        <?php else: ?>
            <span class="text-muted">-</span>
        <?php endif; ?>
    </td>
</tr>
<?php endwhile; else: ?>
<tr>
    <td colspan="13" class="text-center text-muted py-4">
        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
        Tidak ada data izin keluar.
    </td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= buildPaginationUrl($page-1,$filterNama,$filterNik,$filterDari,$filterSampai) ?>">&laquo;</a>
        </li>
        <?php
        $sp = max(1, $page-2); $ep = min($totalPages, $page+2);
        if ($page <= 3) $ep = min(5, $totalPages);
        if ($page >= $totalPages-2) $sp = max(1, $totalPages-4);
        if ($sp > 1) { echo '<li class="page-item"><a class="page-link" href="'.buildPaginationUrl(1,$filterNama,$filterNik,$filterDari,$filterSampai).'">1</a></li>'; if ($sp > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
        for ($i=$sp; $i<=$ep; $i++) { $ac=($i==$page)?'active':''; echo '<li class="page-item '.$ac.'"><a class="page-link" href="'.buildPaginationUrl($i,$filterNama,$filterNik,$filterDari,$filterSampai).'">'.$i.'</a></li>'; }
        if ($ep < $totalPages) { if ($ep < $totalPages-1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; echo '<li class="page-item"><a class="page-link" href="'.buildPaginationUrl($totalPages,$filterNama,$filterNik,$filterDari,$filterSampai).'">'.$totalPages.'</a></li>'; }
        ?>
        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= buildPaginationUrl($page+1,$filterNama,$filterNik,$filterDari,$filterSampai) ?>">&raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

</div><!-- card-body -->
</div><!-- card -->
</div>
</section>
</div>
</div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Setujui                                               -->
<!-- ============================================================ -->
<div class="modal fade" id="modalApproval" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title">
            <i class="fas fa-check-circle mr-2"></i>Setujui Izin Keluar
          </h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id_izin"    id="approval_id">
          <input type="hidden" name="status_sdm" value="disetujui">

          <div class="alert alert-info py-2">
            <strong>Nama:</strong> <span id="approval_nama"></span><br>
            <strong>Status Atasan:</strong> <span id="approval_status_atasan"></span>
          </div>

          <div class="form-group">
            <label class="font-weight-bold">
              Pilih Catatan Approval <span class="text-danger">*</span>
            </label>

            <div class="catatan-option" onclick="selectCatatan(this,'cat1')">
              <input type="radio" name="catatan_sdm" value="Sudah ACC Atasan" id="cat1" required>
              <label class="catatan-label" for="cat1">
                <i class="fas fa-check-double text-success mr-1"></i> Sudah ACC Atasan
              </label>
            </div>
            <div class="catatan-option" onclick="selectCatatan(this,'cat2')">
              <input type="radio" name="catatan_sdm" value="Atasan Tidak Hadir / Libur" id="cat2">
              <label class="catatan-label" for="cat2">
                <i class="fas fa-calendar-times text-warning mr-1"></i> Atasan Tidak Hadir / Libur
              </label>
            </div>
            <div class="catatan-option" onclick="selectCatatan(this,'cat3')">
              <input type="radio" name="catatan_sdm" value="Atasan Tidak Hadir / Cuti" id="cat3">
              <label class="catatan-label" for="cat3">
                <i class="fas fa-umbrella-beach text-info mr-1"></i> Atasan Tidak Hadir / Cuti
              </label>
            </div>
            <div class="catatan-option" onclick="selectCatatan(this,'cat4')">
              <input type="radio" name="catatan_sdm" value="Atasan Tidak Di Tempat" id="cat4">
              <label class="catatan-label" for="cat4">
                <i class="fas fa-user-slash text-secondary mr-1"></i> Atasan Tidak Di Tempat
              </label>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success">
            <i class="fas fa-check mr-1"></i>Setujui Izin
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Tolak                                                 -->
<!-- ============================================================ -->
<div class="modal fade" id="modalTolak" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">
            <i class="fas fa-times-circle mr-2"></i>Tolak Izin Keluar
          </h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id_izin"    id="tolak_id">
          <input type="hidden" name="status_sdm" value="ditolak">

          <div class="alert alert-warning py-2">
            <strong>Nama:</strong> <span id="tolak_nama"></span>
          </div>

          <div class="form-group">
            <label class="font-weight-bold">
              Alasan Penolakan <span class="text-danger">*</span>
            </label>
            <textarea name="catatan_sdm"
                      class="form-control" rows="3"
                      placeholder="Contoh: Tidak ada pengganti, jadwal sudah penuh, dll."
                      required></textarea>
          </div>
          <small class="text-muted">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Tuliskan alasan penolakan dengan jelas
          </small>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-times mr-1"></i>Tolak Izin
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
$(document).ready(function () {
    setTimeout(function () { $('#flashMsg').fadeOut('slow'); }, 3000);
});

$(document).on('click', '.btn-acc', function () {
    var id           = $(this).data('id');
    var nama         = $(this).data('nama');
    var statusAtasan = $(this).data('status-atasan');

    $('#approval_id').val(id);
    $('#approval_nama').text(nama);

    var badgeMap = { disetujui:'success', ditolak:'danger', pending:'warning', dibatalkan:'secondary' };
    var bc = badgeMap[statusAtasan] || 'secondary';
    var txt = statusAtasan.charAt(0).toUpperCase() + statusAtasan.slice(1);
    $('#approval_status_atasan').html('<span class="badge badge-' + bc + '">' + txt + '</span>');

    $('input[name="catatan_sdm"]').prop('checked', false);
    $('.catatan-option').removeClass('selected');

    $('#modalApproval').modal('show');
});

$(document).on('click', '.btn-tolak', function () {
    $('#tolak_id').val($(this).data('id'));
    $('#tolak_nama').text($(this).data('nama'));
    $('#modalTolak').modal('show');
});

function selectCatatan(el, radioId) {
    $('.catatan-option').removeClass('selected');
    $(el).addClass('selected');
    $('#' + radioId).prop('checked', true);
}
</script>
</body>
</html>