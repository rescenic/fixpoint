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

// ===================================================
// FUNCTION TTE - UPDATED
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
    return mysqli_fetch_assoc($q) ?: null;
}

function qrTte($token) {
    $url = "http://" . $_SERVER['HTTP_HOST'] . "/cek_tte.php?token=" . $token;
    return "https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=" . urlencode($url);
}

// NEW: Get actual signing timestamp from document_log
function getTteSigningTime($conn, $token, $document_hash = null) {
    if (empty($token)) return null;
    
    $query = "SELECT signed_at FROM tte_document_log 
              WHERE tte_token = ?";
    
    if ($document_hash) {
        $query .= " AND document_hash = ?";
        $stmt = $conn->prepare($query . " ORDER BY signed_at DESC LIMIT 1");
        $stmt->bind_param("ss", $token, $document_hash);
    } else {
        $stmt = $conn->prepare($query . " ORDER BY signed_at DESC LIMIT 1");
        $stmt->bind_param("s", $token);
    }
    
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result ? $result['signed_at'] : null;
}

// ===================================================
// VALIDASI
// ===================================================
if (!isset($_GET['id'])) die('ID tidak ditemukan');
$id = intval($_GET['id']);

// ===================================================
// DATA IZIN
// ===================================================
$qIzin = mysqli_query($conn, "
    SELECT i.*, u.nik, u.nama, u.jabatan, u.unit_kerja 
    FROM izin_keluar i
    JOIN users u ON i.user_id = u.id
    WHERE i.id = '$id'
");
$data = mysqli_fetch_assoc($qIzin);
if (!$data) die('Data tidak ditemukan');

// ===================================================
// TTE
// ===================================================
$tte_pemohon = getTteByUser($conn, $data['user_id']);
$tte_atasan  = !empty($data['acc_oleh_atasan']) ? getTteByUser($conn, $data['acc_oleh_atasan']) : null;
$tte_sdm     = !empty($data['acc_oleh_sdm'])    ? getTteByUser($conn, $data['acc_oleh_sdm'])    : null;

$qr_pemohon = $tte_pemohon ? qrTte($tte_pemohon['token']) : '';
$qr_atasan  = $tte_atasan  ? qrTte($tte_atasan['token'])  : '';
$qr_sdm     = $tte_sdm     ? qrTte($tte_sdm['token'])     : '';

// ===================================================
// DATA PERUSAHAAN
// ===================================================
$qPer = mysqli_query($conn, "SELECT * FROM perusahaan LIMIT 1");
$perusahaan = mysqli_fetch_assoc($qPer);

// ===================================================
// JAM
// ===================================================
$jamKembali = '-';
if (!empty($data['jam_kembali_real'])) {
    $jamKembali = date('d-m-Y H:i', strtotime($data['jam_kembali_real'])) . ' WIB';
}

// ===================================================
// STATUS ACC
// ===================================================
$status_acc = ($tte_atasan && $tte_sdm);

// ===================================================
// HTML
// ===================================================
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; }
.page { width: 100%; padding: 20px; }
.card { border: 2px solid #2c3e50; padding: 15px; }
.header { text-align: center; border-bottom: 2px solid #2c3e50; padding-bottom: 8px; }
.header h2 { margin: 0; font-size: 14px; color: #2c3e50; }
.title { text-align: center; margin: 12px 0; font-size: 13px; font-weight: bold; color: #1f4fd8; }
.info-line { margin-bottom: 3px; }
.label { width: 120px; display: inline-block; font-weight: bold; }
.qr { width: 70px; margin-bottom: 5px; }
.tte-table { width: 100%; margin-top: 15px; border-collapse: collapse; font-size: 10px; }
.tte-table td { width: 33%; text-align: center; vertical-align: top; padding: 6px; border-top: 1px dashed #999; }
.tte-name { font-weight: bold; text-decoration: underline; }
.status-ok { margin-top: 12px; padding: 6px; text-align: center; font-weight: bold; background: #d4edda; color: #155724; border: 1px solid #28a745; }
.footer { margin-top: 15px; font-size: 9px; text-align: center; color: #555; border-top: 1px solid #ccc; padding-top: 5px; }
</style>
</head>

<body>
<div class="page">
<div class="card">

<div class="header">
  <h2>'.htmlspecialchars($perusahaan['nama_perusahaan']).'</h2>
  '.htmlspecialchars($perusahaan['alamat']).', '.htmlspecialchars($perusahaan['kota']).'
</div>

<div class="title">SURAT IZIN KELUAR PEGAWAI</div>

<div>
  <div class="info-line"><span class="label">Nama</span>: '.htmlspecialchars($data['nama']).'</div>
  <div class="info-line"><span class="label">NIK</span>: '.htmlspecialchars($data['nik']).'</div>
  <div class="info-line"><span class="label">Jabatan</span>: '.htmlspecialchars($data['jabatan']).'</div>
  <div class="info-line"><span class="label">Unit</span>: '.htmlspecialchars($data['unit_kerja']).'</div>
  <div class="info-line"><span class="label">Tanggal</span>: '.date('d-m-Y', strtotime($data['tanggal'])).'</div>
  <div class="info-line"><span class="label">Jam Keluar</span>: '.$data['jam_keluar'].' WIB</div>
  <div class="info-line"><span class="label">Jam Kembali</span>: '.$jamKembali.'</div>
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
'.htmlspecialchars($tte_pemohon['jabatan']).'<br>
<small>'.date('d-m-Y H:i', strtotime($tte_pemohon['created_at'])).'</small>
' : '<em>Belum ditandatangani</em>').'
</td>

<td>'.
($tte_atasan ? '
<img src="'.$qr_atasan.'" class="qr"><br>
<div class="tte-name">'.htmlspecialchars($tte_atasan['nama']).'</div>
'.htmlspecialchars($tte_atasan['jabatan']).'<br>
<small>'.date('d-m-Y H:i', strtotime($tte_atasan['created_at'])).'</small>
' : '<em>Belum disetujui</em>').'
</td>

<td>'.
($tte_sdm ? '
<img src="'.$qr_sdm.'" class="qr"><br>
<div class="tte-name">'.htmlspecialchars($tte_sdm['nama']).'</div>
'.htmlspecialchars($tte_sdm['jabatan']).'<br>
<small>'.date('d-m-Y H:i', strtotime($tte_sdm['created_at'])).'</small>
' : '<em>Belum disetujui</em>').'
</td>

</tr>
</table>';

if ($status_acc) {
    $html .= '<div class="status-ok">✅ TELAH DISETUJUI UNTUK IZIN KELUAR</div>';
}

$html .= '
<div class="footer">
TTE Non Sertifikasi di-generate melalui aplikasi
<strong>FixPoint – Smart Office Management System</strong>
</div>

</div>
</div>
</body>
</html>';

// ===================================================
// GENERATE PDF (WAJIB remote enabled)
// ===================================================
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$pdf = new Dompdf($options);
$pdf->loadHtml($html);
$pdf->setPaper('A4', 'portrait');
$pdf->render();

// ===================================================
// GET PDF OUTPUT & EMBED TOKENS
// ===================================================
$pdf_output = $pdf->output(); // Generate sekali saja!

// Embed ALL tokens di PDF stream (before %%EOF)
$tokens_to_embed = [];
if ($tte_pemohon) $tokens_to_embed[] = $tte_pemohon['token'];
if ($tte_atasan) $tokens_to_embed[] = $tte_atasan['token'];
if ($tte_sdm) $tokens_to_embed[] = $tte_sdm['token'];

if (!empty($tokens_to_embed)) {
    $token_text = "\n";
    foreach ($tokens_to_embed as $token) {
        $token_text .= "TTE-TOKEN:" . $token . "\n";
    }
    // Insert before %%EOF
    $pdf_output = str_replace('%%EOF', $token_text . '%%EOF', $pdf_output);
}

// ===================================================
// SAVE PDF & LOG TTE - NEW!
// ===================================================
$output_dir = __DIR__ . '/uploads/signed/';
if (!is_dir($output_dir)) {
    @mkdir($output_dir, 0755, true);
}

$filename = 'izin_keluar_' . $data['nik'] . '_' . time() . '.pdf';
$filepath = $output_dir . $filename;

// Save PDF output yang SAMA PERSIS
file_put_contents($filepath, $pdf_output);

// Generate file hash dari file yang disimpan
$file_hash = generateFileHash($filepath);

// Log document signing for each TTE present
if ($file_hash) {
    // Log pemohon TTE
    if ($tte_pemohon) {
        saveDocumentSigningLog($conn, $tte_pemohon['token'], $data['user_id'], $filename, $file_hash);
    }
    
    // Log atasan TTE
    if ($tte_atasan) {
        saveDocumentSigningLog($conn, $tte_atasan['token'], $data['acc_oleh_atasan'], $filename, $file_hash);
    }
    
    // Log SDM TTE
    if ($tte_sdm) {
        saveDocumentSigningLog($conn, $tte_sdm['token'], $data['acc_oleh_sdm'], $filename, $file_hash);
    }
}

// ===================================================
// STREAM PDF TO BROWSER
// ===================================================
// Set headers untuk download/view
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="izin_keluar_'.$data['nik'].'.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($pdf_output));

// Output PDF yang SAMA dengan yang disimpan
echo $pdf_output;
exit;