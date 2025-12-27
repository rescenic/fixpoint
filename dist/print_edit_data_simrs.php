<?php
// ===================================================
// LOAD DOMPDF
// ===================================================
require 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;

include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// ===================================================
// FUNCTION TTE
// ===================================================
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

// ===================================================
// VALIDASI
// ===================================================
if (!isset($_GET['id'])) {
    die('ID permintaan tidak ditemukan.');
}
$id = intval($_GET['id']);

// ===================================================
// DATA PERMINTAAN
// ===================================================
$query = $conn->query("
    SELECT p.*, 
           u.nik, u.nama, u.jabatan, u.unit_kerja,
           a.nama AS nama_atasan, a.nik AS nik_atasan, a.jabatan AS jabatan_atasan
    FROM permintaan_edit_data p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN users a ON p.atasan_id = a.id
    WHERE p.id = '$id'
");
$data = $query->fetch_assoc();
if (!$data) die('Data tidak ditemukan.');

// ===================================================
// TTE
// ===================================================
$tte_pemohon = getTteByUser($conn, $data['user_id']);
$tte_atasan  = !empty($data['atasan_id']) ? getTteByUser($conn, $data['atasan_id']) : null;

$qr_pemohon = $tte_pemohon ? qrTte($tte_pemohon['token']) : '';
$qr_atasan  = $tte_atasan  ? qrTte($tte_atasan['token'])  : '';

// ===================================================
// STATUS
// ===================================================
$status_text = [
    "Menunggu Persetujuan Atasan" => "MENUNGGU PERSETUJUAN ATASAN",
    "Disetujui Atasan"           => "DISETUJUI ATASAN",
    "Ditolak Atasan"             => "DITOLAK ATASAN"
];
$stempel_status = $status_text[$data['status']] ?? "-";

// ===================================================
// DATA PERUSAHAAN
// ===================================================
$q_perusahaan = $conn->query("SELECT * FROM perusahaan LIMIT 1");
$perusahaan = $q_perusahaan->fetch_assoc();

// ===================================================
// FORMAT TANGGAL
// ===================================================
$tanggal_pengajuan = date('d-m-Y', strtotime($data['tanggal']));
$tanggal_acc = $data['tanggal_acc'] ? date('d-m-Y', strtotime($data['tanggal_acc'])) : '-';

// ===================================================
// HTML
// ===================================================
$html = '
<style>
body { font-family: Arial, sans-serif; font-size: 11px; margin:22px; }

table.kop { width:100%; border-bottom:2px solid #000; margin-bottom:8px; }
.kop-text { text-align:center; font-size:15px; font-weight:bold; }
.subkop { text-align:center; font-size:10px; }

.title { text-align:center; font-size:14px; font-weight:bold; margin:12px 0; }

.info .label { width:170px; display:inline-block; font-weight:bold; }

.content-box {
    border:1px solid #000; padding:10px; min-height:70px; line-height:1.4; margin-top:6px;
}

.tte-table {
    width:100%;
    margin-top:30px;
    border-collapse:collapse;
    font-size:10px;
}
.tte-table td {
    width:50%;
    text-align:center;
    vertical-align:top;
    padding:8px;
    border-top:1px dashed #999;
}
.qr {
    width:70px;
    margin-bottom:4px;
}
.tte-name {
    font-weight:bold;
    text-decoration:underline;
}

.footer { margin-top:25px; font-size:10px; text-align:center; color:#555; }
</style>

<!-- KOP -->
<table class="kop">
<tr><td class="kop-text">'.strtoupper($perusahaan['nama_perusahaan']).'</td></tr>
<tr><td class="subkop">'.$perusahaan['alamat'].' - '.$perusahaan['kota'].', '.$perusahaan['provinsi'].'</td></tr>
<tr><td class="subkop">Telp: '.$perusahaan['kontak'].' | Email: '.$perusahaan['email'].'</td></tr>
</table>

<div class="title">FORM PERMOHONAN EDIT DATA SIMRS</div>

<div class="info">
    <div><span class="label">Nomor Surat</span>: <b>'.$data['nomor_surat'].'</b></div>
    <div><span class="label">Tanggal Permohonan</span>: '.$tanggal_pengajuan.'</div>
    <div><span class="label">Status Permohonan</span>: <b>'.$stempel_status.'</b></div>
    <div><span class="label">NIK Pemohon</span>: '.$data['nik'].'</div>
    <div><span class="label">Nama Pemohon</span>: '.htmlspecialchars($data['nama']).'</div>
    <div><span class="label">Jabatan</span>: '.htmlspecialchars($data['jabatan']).'</div>
    <div><span class="label">Unit Kerja</span>: '.htmlspecialchars($data['unit_kerja']).'</div>
</div>

<b>DATA YANG DIMINTA UNTUK DIUBAH</b>

<b>Data Lama:</b>
<div class="content-box">'.nl2br(htmlspecialchars($data['data_lama'])).'</div>

<b>Data Baru:</b>
<div class="content-box">'.nl2br(htmlspecialchars($data['data_baru'])).'</div>

<b>Alasan Perubahan:</b>
<div class="content-box">'.nl2br(htmlspecialchars($data['alasan'])).'</div>

<b>HASIL PERSETUJUAN ATASAN</b>
<div class="content-box">
Status: <b>'.$stempel_status.'</b><br><br>
Catatan Atasan:<br>
'.nl2br(htmlspecialchars($data['catatan_atasan'] ?: "-")).'<br><br>
Tanggal Persetujuan: '.$tanggal_acc.'
</div>

<!-- TTE -->
<table class="tte-table">
<tr>
<td><strong>Pemohon</strong></td>
<td><strong>Atasan Langsung</strong></td>
</tr>
<tr>
<td>
'.($tte_pemohon ? '
<img src="'.$qr_pemohon.'" class="qr"><br>
<div class="tte-name">'.$tte_pemohon['nama'].'</div>
'.$tte_pemohon['jabatan'].'<br>
' : '<em>TTE tidak tersedia</em>').'
</td>

<td>
'.($tte_atasan ? '
<img src="'.$qr_atasan.'" class="qr"><br>
<div class="tte-name">'.$tte_atasan['nama'].'</div>
'.$tte_atasan['jabatan'].'<br>
' : '<em>Belum disetujui atasan</em>').'
</td>
</tr>
</table>

'.($tte_pemohon && $tte_atasan ? '
<div style="
    margin-top:15px;
    padding:6px;
    text-align:center;
    font-weight:bold;
    background:#e8fff2;
    border:1px solid #2ecc71;
    color:#145a32;
">
✔ DOKUMEN TELAH DITANDATANGANI SECARA ELEKTRONIK
</div>
' : '').'

<div class="footer">
TTE Non Sertifikasi di-generate melalui aplikasi
<strong>FixPoint – Smart Office Management System</strong>
</div>
';

// ===================================================
// GENERATE PDF
// ===================================================
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// ===================================================
// WATERMARK OPSIONAL
// ===================================================
$canvas = $dompdf->getCanvas();
$canvas->set_opacity(0.05);
$img = 'assets/watermark.jpg';
if (file_exists($img)) {
    $canvas->image($img, 150, 220, 300, 180);
}

// ===================================================
// STREAM
// ===================================================
$dompdf->stream("Permohonan_Edit_Data_SIMRS_$id.pdf", ["Attachment" => false]);
