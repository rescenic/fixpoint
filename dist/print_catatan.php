<?php
session_start();
include 'koneksi.php';
require 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;

date_default_timezone_set('Asia/Jakarta');

// ================= CEK LOGIN =================
$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    echo "<script>alert('Anda belum login.'); window.close();</script>";
    exit;
}

// ================= FUNCTION TTE =================
function getTteByUser($conn, $user_id) {
    if (empty($user_id)) return null;
    $q = mysqli_query($conn, "
        SELECT * FROM tte_user
        WHERE user_id = '$user_id'
          AND status = 'aktif'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    return mysqli_fetch_assoc($q);
}

function qrTte($token) {
    $url = "http://" . $_SERVER['HTTP_HOST'] . "/verify_tte.php?token=" . $token;
    return "https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=" . urlencode($url);
}

// ================= DATA PERUSAHAAN =================
$q_perusahaan = mysqli_query($conn, "SELECT * FROM perusahaan LIMIT 1");
$perusahaan = mysqli_fetch_assoc($q_perusahaan);

// ================= DATA USER =================
$q_user = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id' LIMIT 1");
$user = mysqli_fetch_assoc($q_user);

// ================= TTE USER =================
$tte_user = getTteByUser($conn, $user_id);
$qr_user  = $tte_user ? qrTte($tte_user['token']) : '';

// ================= FILTER =================
$tgl_dari   = $_GET['tgl_dari'] ?? '';
$tgl_sampai = $_GET['tgl_sampai'] ?? '';
$search     = $_GET['search'] ?? '';

$where  = "WHERE c.user_id = '$user_id'";
if ($tgl_dari && $tgl_sampai) {
    $where .= " AND DATE(c.tanggal) BETWEEN '$tgl_dari' AND '$tgl_sampai'";
} elseif ($tgl_dari) {
    $where .= " AND DATE(c.tanggal) >= '$tgl_dari'";
} elseif ($tgl_sampai) {
    $where .= " AND DATE(c.tanggal) <= '$tgl_sampai'";
}
if ($search) {
    $search = mysqli_real_escape_string($conn, $search);
    $where .= " AND (c.judul LIKE '%$search%' OR c.isi LIKE '%$search%')";
}

// ================= DATA CATATAN =================
$sql = "
    SELECT c.*, u.nama 
    FROM catatan_kerja c
    JOIN users u ON c.user_id = u.id
    $where
    ORDER BY c.tanggal DESC
";
$q = mysqli_query($conn, $sql);

// ================= PERIODE =================
if ($tgl_dari && $tgl_sampai) {
    $periode = 'Periode: '.date('d-m-Y', strtotime($tgl_dari)).' s/d '.date('d-m-Y', strtotime($tgl_sampai));
} elseif ($tgl_dari) {
    $periode = 'Periode: Mulai '.date('d-m-Y', strtotime($tgl_dari));
} elseif ($tgl_sampai) {
    $periode = 'Periode: Sampai '.date('d-m-Y', strtotime($tgl_sampai));
} else {
    $periode = 'Periode: Semua Data';
}

// ================= HTML =================
$html = '
<style>
body { font-family: Arial, sans-serif; font-size: 11px; }
.kop { text-align:center; border-bottom:2px solid #000; margin-bottom:12px; }
.kop .nama { font-size:16px; font-weight:bold; text-transform:uppercase; }
.kop .alamat { font-size:11px; }
h3 { text-align:center; margin:10px 0 3px; }
p { text-align:center; margin:2px 0; }

table { border-collapse:collapse; width:100%; margin-top:10px; }
table, th, td { border:1px solid #000; }
th, td { padding:5px; }
th { background:#f2f2f2; }

.tte-box {
    width:260px;
    float:right;
    text-align:center;
    margin-top:25px;
    font-size:10px;
}
.qr { width:80px; margin:6px 0; }
.tte-name { font-weight:bold; text-decoration:underline; }
.footer {
    clear:both;
    margin-top:30px;
    text-align:center;
    font-size:9px;
    color:#555;
}
</style>

<div class="kop">
  <div class="nama">'.htmlspecialchars($perusahaan['nama_perusahaan']).'</div>
  <div class="alamat">'
    .htmlspecialchars($perusahaan['alamat']).', '
    .htmlspecialchars($perusahaan['kota']).', '
    .htmlspecialchars($perusahaan['provinsi']).'<br>
    Telp: '.htmlspecialchars($perusahaan['kontak']).' | Email: '.htmlspecialchars($perusahaan['email']).'
  </div>
</div>

<h3>LAPORAN CATATAN KERJA</h3>
<p><b>'.$periode.'</b></p>
<p>Dicetak pada: '.date('d-m-Y H:i').' WIB</p>

<table>
<thead>
<tr>
  <th>No</th>
  <th>Nama</th>
  <th>Tanggal</th>
  <th>Judul</th>
  <th>Catatan</th>
</tr>
</thead>
<tbody>';

$no = 1;
if (mysqli_num_rows($q) > 0) {
    while ($row = mysqli_fetch_assoc($q)) {
        $html .= '
        <tr>
          <td align="center">'.$no++.'</td>
          <td>'.htmlspecialchars($row['nama']).'</td>
          <td>'.date('d-m-Y H:i', strtotime($row['tanggal'])).'</td>
          <td>'.htmlspecialchars($row['judul']).'</td>
          <td>'.nl2br(htmlspecialchars($row['isi'])).'</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="5" align="center">Tidak ada data</td></tr>';
}

$html .= '
</tbody>
</table>

<div class="tte-box">
<p>'.$perusahaan['kota'].', '.date('d-m-Y').'</p>
<p><b>Ditandatangani secara elektronik oleh</b></p>

'.($tte_user ? '
<img src="'.$qr_user.'" class="qr"><br>
<div class="tte-name">'.$tte_user['nama'].'</div>
'.$tte_user['jabatan'].'<br>
' : '<em>TTE tidak tersedia</em>').'
</div>

<div class="footer">
TTE Non Sertifikasi di-generate melalui aplikasi
<strong>FixPoint – Smart Office Management System</strong>
</div>
';

// ================= GENERATE PDF =================
$pdf = new Dompdf();
$pdf->loadHtml($html);
$pdf->setPaper('A4', 'landscape');
$pdf->render();
$pdf->stream("laporan_catatan_kerja.pdf", ["Attachment" => false]);
