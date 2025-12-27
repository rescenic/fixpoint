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
    return "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($url);
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
if (!isset($_GET['id'])) die('ID pengajuan tidak ditemukan.');
$id = intval($_GET['id']);

// ===================================================
// DATA PERUSAHAAN (KOP SURAT)
// ===================================================
$q_perusahaan = $conn->query("SELECT * FROM perusahaan LIMIT 1");
$perusahaan = $q_perusahaan->fetch_assoc();

// ===================================================
// QUERY DATA PENGAJUAN CUTI
// ===================================================
$sql = "SELECT p.*, u.id as pemohon_id, u.nik, u.nama, u.unit_kerja, u.jabatan,
               mc.nama_cuti, 
               d.id as delegasi_user_id, d.nama AS nama_delegasi,
               COUNT(pc.id) AS lama_hari,
               GROUP_CONCAT(DATE_FORMAT(pc.tanggal,'%d-%m-%Y') ORDER BY pc.tanggal SEPARATOR ', ') AS tanggal_cuti
        FROM pengajuan_cuti p
        JOIN users u ON p.karyawan_id = u.id
        JOIN master_cuti mc ON p.cuti_id = mc.id
        LEFT JOIN users d ON p.delegasi_id = d.id
        LEFT JOIN pengajuan_cuti_detail pc ON pc.pengajuan_id = p.id
        WHERE p.id = ?
        GROUP BY p.id";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) die("Data tidak ditemukan!");

// ===================================================
// GET TTE UNTUK SEMUA PIHAK
// ===================================================
$tte_pemohon  = getTteByUser($conn, $data['pemohon_id']);
$tte_delegasi = !empty($data['delegasi_user_id']) && $data['status_delegasi'] == 'Disetujui' 
                ? getTteByUser($conn, $data['delegasi_user_id']) 
                : null;

// Get user_id untuk atasan dan HRD dari nama yang tersimpan
$tte_atasan = null;
$tte_hrd = null;

if (!empty($data['acc_atasan_by']) && $data['status_atasan'] == 'Disetujui') {
    $qAtasan = mysqli_query($conn, "SELECT id FROM users WHERE nama = '".mysqli_real_escape_string($conn, $data['acc_atasan_by'])."' LIMIT 1");
    $atasan_data = mysqli_fetch_assoc($qAtasan);
    if ($atasan_data) {
        $tte_atasan = getTteByUser($conn, $atasan_data['id']);
    }
}

if (!empty($data['acc_hrd_by']) && $data['status_hrd'] == 'Disetujui') {
    $qHrd = mysqli_query($conn, "SELECT id FROM users WHERE nama = '".mysqli_real_escape_string($conn, $data['acc_hrd_by'])."' LIMIT 1");
    $hrd_data = mysqli_fetch_assoc($qHrd);
    if ($hrd_data) {
        $tte_hrd = getTteByUser($conn, $hrd_data['id']);
    }
}

// Generate QR Code
$qr_pemohon  = $tte_pemohon  ? qrTte($tte_pemohon['token'])  : '';
$qr_delegasi = $tte_delegasi ? qrTte($tte_delegasi['token']) : '';
$qr_atasan   = $tte_atasan   ? qrTte($tte_atasan['token'])   : '';
$qr_hrd      = $tte_hrd      ? qrTte($tte_hrd['token'])      : '';

// ===================================================
// SIAPKAN NAMA TANDA TANGAN
// ===================================================
$pemohon  = $data['nama'] ?: '........................';
$delegasi = $data['nama_delegasi'] ?: '........................';
$atasan   = $data['acc_atasan_by'] ?: '........................';
$hrd      = $data['acc_hrd_by'] ?: '........................';

// ===================================================
// STATUS APPROVAL
// ===================================================
$status_full_approved = ($data['status_delegasi'] == 'Disetujui' && 
                         $data['status_atasan'] == 'Disetujui' && 
                         $data['status_hrd'] == 'Disetujui');

