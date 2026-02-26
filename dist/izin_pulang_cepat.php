<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

/* ===============================
   TELEGRAM
================================ */
function sendTelegram($conn, $pesan_html, $tujuan='hrd'){
    $token = mysqli_fetch_assoc(
        mysqli_query($conn,"SELECT nilai FROM setting WHERE nama='telegram_bot_token' LIMIT 1")
    )['nilai'] ?? '';

    $setting_chat = ($tujuan==='it') ? 'telegram_chat_id' : 'telegram_chat_id_hrd';
    $chat_id = mysqli_fetch_assoc(
        mysqli_query($conn,"SELECT nilai FROM setting WHERE nama='$setting_chat' LIMIT 1")
    )['nilai'] ?? '';

    if(!$token || !$chat_id) return false;

    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>http_build_query([
            'chat_id'=>$chat_id,
            'text'=>$pesan_html,
            'parse_mode'=>'HTML'
        ]),
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_SSL_VERIFYPEER=>false
    ]);
    curl_exec($ch);
    curl_close($ch);
}

/* ===============================
   CEK LOGIN
================================ */
$user_id = $_SESSION['user_id'] ?? 0;
if($user_id==0){
    echo "<script>alert('Anda belum login');location.href='login.php';</script>";
    exit;
}

$current_file = basename(__FILE__);

/* ===============================
   CEK AKSES
================================ */
$stmt = $conn->prepare("
    SELECT 1 FROM akses_menu 
    JOIN menu ON akses_menu.menu_id=menu.id
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
    SELECT u.nik,u.nama,u.jabatan,u.unit_kerja,a.nama AS nama_atasan
    FROM users u
    LEFT JOIN users a ON u.atasan_id=a.id
    WHERE u.id=?
");
$qUser->bind_param("i",$user_id);
$qUser->execute();
$user = $qUser->get_result()->fetch_assoc();

/* ===============================
   SIMPAN DATA
================================ */
if(isset($_POST['simpan'])){
    $tanggal = $_POST['tanggal'] ?? '';
    $jam_pulang = $_POST['jam_pulang'] ?? '';
    $keperluan = trim($_POST['keperluan'] ?? '');

    if(!$tanggal || !$jam_pulang || !$keperluan){
        $_SESSION['flash_message']="Tanggal, jam pulang dan alasan wajib diisi!";
        header("Location: izin_pulang_cepat.php");
        exit;
    }

    $insert = $conn->prepare("
        INSERT INTO izin_pulang_cepat
        (user_id,nik,nama,jabatan,bagian,atasan_langsung,
         tanggal,jam_pulang,keperluan,
         status_atasan,status_sdm,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,'pending','pending',NOW())
    ");
    $insert->bind_param(
        "issssssss",
        $user_id,
        $user['nik'],
        $user['nama'],
        $user['jabatan'],
        $user['unit_kerja'],
        $user['nama_atasan'],
        $tanggal,
        $jam_pulang,
        $keperluan
    );

    if($insert->execute()){
        $tg  ="<b>🏠 IZIN PULANG CEPAT</b>\n\n";
        $tg .="👤 <b>Nama:</b> {$user['nama']}\n";
        $tg .="🆔 <b>NIK:</b> {$user['nik']}\n";
        $tg .="💼 <b>Jabatan:</b> {$user['jabatan']}\n";
        $tg .="🏢 <b>Unit:</b> {$user['unit_kerja']}\n";
        $tg .="🕒 <b>Jam Pulang:</b> {$jam_pulang} WIB\n";
        $tg .="📌 <b>Alasan:</b>\n<pre>{$keperluan}</pre>\n";
        $tg .="👔 <b>Atasan:</b> {$user['nama_atasan']}\n";
        $tg .="📅 <b>Tanggal:</b> ".date('d-m-Y',strtotime($tanggal));

        sendTelegram($conn,$tg);
        $_SESSION['flash_message']="✅ Izin pulang cepat berhasil diajukan.";
    }else{
        $_SESSION['flash_message']="❌ Gagal menyimpan data.";
    }
    header("Location: izin_pulang_cepat.php?tab=data");
    exit;
}

/* ===============================
   FILTER & PAGINATION
================================ */
$tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-d');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

$limit=10;
$page=max(1,intval($_GET['page'] ?? 1));
$start=($page-1)*$limit;

$count = $conn->prepare("
    SELECT COUNT(*) total FROM izin_pulang_cepat
    WHERE user_id=? AND tanggal BETWEEN ? AND ?
");
$count->bind_param("iss",$user_id,$tgl_awal,$tgl_akhir);
$count->execute();
$total = $count->get_result()->fetch_assoc()['total'];
$total_page = ceil($total/$limit);

$data = $conn->prepare("
    SELECT * FROM izin_pulang_cepat
    WHERE user_id=? AND tanggal BETWEEN ? AND ?
    ORDER BY created_at DESC LIMIT ?,?
");
$data->bind_param("issii",$user_id,$tgl_awal,$tgl_akhir,$start,$limit);
$data->execute();
$data_izin = $data->get_result();

function pageUrl($p,$a,$b){
    return "izin_pulang_cepat.php?".http_build_query([
        'tab'=>'data','page'=>$p,'tgl_awal'=>$a,'tgl_akhir'=>$b
    ]);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Izin Pulang Cepat</title>
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
<h4>Form Izin Pulang Cepat</h4>
</div>

<div class="card-body">
<ul class="nav nav-tabs">
<li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#input">Input</a></li>
<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#data">Data</a></li>
</ul>

<div class="tab-content mt-3">
<div class="tab-pane fade show active" id="input">
<form method="POST">
<div class="row">
<div class="col-md-6">
<div class="form-group">
<label>Tanggal</label>
<input type="date" name="tanggal" class="form-control" value="<?=date('Y-m-d')?>">
</div>
<div class="form-group">
<label>Jam Pulang</label>
<input type="time" name="jam_pulang" class="form-control">
</div>
</div>
<div class="col-md-6">
<div class="form-group">
<label>Alasan</label>
<textarea name="keperluan" class="form-control" rows="5"></textarea>
</div>
</div>
</div>
<button class="btn btn-primary" name="simpan">
<i class="fas fa-save"></i> Simpan
</button>
</form>
</div>

<div class="tab-pane fade" id="data">
<table class="table table-bordered">
<thead class="bg-dark text-white">
<tr>
<th>No</th>
<th>Tanggal</th>
<th>Jam Pulang</th>
<th>Alasan</th>
<th>Status Atasan</th>
<th>Status SDM</th>
<th>Aksi</th>
</tr>
</thead>
<tbody>
<?php $no=$start+1; while($r=$data_izin->fetch_assoc()): ?>
<tr>
<td><?=$no++?></td>
<td><?=date('d-m-Y',strtotime($r['tanggal']))?></td>
<td><?=$r['jam_pulang']?></td>
<td><?=$r['keperluan']?></td>
<td><?=$r['status_atasan']?></td>
<td><?=$r['status_sdm']?></td>
<td>
<a href="cetak_izin_pulang_cepat.php?id=<?=$r['id']?>" target="_blank" class="btn btn-info btn-sm">
<i class="fas fa-print"></i> Cetak
</a>
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
