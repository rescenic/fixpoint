<?php
// ===================================================
// ERROR HANDLING AMAN PHP 8
// ===================================================
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 1);

// ===================================================
// LOAD DOMPDF
// ===================================================
require 'dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

include 'koneksi.php';
require_once __DIR__ . '/tte_hash_helper.php';

/* ===================================================
   FUNCTION TTE
=================================================== */
function getTteByUser($conn, $user_id) {
    if (empty($user_id)) return null;
    $q = mysqli_query($conn,"
        SELECT * FROM tte_user
        WHERE user_id='$user_id'
          AND status='aktif'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    return mysqli_fetch_assoc($q) ?: null;
}

function qrTte($token) {
    $url = "http://" . $_SERVER['HTTP_HOST'] . "/cek_tte.php?token=" . $token;
    return "https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=" . urlencode($url);
}

/* ===================================================
   VALIDASI
=================================================== */
if (!isset($_GET['id'])) die('ID tidak ditemukan');
$id = intval($_GET['id']);

/* ===================================================
   DATA IZIN PULANG CEPAT
=================================================== */
$qIzin = mysqli_query($conn,"
    SELECT i.*, u.nik, u.nama, u.jabatan, u.unit_kerja
    FROM izin_pulang_cepat i
    JOIN users u ON i.user_id = u.id
    WHERE i.id='$id'
");
$data = mysqli_fetch_assoc($qIzin);
if (!$data) die('Data tidak ditemukan');

/* ===================================================
   TTE
=================================================== */
$tte_pemohon = getTteByUser($conn, $data['user_id']);
$tte_atasan  = !empty($data['acc_oleh_atasan']) ? getTteByUser($conn, $data['acc_oleh_atasan']) : null;
$tte_sdm     = !empty($data['acc_oleh_sdm'])    ? getTteByUser($conn, $data['acc_oleh_sdm'])    : null;

$qr_pemohon = $tte_pemohon ? qrTte($tte_pemohon['token']) : '';
$qr_atasan  = $tte_atasan  ? qrTte($tte_atasan['token'])  : '';
$qr_sdm     = $tte_sdm     ? qrTte($tte_sdm['token'])     : '';

/* ===================================================
   DATA PERUSAHAAN
=================================================== */
$qPer = mysqli_query($conn,"SELECT * FROM perusahaan LIMIT 1");
$perusahaan = mysqli_fetch_assoc($qPer);

/* ===================================================
   STATUS ACC
=================================================== */
$status_acc = ($tte_atasan && $tte_sdm);

/* ===================================================
   HTML
=================================================== */
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body{font-family:Helvetica,Arial,sans-serif;font-size:11px}
.page{padding:20px}
.card{border:2px solid #2c3e50;padding:15px}
.header{text-align:center;border-bottom:2px solid #2c3e50;padding-bottom:8px}
.header h2{margin:0;font-size:14px;color:#2c3e50}
.title{text-align:center;margin:12px 0;font-size:13px;font-weight:bold;color:#1f4fd8}
.info-line{margin-bottom:3px}
.label{width:130px;display:inline-block;font-weight:bold}
.qr{width:70px;margin-bottom:5px}
.tte-table{width:100%;margin-top:15px;border-collapse:collapse;font-size:10px}
.tte-table td{width:33%;text-align:center;vertical-align:top;padding:6px;border-top:1px dashed #999}
.tte-name{font-weight:bold;text-decoration:underline}
.status-ok{margin-top:12px;padding:6px;text-align:center;font-weight:bold;background:#d4edda;color:#155724;border:1px solid #28a745}
.footer{margin-top:15px;font-size:9px;text-align:center;color:#555;border-top:1px solid #ccc;padding-top:5px}
</style>
</head>

<body>
<div class="page">
<div class="card">

<div class="header">
  <h2>'.htmlspecialchars($perusahaan['nama_perusahaan']).'</h2>
  '.htmlspecialchars($perusahaan['alamat']).', '.htmlspecialchars($perusahaan['kota']).'
</div>

<div class="title">SURAT IZIN PULANG CEPAT PEGAWAI</div>

<div>
  <div class="info-line"><span class="label">Nama</span>: '.htmlspecialchars($data['nama']).'</div>
  <div class="info-line"><span class="label">NIK</span>: '.htmlspecialchars($data['nik']).'</div>
  <div class="info-line"><span class="label">Jabatan</span>: '.htmlspecialchars($data['jabatan']).'</div>
  <div class="info-line"><span class="label">Unit</span>: '.htmlspecialchars($data['unit_kerja']).'</div>
  <div class="info-line"><span class="label">Tanggal</span>: '.date('d-m-Y',strtotime($data['tanggal'])).'</div>
  <div class="info-line"><span class="label">Jam Pulang</span>: '.$data['jam_pulang'].' WIB</div>
</div>

<br>
<strong>Keperluan:</strong><br>
'.nl2br(htmlspecialchars($data['keperluan'])).'

<table class="tte-table">
<tr>
  <td><strong>Pemohon</strong></td>
  <td><strong>Atasan Langsung</strong></td>
  <td><strong>Bagian SDM</strong></td>
</tr>
<tr>

<td>'.
($tte_pemohon ? '
<img src="'.$qr_pemohon.'" class="qr"><br>
<div class="tte-name">'.htmlspecialchars($tte_pemohon['nama']).'</div>
'.htmlspecialchars($tte_pemohon['jabatan']).'
' : '<em>Belum ditandatangani</em>').'
</td>

<td>'.
($tte_atasan ? '
<img src="'.$qr_atasan.'" class="qr"><br>
<div class="tte-name">'.htmlspecialchars($tte_atasan['nama']).'</div>
'.htmlspecialchars($tte_atasan['jabatan']).'
' : '<em>Belum disetujui</em>').'
</td>

<td>'.
($tte_sdm ? '
<img src="'.$qr_sdm.'" class="qr"><br>
<div class="tte-name">'.htmlspecialchars($tte_sdm['nama']).'</div>
'.htmlspecialchars($tte_sdm['jabatan']).'
' : '<em>Belum disetujui</em>').'
</td>

</tr>
</table>';

if ($status_acc) {
    $html .= '<div class="status-ok">✅ TELAH DISETUJUI UNTUK IZIN PULANG CEPAT</div>';
}

$html .= '
<div class="footer">
<strong>Tanda Tangan Elektronik (TTE) Non Sertifikasi</strong><br>
Dokumen ini menggunakan TTE Non Sertifikasi untuk penggunaan internal perusahaan<br>
sesuai <em>PP No. 71 Tahun 2019</em> dan <em>UU ITE No. 11 Tahun 2008 jo. UU No. 19 Tahun 2016</em><br>
Dokumen dihasilkan oleh <strong>FixPoint – Smart Office Management System</strong>
</div>

</div>
</div>
</body>
</html>';

/* ===================================================
   GENERATE PDF
=================================================== */
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$pdf = new Dompdf($options);
$pdf->loadHtml($html);
$pdf->setPaper('A4','portrait');
$pdf->render();

/* ===================================================
   EMBED TOKEN & SAVE
=================================================== */
$pdf_output = $pdf->output();

$tokens=[];
if($tte_pemohon) $tokens[]=$tte_pemohon['token'];
if($tte_atasan)  $tokens[]=$tte_atasan['token'];
if($tte_sdm)     $tokens[]=$tte_sdm['token'];

if($tokens){
    $txt="\n";
    foreach($tokens as $t){ $txt.="TTE-TOKEN:$t\n"; }
    $pdf_output=str_replace('%%EOF',$txt.'%%EOF',$pdf_output);
}

$dir=__DIR__.'/uploads/signed/';
if(!is_dir($dir)) @mkdir($dir,0755,true);

$filename='izin_pulang_cepat_'.$data['nik'].'_'.time().'.pdf';
$path=$dir.$filename;
file_put_contents($path,$pdf_output);

// HASH + LOG
$hash=generateFileHash($path);
if($hash){
    if($tte_pemohon) saveDocumentSigningLog($conn,$tte_pemohon['token'],$data['user_id'],$filename,$hash);
    if($tte_atasan)  saveDocumentSigningLog($conn,$tte_atasan['token'],$data['acc_oleh_atasan'],$filename,$hash);
    if($tte_sdm)     saveDocumentSigningLog($conn,$tte_sdm['token'],$data['acc_oleh_sdm'],$filename,$hash);
}

/* ===================================================
   STREAM PDF
=================================================== */
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$filename.'"');
header('Content-Length: '.strlen($pdf_output));
echo $pdf_output;
exit;
