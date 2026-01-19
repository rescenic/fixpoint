<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    echo "<script>alert('Anda belum login');window.location='login.php';</script>";
    exit;
}

$current_file = basename(__FILE__);

$query = "SELECT 1 FROM akses_menu 
          JOIN menu ON akses_menu.menu_id = menu.id 
          WHERE akses_menu.user_id = ? AND menu.file_menu = ?";
$stmt   = $conn->prepare($query);
$stmt->bind_param("is", $user_id, $current_file);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini');window.location='dashboard.php';</script>";
    exit;
}

// === UPDATE STATUS ===
if (isset($_POST['update_status'])) {
    $id     = intval($_POST['id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $catatan_admin = mysqli_real_escape_string($conn, $_POST['catatan_admin']);

    $waktu_update = date('Y-m-d H:i:s');
    $admin_nama   = $_SESSION['nama'] ?? 'Administrator';

    $query = "UPDATE permintaan_hapus_data 
              SET status='$status', 
                  updated_status_at='$waktu_update', 
                  updated_by='$admin_nama',
                  catatan_admin='$catatan_admin'
              WHERE id='$id'";
    
    if ($conn->query($query)) {
        $_SESSION['flash_message'] = "Status berhasil diperbarui menjadi <strong>$status</strong>";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Gagal mengupdate status: " . $conn->error;
        $_SESSION['flash_type'] = "danger";
    }
    
    echo "<script>location.href='data_permintaan_hapus_data_simrs.php';</script>";
    exit;
}

// === FILTER STATUS ===
$filterStatus = $_GET['filter_status'] ?? '';
$whereFilter = "";
if ($filterStatus) {
    $whereFilter = "WHERE status = '" . mysqli_real_escape_string($conn, $filterStatus) . "'";
}

// === PAGINATION SETTING ===
$limit = 10;
$page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $limit;

$totalQuery = $conn->query("SELECT COUNT(*) AS total FROM permintaan_hapus_data $whereFilter");
$totalData  = $totalQuery->fetch_assoc()['total'];
$totalPages = ceil($totalData / $limit);

// === LOAD DATA WITH PAGINATION ===
$data = $conn->query("SELECT * FROM permintaan_hapus_data $whereFilter ORDER BY id DESC LIMIT $start,$limit");



?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Permintaan Hapus Data SIMRS</title>

<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">

<style>
.flash-center {
    position: fixed;
    top: 20%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 9999;
    min-width: 300px;
    max-width: 90%;
    text-align: center;
    padding: 15px;
    border-radius: 8px;
    font-weight: 500;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}

.stats-card {
    text-align: center;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}

.stats-card:hover {
    transform: translateY(-5px);
}

.stats-icon {
    font-size: 40px;
    margin-bottom: 10px;
}

.stats-number {
    font-size: 32px;
    font-weight: 700;
}

.stats-label {
    font-size: 14px;
    color: #666;
    font-weight: 600;
}

.table-scroll {
    overflow-x: auto;
    white-space: nowrap;
}

.table td {
    vertical-align: middle;
}

.small-text {
    font-size: 11px;
    color: #666;
}

/* Modal fix */
.modal-backdrop {
    z-index: 1040 !important;
}

.modal {
    z-index: 1050 !important;
}

.modal-dialog-centered {
    display: flex;
    align-items: center;
    min-height: calc(100% - 3.5rem);
}

.filter-card {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.pagination-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
}

.pagination-info {
    font-size: 14px;
    color: #000;
    font-weight: 500;
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

<!-- Flash -->
<?php if(isset($_SESSION['flash_message'])): 
    $flashType = $_SESSION['flash_type'] ?? 'info';
?>
<div class="alert alert-<?= $flashType ?> flash-center" id="flashMsg">
    <i class="fas fa-<?= $flashType=='success'?'check-circle':($flashType=='danger'?'exclamation-circle':'info-circle') ?>"></i>
    <?= $_SESSION['flash_message']; ?>
</div>
<script>setTimeout(()=>{document.getElementById('flashMsg').style.display='none';},3000);</script>
<?php 
    unset($_SESSION['flash_message']); 
    unset($_SESSION['flash_type']); 
endif; 
?>



<div class="card">
<div class="card-header">
    <h4><i class="fas fa-list"></i> Data Permintaan Hapus Data SIMRS</h4>
</div>

<div class="card-body">

<!-- Filter -->
<div class="filter-card">
    <form method="GET" class="form-inline">
        <label class="mr-2"><i class="fas fa-filter"></i> Filter Status:</label>
        <select name="filter_status" class="form-control mr-2" onchange="this.form.submit()">
            <option value="">-- Semua Status --</option>
            <option value="Menunggu" <?= ($filterStatus=='Menunggu')?'selected':'' ?>>Menunggu</option>
            <option value="Diproses" <?= ($filterStatus=='Diproses')?'selected':'' ?>>Diproses</option>
            <option value="Disetujui" <?= ($filterStatus=='Disetujui')?'selected':'' ?>>Disetujui</option>
            <option value="Ditolak" <?= ($filterStatus=='Ditolak')?'selected':'' ?>>Ditolak</option>
            <option value="Selesai" <?= ($filterStatus=='Selesai')?'selected':'' ?>>Selesai</option>
        </select>
        <?php if ($filterStatus): ?>
        <a href="data_permintaan_hapus_data_simrs.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-redo"></i> Reset
        </a>
        <?php endif; ?>
    </form>
</div>

<div class="table-responsive table-scroll">
<table class="table table-bordered table-hover table-sm">
<thead class="text-center bg-primary text-white">
<tr>
    <th width="40">No</th>
    <th width="150">No. Surat</th>
    <th>Nama Pemohon</th>
    <th>Jabatan</th>
    <th>Unit</th>
    <th>Data Terkait</th>
    <th>Alasan</th>
    <th width="100">Tanggal</th>
    <th width="100">Status</th>
    <th width="150">Update Terakhir</th>
    <th width="100">Aksi</th>
</tr>
</thead>

<tbody>
<?php 
$no = $start + 1;
if (mysqli_num_rows($data) > 0):
    while($row = $data->fetch_assoc()):
?>

<tr>
    <td class="text-center"><?= $no++; ?></td>
    <td><strong><?= htmlspecialchars($row['nomor_surat']); ?></strong></td>
    <td><?= htmlspecialchars($row['nama']); ?></td>
    <td><?= htmlspecialchars($row['jabatan']); ?></td>
    <td><?= htmlspecialchars($row['unit_kerja']); ?></td>
    <td><?= htmlspecialchars($row['data_terkait'] ?? '-'); ?></td>
    <td><?= htmlspecialchars($row['alasan'] ?? '-'); ?></td>
    <td class="text-center small-text"><?= date('d/m/Y', strtotime($row['tanggal'])); ?></td>

    <td class="text-center">
    <?php
    $statusConfig = [
        "Menunggu" => ["badge-warning", "hourglass-half"],
        "Diproses" => ["badge-primary", "cog"],
        "Disetujui" => ["badge-info", "check"],
        "Ditolak"  => ["badge-danger", "times"],
        "Selesai"  => ["badge-success", "check-circle"]
    ];
    $config = $statusConfig[$row['status']] ?? ["badge-secondary", "question"];
    ?>
    <span class="badge <?= $config[0] ?>">
        <i class="fas fa-<?= $config[1] ?>"></i> <?= $row['status']; ?>
    </span>
    </td>

    <td class="small-text">
    <?= $row['updated_status_at'] ? 
    "<b>".date('d/m/Y H:i',strtotime($row['updated_status_at']))."</b><br><i>oleh ".htmlspecialchars($row['updated_by'])."</i>" 
    : "<i class='text-muted'>- belum ada update -</i>"; ?>
    </td>

    <td class="text-center">
        <button class="btn btn-sm btn-info"
            onclick="openModal(
                '<?= $row['id']; ?>',
                '<?= htmlspecialchars($row['status']); ?>',
                `<?= htmlspecialchars($row['kronologi'], ENT_QUOTES); ?>`,
                '<?= htmlspecialchars($row['nama']); ?>',
                '<?= htmlspecialchars($row['nomor_surat']); ?>',
                '<?= htmlspecialchars($row['data_terkait'] ?? ''); ?>',
                '<?= htmlspecialchars($row['alasan'] ?? ''); ?>',
                `<?= htmlspecialchars($row['catatan_admin'] ?? '', ENT_QUOTES); ?>`
            )"
            title="Update Status">
            <i class="fas fa-edit"></i>
        </button>
        <a href="print_hapus_data.php?id=<?= $row['id']; ?>" 
           target="_blank" 
           class="btn btn-sm btn-secondary"
           title="Cetak">
            <i class="fas fa-print"></i>
        </a>
    </td>
</tr>
<?php 
    endwhile;
else:
?>
<tr>
    <td colspan="11" class="text-center">
        <i class="fas fa-inbox fa-3x text-muted mb-3"></i><br>
        Tidak ada data permintaan
    </td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination-wrapper">
    <div class="pagination-info">
        Menampilkan <?= $start + 1 ?> - <?= min($start + $limit, $totalData) ?> dari <?= $totalData ?> data
    </div>
    <nav>
        <ul class="pagination mb-0">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?><?= $filterStatus?"&filter_status=$filterStatus":'' ?>">
                    <span>&laquo;</span>
                </a>
            </li>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($totalPages, $page + 2);

            if($start_page > 1): ?>
                <li class="page-item"><a class="page-link" href="?page=1<?= $filterStatus?"&filter_status=$filterStatus":'' ?>">1</a></li>
                <?php if($start_page > 2): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif;
            endif;

            for($i = $start_page; $i <= $end_page; $i++): ?>
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?><?= $filterStatus?"&filter_status=$filterStatus":'' ?>"><?= $i ?></a>
                </li>
            <?php endfor;

            if($end_page < $totalPages): 
                if($end_page < $totalPages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $totalPages ?><?= $filterStatus?"&filter_status=$filterStatus":'' ?>"><?= $totalPages ?></a></li>
            <?php endif; ?>

            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?><?= $filterStatus?"&filter_status=$filterStatus":'' ?>">
                    <span>&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
</div>
<?php endif; ?>

</div>
</div>

</div>
</section>
</div>
</div>
</div>

<!-- MODAL UPDATE STATUS -->
<div class="modal fade" id="modalStatusGlobal" tabindex="-1" role="dialog">
<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
<div class="modal-content">

<div class="modal-header bg-primary text-white">
    <h5 class="modal-title"><i class="fas fa-edit"></i> Update Status Permintaan</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form method="POST">
<div class="modal-body">
    <input type="hidden" name="id" id="modalId">
    
    <div class="alert alert-info">
        <strong>No. Surat:</strong> <span id="modalNomorSurat"></span><br>
        <strong>Pemohon:</strong> <span id="modalNama"></span>
    </div>
    
    <div class="form-group">
        <label><strong>Data yang Akan Dihapus:</strong></label>
        <p id="modalDataTerkait" class="form-control-plaintext bg-light p-2 rounded"></p>
    </div>
    
    <div class="form-group">
        <label><strong>Alasan:</strong></label>
        <p id="modalAlasan" class="form-control-plaintext bg-light p-2 rounded"></p>
    </div>
    
    <div class="form-group">
        <label><strong>Kronologi:</strong></label>
        <p id="modalKronologi" class="form-control-plaintext bg-light p-2 rounded" style="white-space:pre-line;"></p>
    </div>

    <hr>

    <div class="form-group">
        <label><i class="fas fa-clipboard-check"></i> Update Status <span class="text-danger">*</span></label>
        <select name="status" id="modalStatus" class="form-control" required>
            <option value="Menunggu">Menunggu</option>
            <option value="Diproses">Diproses</option>
            <option value="Disetujui">Disetujui</option>
            <option value="Ditolak">Ditolak</option>
            <option value="Selesai">Selesai</option>
        </select>
        <small class="form-text text-muted">
            <strong>Disetujui:</strong> Permintaan disetujui, siap untuk dihapus<br>
            <strong>Selesai:</strong> Data sudah berhasil dihapus
        </small>
    </div>
    
    <div class="form-group">
        <label><i class="fas fa-comment"></i> Catatan Admin</label>
        <textarea name="catatan_admin" id="modalCatatanAdmin" class="form-control" rows="4" 
                  placeholder="Berikan catatan jika diperlukan (opsional, terutama untuk status Ditolak)"></textarea>
    </div>

    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> <strong>Perhatian:</strong>
        <ul class="mb-0 mt-2">
            <li>Pastikan Anda sudah memverifikasi data dengan benar</li>
            <li>Status "Selesai" berarti data sudah dihapus dari SIMRS</li>
            <li>Status "Ditolak" sebaiknya disertai dengan catatan alasan</li>
        </ul>
    </div>
</div>

<div class="modal-footer">
    <button type="submit" name="update_status" class="btn btn-primary">
        <i class="fas fa-save"></i> Simpan Perubahan
    </button>
    <button type="button" class="btn btn-secondary" data-dismiss="modal">
        <i class="fas fa-times"></i> Batal
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
function openModal(id, status, kronologi, nama, nomor_surat, data_terkait, alasan, catatan_admin){
    document.getElementById("modalId").value = id;
    document.getElementById("modalStatus").value = status;
    document.getElementById("modalNama").innerHTML = nama;
    document.getElementById("modalNomorSurat").innerHTML = nomor_surat;
    document.getElementById("modalKronologi").innerHTML = kronologi;
    document.getElementById("modalDataTerkait").innerHTML = data_terkait || '-';
    document.getElementById("modalAlasan").innerHTML = alasan || '-';
    document.getElementById("modalCatatanAdmin").value = catatan_admin || '';
    $('#modalStatusGlobal').modal('show');
}
</script>

</body>
</html>