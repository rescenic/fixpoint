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
   CEK AKSES
================================ */
$stmt = $conn->prepare("
    SELECT 1 FROM akses_menu
    JOIN menu ON akses_menu.menu_id = menu.id
    WHERE akses_menu.user_id=? AND menu.file_menu=?
");
$stmt->bind_param("is",$user_id,$current_file);
$stmt->execute();
if($stmt->get_result()->num_rows==0){
    echo "<script>alert('Anda tidak memiliki akses');location.href='dashboard.php';</script>";
    exit;
}

/* ===============================
   DATA USER
================================ */
$qUser = $conn->prepare("
    SELECT nik,nama,jabatan,unit_kerja
    FROM users WHERE id=?
");
$qUser->bind_param("i",$user_id);
$qUser->execute();
$user = $qUser->get_result()->fetch_assoc();

/* ===============================
   GENERATE NO SURAT
================================ */
$bulan = date('m');
$tahun = date('Y');

$qNo = mysqli_query($conn,"
    SELECT COUNT(*) total
    FROM lembur_pengajuan
    WHERE MONTH(tanggal_pengajuan)='$bulan'
    AND YEAR(tanggal_pengajuan)='$tahun'
");
$urut = mysqli_fetch_assoc($qNo)['total'] + 1;
$no_surat = str_pad($urut,4,'0',STR_PAD_LEFT)."/LBR/RSPH/$bulan/$tahun";

/* ===============================
   SIMPAN PENGAJUAN LEMBUR
================================ */
if(isset($_POST['simpan'])){

    $tgl_mulai    = $_POST['tgl_mulai'];
    $jam_mulai    = $_POST['jam_mulai'];
    $tgl_selesai  = $_POST['tgl_selesai'];
    $jam_selesai  = $_POST['jam_selesai'];
    $total_jam    = floatval($_POST['total_jam']);

    $dasar  = trim($_POST['dasar_pengajuan']);
    $jenis  = implode(', ', $_POST['jenis_pekerjaan'] ?? []);
    $aktual = trim($_POST['aktual_pekerjaan']);

    if(!$tgl_mulai || !$jam_mulai || !$tgl_selesai || !$jam_selesai || !$dasar || !$jenis || $total_jam<=0){
        $_SESSION['flash_message']="❌ Data lembur belum lengkap.";
        header("Location: lembur.php");
        exit;
    }

    $lembur_mulai   = $tgl_mulai.' '.$jam_mulai.':00';
    $lembur_selesai = $tgl_selesai.' '.$jam_selesai.':00';

    $stmt = $conn->prepare("
        INSERT INTO lembur_pengajuan
        (no_surat,user_id,nik,nama,jabatan,unit,
         tanggal_pengajuan,lembur_mulai,lembur_selesai,
         total_jam,dasar_pengajuan,jenis_pekerjaan,aktual_pekerjaan,
         status_atasan,status_sdm,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'pending','pending',NOW())
    ");
    $stmt->bind_param(
        "sisssssssdsss",
        $no_surat,
        $user_id,
        $user['nik'],
        $user['nama'],
        $user['jabatan'],
        $user['unit_kerja'],
        date('Y-m-d'),
        $lembur_mulai,
        $lembur_selesai,
        $total_jam,
        $dasar,
        $jenis,
        $aktual
    );
    $stmt->execute();

    $_SESSION['flash_message']="✅ Pengajuan lembur berhasil disimpan.";
    header("Location: lembur.php");
    exit;
}

/* ===============================
   SIMPAN LAPORAN LEMBUR
================================ */
if(isset($_POST['simpan_laporan'])){
    $lembur_id = intval($_POST['lembur_id']);
    $jenis_detail = implode(', ', $_POST['jenis_detail'] ?? []);
    $aktual = trim($_POST['aktual']);
    $ket = trim($_POST['keterangan']);

    if(!$lembur_id || !$jenis_detail || !$aktual){
        $_SESSION['flash_message']="❌ Laporan lembur belum lengkap.";
        header("Location: lembur.php");
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO lembur_laporan
        (lembur_id, jenis_pekerjaan_detail, aktual_pelaksanaan, keterangan)
        VALUES (?,?,?,?)
    ");
    $stmt->bind_param("isss",$lembur_id,$jenis_detail,$aktual,$ket);
    $stmt->execute();

    $_SESSION['flash_message']="✅ Laporan lembur berhasil disimpan.";
    header("Location: lembur.php");
    exit;
}

/* ===============================
   DATA LEMBUR USER
================================ */
$qData = $conn->prepare("
    SELECT lp.*, ll.id AS laporan_id
    FROM lembur_pengajuan lp
    LEFT JOIN lembur_laporan ll ON ll.lembur_id = lp.id
    WHERE lp.user_id = ?
    ORDER BY lp.created_at DESC
");
$qData->bind_param("i",$user_id);
$qData->execute();
$data_lembur = $qData->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Pengajuan Lembur</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
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
<div class="alert alert-info"><?= $_SESSION['flash_message']; ?></div>
<?php unset($_SESSION['flash_message']); endif; ?>

<div class="card">
<div class="card-header">
<h4>Pengajuan Lembur</h4>
</div>

<div class="card-body">

<ul class="nav nav-tabs">
<li class="nav-item">
<a class="nav-link active" data-toggle="tab" href="#input">Input Lembur</a>
</li>
<li class="nav-item">
<a class="nav-link" data-toggle="tab" href="#data">Data Lembur</a>
</li>
</ul>

<div class="tab-content mt-3">

<!-- ================= TAB INPUT ================= -->
<div class="tab-pane fade show active" id="input">

<!-- FIX: Tambahkan method POST pada form -->
<form method="POST" id="formLembur">
<!-- FIX: Tambahkan input hidden name="simpan" agar PHP bisa deteksi POST -->
<input type="hidden" name="simpan" value="1">

<div class="row">
<div class="col-md-6">

<label>No Surat</label>
<input class="form-control" value="<?= $no_surat ?>" readonly>

<label class="mt-2">Tanggal Mulai</label>
<input type="date" name="tgl_mulai" id="tgl_mulai" class="form-control" required>

<label class="mt-2">Jam Mulai</label>
<input type="time" name="jam_mulai" id="jam_mulai" class="form-control" required>

<label class="mt-2">Tanggal Selesai</label>
<input type="date" name="tgl_selesai" id="tgl_selesai" class="form-control" required>

<label class="mt-2">Jam Selesai</label>
<input type="time" name="jam_selesai" id="jam_selesai" class="form-control" required>

<label class="mt-2">Total Jam</label>
<input type="text" name="total_jam" id="total_jam" class="form-control" readonly placeholder="Otomatis terhitung">

</div>

<div class="col-md-6">

<label>Dasar Pengajuan</label>
<textarea name="dasar_pengajuan" class="form-control" rows="3" required></textarea>

<label class="mt-2">Jenis Pekerjaan</label><br>
<label><input type="checkbox" name="jenis_pekerjaan[]" value="Administrasi"> Administrasi</label><br>
<label><input type="checkbox" name="jenis_pekerjaan[]" value="Pelayanan Pasien"> Pelayanan Pasien</label><br>
<label><input type="checkbox" name="jenis_pekerjaan[]" value="IT / Sistem"> IT / Sistem</label><br>
<label><input type="checkbox" name="jenis_pekerjaan[]" value="Lainnya"> Lainnya</label>

<label class="mt-2">Aktual Pekerjaan</label>
<textarea name="aktual_pekerjaan" class="form-control" rows="3"></textarea>

</div>
</div>

<!-- FIX: Tombol Simpan sekarang memicu modal konfirmasi -->
<button type="button" id="btnSubmit" class="btn btn-primary mt-3">
<i class="fas fa-save"></i> Simpan
</button>

</form>

</div>

<!-- ================= TAB DATA ================= -->
<div class="tab-pane fade" id="data">

<div class="table-responsive">
<table class="table table-bordered table-sm">
<thead class="bg-dark text-white text-center">
<tr>
<th>No</th>
<th>No Surat</th>
<th>Waktu Lembur</th>
<th>Total Jam</th>
<th>Status Atasan</th>
<th>Status SDM</th>
<th>Laporan</th>
</tr>
</thead>
<tbody>

<?php $no=1; while($r=$data_lembur->fetch_assoc()): ?>
<tr>
<td class="text-center"><?= $no++ ?></td>
<td><?= htmlspecialchars($r['no_surat']) ?></td>
<td>
<?= date('d-m-Y H:i',strtotime($r['lembur_mulai'])) ?><br>s/d<br>
<?= date('d-m-Y H:i',strtotime($r['lembur_selesai'])) ?>
</td>
<td class="text-center"><?= $r['total_jam'] ?> Jam</td>
<td class="text-center">
    <?php
    $sa = $r['status_atasan'];
    $badge_atasan = $sa == 'disetujui' ? 'success' : ($sa == 'ditolak' ? 'danger' : 'warning');
    ?>
    <span class="badge badge-<?= $badge_atasan ?>"><?= ucfirst($sa) ?></span>
</td>
<td class="text-center">
    <?php
    $ss = $r['status_sdm'];
    $badge_sdm = $ss == 'disetujui' ? 'success' : ($ss == 'ditolak' ? 'danger' : 'warning');
    ?>
    <span class="badge badge-<?= $badge_sdm ?>"><?= ucfirst($ss) ?></span>
</td>
<td class="text-center">
<?php if($r['status_atasan']=='disetujui' && $r['status_sdm']=='disetujui'): ?>
    <?php if($r['laporan_id']): ?>
        <span class="badge badge-success">Sudah Laporan</span>
    <?php else: ?>
        <button class="btn btn-success btn-sm btnLaporan" data-id="<?= $r['id'] ?>">
            <i class="fas fa-clipboard-list"></i> Laporan
        </button>
    <?php endif; ?>
<?php else: ?>
    <span class="text-muted">Menunggu ACC</span>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>

</tbody>
</table>
</div>

</div>

</div>
</div>

</div>
</section>
</div>
</div>
</div>

<!-- ================= MODAL KONFIRMASI (FIX: Modal yang hilang, sekarang ditambahkan) ================= -->
<div class="modal fade" id="modalKonfirmasi">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary text-white">
<h5 class="modal-title"><i class="fas fa-question-circle"></i> Konfirmasi Pengajuan</h5>
<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
<p>Apakah data pengajuan lembur sudah benar?</p>
<ul class="list-unstyled" id="ringkasanLembur"></ul>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-dismiss="modal">
    <i class="fas fa-times"></i> Batal
</button>
<button type="button" class="btn btn-primary" id="btnKonfirmasi">
    <i class="fas fa-check"></i> Ya, Simpan
</button>
</div>
</div>
</div>
</div>

<!-- ================= MODAL LAPORAN ================= -->
<div class="modal fade" id="modalLaporan">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<form method="POST">
<input type="hidden" name="lembur_id" id="lembur_id">

<div class="modal-header bg-success text-white">
<h5 class="modal-title"><i class="fas fa-clipboard-check"></i> Laporan Lembur</h5>
<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
</div>

<div class="modal-body">

<label>Jenis Pekerjaan Detail</label><br>
<label><input type="checkbox" name="jenis_detail[]" value="Administrasi"> Administrasi</label><br>
<label><input type="checkbox" name="jenis_detail[]" value="Pelayanan Pasien"> Pelayanan Pasien</label><br>
<label><input type="checkbox" name="jenis_detail[]" value="IT / Sistem"> IT / Sistem</label><br>
<label><input type="checkbox" name="jenis_detail[]" value="Lainnya"> Lainnya</label>

<div class="form-group mt-2">
<label>Aktual Pelaksanaan</label>
<input type="text" name="aktual" class="form-control" placeholder="Contoh: 100% selesai" required>
</div>

<div class="form-group">
<label>Keterangan</label>
<textarea name="keterangan" class="form-control"></textarea>
</div>

</div>

<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
<button type="submit" name="simpan_laporan" class="btn btn-success">
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
/* ===== HITUNG TOTAL JAM OTOMATIS ===== */
function hitungJam(){
    var t1 = $('#tgl_mulai').val();
    var j1 = $('#jam_mulai').val();
    var t2 = $('#tgl_selesai').val();
    var j2 = $('#jam_selesai').val();

    if(!t1 || !j1 || !t2 || !j2){
        $('#total_jam').val('');
        return;
    }

    var start = new Date(t1 + 'T' + j1 + ':00');
    var end   = new Date(t2 + 'T' + j2 + ':00');

    if(end <= start){
        $('#total_jam').val('0.00');
        return;
    }

    var jam = ((end - start) / 3600000).toFixed(2);
    $('#total_jam').val(jam);
}

$('#tgl_mulai, #jam_mulai, #tgl_selesai, #jam_selesai').on('change', hitungJam);

/* ===== VALIDASI & TAMPILKAN MODAL KONFIRMASI ===== */
$('#btnSubmit').click(function(){
    // Validasi total jam
    var totalJam = parseFloat($('#total_jam').val());
    if(!totalJam || totalJam <= 0){
        alert('Total jam lembur belum valid. Pastikan tanggal dan jam sudah diisi dengan benar.');
        return;
    }

    // Validasi jenis pekerjaan minimal 1 dipilih
    if($('input[name="jenis_pekerjaan[]"]:checked').length === 0){
        alert('Pilih minimal satu jenis pekerjaan.');
        return;
    }

    // Validasi dasar pengajuan
    if($('textarea[name="dasar_pengajuan"]').val().trim() === ''){
        alert('Dasar pengajuan wajib diisi.');
        return;
    }

    // Tampilkan ringkasan di modal konfirmasi
    var jenis = [];
    $('input[name="jenis_pekerjaan[]"]:checked').each(function(){
        jenis.push($(this).val());
    });

    var ringkasan = '<li><strong>Mulai:</strong> ' + $('#tgl_mulai').val() + ' ' + $('#jam_mulai').val() + '</li>' +
                   '<li><strong>Selesai:</strong> ' + $('#tgl_selesai').val() + ' ' + $('#jam_selesai').val() + '</li>' +
                   '<li><strong>Total Jam:</strong> ' + totalJam + ' Jam</li>' +
                   '<li><strong>Jenis Pekerjaan:</strong> ' + jenis.join(', ') + '</li>';

    $('#ringkasanLembur').html(ringkasan);

    // Tampilkan modal konfirmasi
    $('#modalKonfirmasi').modal('show');
});

/* ===== SUBMIT FORM SETELAH KONFIRMASI ===== */
$('#btnKonfirmasi').click(function(){
    $('#modalKonfirmasi').modal('hide');
    $('#formLembur').submit(); // FIX: Submit form yang sudah punya input hidden name="simpan"
});

/* ===== BUKA MODAL LAPORAN ===== */
$(document).on('click', '.btnLaporan', function(){
    $('#lembur_id').val($(this).data('id'));
    // Reset checkbox sebelum buka
    $('#modalLaporan input[type="checkbox"]').prop('checked', false);
    $('#modalLaporan input[name="aktual"]').val('');
    $('#modalLaporan textarea[name="keterangan"]').val('');
    $('#modalLaporan').modal('show');
});
</script>

</body>
</html>