// ===================================================
// HTML UNTUK PDF BERBENTUK SURAT
// ===================================================
$html = '
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<style>
body { 
  font-family: "Times New Roman", serif; 
  font-size: 12pt; 
  line-height: 1.6; 
  color: #000; 
  margin: 0;
  padding: 20px;
}
.header { 
  text-align: center; 
  border-bottom: 3px solid #2c3e50; 
  padding-bottom: 10px; 
  margin-bottom: 20px; 
}
.header .nama-perusahaan { 
  font-size: 18pt; 
  font-weight: bold; 
  text-transform: uppercase; 
  color: #2c3e50;
  margin-bottom: 5px;
}
.header .alamat { 
  font-size: 10pt; 
  color: #555; 
}
.content { 
  margin-top: 25px; 
  text-align: justify; 
}
.ttd {
  width: 100%;
  margin-top: 30px;
  text-align: center;
  border-collapse: collapse;
}
.ttd td {
  width: 25%;
  vertical-align: top;
  padding: 8px;
  border-top: 1px dashed #ccc;
}
.ttd .jabatan {
  font-style: italic;
  font-size: 10pt;
  color: #666;
  margin-bottom: 10px;
  font-weight: bold;
}
.ttd .qr-code {
  width: 90px;
  height: 90px;
  margin: 5px auto;
}
.ttd .name {
  font-weight: bold;
  text-decoration: underline;
  margin-top: 8px;
  font-size: 11pt;
}
.ttd .detail {
  font-size: 9pt;
  color: #666;
  margin-top: 3px;
}
.status-approved {
  margin-top: 20px;
  padding: 10px;
  text-align: center;
  font-weight: bold;
  background: #d4edda;
  color: #155724;
  border: 2px solid #28a745;
  border-radius: 5px;
}
.footer {
  margin-top: 25px;
  padding-top: 10px;
  border-top: 1px solid #ddd;
  text-align: center;
  font-size: 9pt;
  color: #666;
}
</style>
</head>

<body>

<div class="header">
  <div class="nama-perusahaan">'.htmlspecialchars($perusahaan['nama_perusahaan']).'</div>
  <div class="alamat">
    '.htmlspecialchars($perusahaan['alamat']).', '.htmlspecialchars($perusahaan['kota']).', '.htmlspecialchars($perusahaan['provinsi']).'<br>
    Telp: '.htmlspecialchars($perusahaan['kontak']).' | Email: '.htmlspecialchars($perusahaan['email']).'
  </div>
</div>

<div style="text-align:right; margin-bottom:20px; font-size: 11pt;">
'.htmlspecialchars($perusahaan['kota']).', '.date("d F Y").'
</div>

<div class="content">
<strong>Kepada Yth,</strong><br>
<strong>HRD '.htmlspecialchars($perusahaan['nama_perusahaan']).'</strong><br>
<strong>di Tempat</strong>
<br><br>

Dengan hormat,<br>
Saya yang bertanda tangan di bawah ini:<br><br>

<table style="border: none; font-size: 11pt; line-height: 1.8;">
<tr>
  <td style="width: 120px; vertical-align: top;">Nama</td>
  <td style="width: 10px; vertical-align: top;">:</td>
  <td><strong>'.htmlspecialchars($data['nama']).'</strong></td>
</tr>
<tr>
  <td style="vertical-align: top;">NIK</td>
  <td style="vertical-align: top;">:</td>
  <td><strong>'.htmlspecialchars($data['nik']).'</strong></td>
</tr>
<tr>
  <td style="vertical-align: top;">Jabatan</td>
  <td style="vertical-align: top;">:</td>
  <td>'.htmlspecialchars($data['jabatan'] ?? "-").'</td>
</tr>
<tr>
  <td style="vertical-align: top;">Unit Kerja</td>
  <td style="vertical-align: top;">:</td>
  <td>'.htmlspecialchars($data['unit_kerja']).'</td>
</tr>
</table>

<br>
Mengajukan permohonan cuti <strong>'.htmlspecialchars($data['nama_cuti']).'</strong> selama <strong>'.$data['lama_hari'].' hari</strong>, 
pada tanggal <strong>'.htmlspecialchars($data['tanggal_cuti']).'</strong>.<br><br>

<strong>Alasan cuti:</strong><br>
'.nl2br(htmlspecialchars($data['alasan'])).'<br><br>

Delegasi tugas selama cuti kepada: <strong>'.htmlspecialchars($delegasi).'</strong>.<br><br>

Demikian permohonan ini saya ajukan, atas perhatian dan persetujuannya saya ucapkan terima kasih.
</div>

<table class="ttd">
<tr>
  <td><div class="jabatan">Pemohon</div></td>
  <td><div class="jabatan">Delegasi</div></td>
  <td><div class="jabatan">Atasan</div></td>
  <td><div class="jabatan">HRD</div></td>
</tr>
<tr>
  
  <!-- Pemohon -->
  <td>'.
  ($tte_pemohon ? '
    <img src="'.$qr_pemohon.'" class="qr-code"><br>
    <div class="name">'.htmlspecialchars($tte_pemohon['nama']).'</div>
    <div class="detail">'.htmlspecialchars($tte_pemohon['jabatan']).'</div>
    <div class="detail"><small>'.date('d-m-Y H:i', strtotime($tte_pemohon['created_at'])).' WIB</small></div>
  ' : '
    <div style="height: 90px; display: flex; align-items: center; justify-content: center; border: 1px dashed #ccc; margin: 5px auto; width: 90px;">
      <em style="font-size: 9pt; color: #999;">Belum TTE</em>
    </div>
    <div class="name">'.htmlspecialchars($pemohon).'</div>
  ').'
  </td>

  <!-- Delegasi -->
  <td>'.
  ($tte_delegasi ? '
    <img src="'.$qr_delegasi.'" class="qr-code"><br>
    <div class="name">'.htmlspecialchars($tte_delegasi['nama']).'</div>
    <div class="detail">'.htmlspecialchars($tte_delegasi['jabatan']).'</div>
    <div class="detail"><small>'.date('d-m-Y H:i', strtotime($data['acc_delegasi_time'] ?: $tte_delegasi['created_at'])).' WIB</small></div>
  ' : '
    <div style="height: 90px; display: flex; align-items: center; justify-content: center; border: 1px dashed #ccc; margin: 5px auto; width: 90px;">
      <em style="font-size: 9pt; color: #999;">'.($data['status_delegasi'] == 'Ditolak' ? 'Ditolak' : 'Menunggu').'</em>
    </div>
    <div class="name">'.htmlspecialchars($delegasi).'</div>
  ').'
  </td>

  <!-- Atasan -->
  <td>'.
  ($tte_atasan ? '
    <img src="'.$qr_atasan.'" class="qr-code"><br>
    <div class="name">'.htmlspecialchars($tte_atasan['nama']).'</div>
    <div class="detail">'.htmlspecialchars($tte_atasan['jabatan']).'</div>
    <div class="detail"><small>'.date('d-m-Y H:i', strtotime($data['acc_atasan_time'] ?: $tte_atasan['created_at'])).' WIB</small></div>
  ' : '
    <div style="height: 90px; display: flex; align-items: center; justify-content: center; border: 1px dashed #ccc; margin: 5px auto; width: 90px;">
      <em style="font-size: 9pt; color: #999;">'.($data['status_atasan'] == 'Ditolak' ? 'Ditolak' : 'Menunggu').'</em>
    </div>
    <div class="name">'.htmlspecialchars($atasan).'</div>
  ').'
  </td>

  <!-- HRD -->
  <td>'.
  ($tte_hrd ? '
    <img src="'.$qr_hrd.'" class="qr-code"><br>
    <div class="name">'.htmlspecialchars($tte_hrd['nama']).'</div>
    <div class="detail">'.htmlspecialchars($tte_hrd['jabatan']).'</div>
    <div class="detail"><small>'.date('d-m-Y H:i', strtotime($data['acc_hrd_time'] ?: $tte_hrd['created_at'])).' WIB</small></div>
  ' : '
    <div style="height: 90px; display: flex; align-items: center; justify-content: center; border: 1px dashed #ccc; margin: 5px auto; width: 90px;">
      <em style="font-size: 9pt; color: #999;">'.($data['status_hrd'] == 'Ditolak' ? 'Ditolak' : 'Menunggu').'</em>
    </div>
    <div class="name">'.htmlspecialchars($hrd).'</div>
  ').'
  </td>

</tr>
</table>';

// Status Approval
if ($status_full_approved) {
    $html .= '<div class="status-approved">✅ CUTI TELAH DISETUJUI LENGKAP</div>';
}

$html .= '
<div class="footer">
  Dokumen ini ditandatangani secara elektronik menggunakan<br>
  <strong>TTE Non Sertifikasi - FixPoint Smart Office Management System</strong><br>
  Dicetak pada: '.date('d F Y H:i:s').' WIB
</div>

</body>
</html>';

// ===================================================
// GENERATE PDF (WAJIB remote enabled untuk QR Code)
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
if ($tte_pemohon)  $tokens_to_embed[] = $tte_pemohon['token'];
if ($tte_delegasi) $tokens_to_embed[] = $tte_delegasi['token'];
if ($tte_atasan)   $tokens_to_embed[] = $tte_atasan['token'];
if ($tte_hrd)      $tokens_to_embed[] = $tte_hrd['token'];

if (!empty($tokens_to_embed)) {
    $token_text = "\n";
    foreach ($tokens_to_embed as $token) {
        $token_text .= "TTE-TOKEN:" . $token . "\n";
    }
    // Insert before %%EOF
    $pdf_output = str_replace('%%EOF', $token_text . '%%EOF', $pdf_output);
}

// ===================================================
// SAVE PDF & LOG TTE
// ===================================================
$output_dir = __DIR__ . '/uploads/signed/';
if (!is_dir($output_dir)) {
    @mkdir($output_dir, 0755, true);
}

$filename = 'surat_cuti_' . $data['nik'] . '_' . time() . '.pdf';
$filepath = $output_dir . $filename;

// Save PDF output yang SAMA PERSIS
file_put_contents($filepath, $pdf_output);

// Generate file hash dari file yang disimpan
$file_hash = generateFileHash($filepath);

// Log document signing for each TTE present
if ($file_hash) {
    // Log pemohon TTE
    if ($tte_pemohon) {
        saveDocumentSigningLog($conn, $tte_pemohon['token'], $data['pemohon_id'], $filename, $file_hash);
    }
    
    // Log delegasi TTE
    if ($tte_delegasi && !empty($data['delegasi_user_id'])) {
        saveDocumentSigningLog($conn, $tte_delegasi['token'], $data['delegasi_user_id'], $filename, $file_hash);
    }
    
    // Log atasan TTE
    if ($tte_atasan && !empty($atasan_data['id'])) {
        saveDocumentSigningLog($conn, $tte_atasan['token'], $atasan_data['id'], $filename, $file_hash);
    }
    
    // Log HRD TTE
    if ($tte_hrd && !empty($hrd_data['id'])) {
        saveDocumentSigningLog($conn, $tte_hrd['token'], $hrd_data['id'], $filename, $file_hash);
    }
}

// ===================================================
// STREAM PDF TO BROWSER
// ===================================================
// Set headers untuk download/view
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="surat_cuti_'.$data['nik'].'_'.$data['id'].'.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($pdf_output));

// Output PDF yang SAMA dengan yang disimpan
echo $pdf_output;
exit;