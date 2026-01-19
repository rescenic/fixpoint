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
    return mysqli_fetch_assoc($q) ?: null;
}

function qrTte($token) {
    $url = "http://" . $_SERVER['HTTP_HOST'] . "/cek_tte.php?token=" . $token;
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
if (!$data) {
    die('Data tidak ditemukan.');
}

// ===================================================
// TTE PEMOHON & ATASAN
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
// STATUS CLASS CSS
// ===================================================
$status_class = '';
switch($data['status']) {
    case 'Menunggu Persetujuan Atasan':
        $status_class = 'status-menunggu';
        break;
    case 'Disetujui Atasan':
        $status_class = 'status-selesai';
        break;
    case 'Ditolak Atasan':
        $status_class = 'status-ditolak';
        break;
    default:
        $status_class = '';
}

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
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { 
    font-family: Arial, Helvetica, sans-serif; 
    font-size: 10pt; 
    margin: 0;
    padding: 15px;
    line-height: 1.3;
}

.page { 
    width: 100%; 
    padding: 0px; 
}

.card { 
    border: 2px solid #2c3e50; 
    padding: 12px; 
}

/* KOP SURAT */
.header { 
    text-align: center; 
    border-bottom: 2px solid #2c3e50; 
    padding-bottom: 8px;
    margin-bottom: 10px;
}

.header h2 { 
    margin: 0 0 5px 0; 
    font-size: 14pt; 
    color: #2c3e50; 
    font-weight: bold;
    line-height: 1.2;
}

.header .subkop {
    font-size: 9pt;
    color: #555;
    line-height: 1.4;
}

/* TITLE */
.title { 
    text-align: center; 
    margin: 10px 0 8px 0; 
    font-size: 12pt; 
    font-weight: bold; 
    color: #1f4fd8;
    text-transform: uppercase;
    line-height: 1.2;
}

/* STATUS INLINE (TANPA KOTAK) */
.status-text {
    font-weight: bold;
    font-size: 10pt;
}

.status-menunggu { color: #856404; }
.status-diproses { color: #004085; }
.status-ditolak { color: #721c24; }
.status-selesai { color: #155724; }

/* INFO SECTION */
.info-section {
    margin: 8px 0;
    line-height: 1.4;
    font-size: 9pt;
}

.info-line { 
    margin-bottom: 3px; 
}

.label { 
    width: 160px; 
    display: inline-block; 
    font-weight: bold; 
}

/* SECTION BOX */
.section-title {
    font-weight: bold;
    margin-top: 8px;
    margin-bottom: 5px;
    font-size: 10pt;
}

.content-box {
    border: 1px solid #999; 
    padding: 8px; 
    min-height: 50px; 
    line-height: 1.4; 
    background-color: #f9f9f9;
    text-align: justify;
    font-size: 9pt;
    margin-bottom: 6px;
}

/* TTE TABLE */
.tte-table { 
    width: 100%; 
    margin-top: 10px; 
    border-collapse: collapse; 
    font-size: 9pt; 
}

.tte-table td { 
    width: 50%; 
    text-align: center; 
    vertical-align: top; 
    padding: 8px; 
    border-top: 1px dashed #999; 
}

.tte-table .role-label {
    font-weight: bold;
    margin-bottom: 5px;
    font-size: 10pt;
}

.qr { 
    width: 70px; 
    height: 70px;
    margin: 5px 0; 
}

.tte-name { 
    font-weight: bold; 
    text-decoration: underline; 
    margin-top: 3px;
    font-size: 9pt;
    line-height: 1.3;
}

.tte-info {
    font-size: 8pt;
    color: #555;
    margin-top: 2px;
    line-height: 1.3;
}

.not-signed {
    font-style: italic;
    color: #999;
    padding: 15px 0;
    font-size: 9pt;
}

/* FOOTER */
.footer { 
    margin-top: 12px; 
    font-size: 8pt; 
    text-align: center; 
    color: #666; 
    border-top: 1px solid #ccc; 
    padding-top: 8px;
    line-height: 1.4;
}

.footer strong {
    color: #2c3e50;
}

.footer .legal {
    margin-top: 4px;
    font-size: 7pt;
    color: #888;
    font-style: italic;
}

/* CATATAN ATASAN BOX */
.catatan-box {
    margin-top: 6px;
    padding: 8px;
    background-color: #e8f4fd;
    border: 1px solid #2196F3;
    border-radius: 3px;
}

.catatan-box .title {
    font-weight: bold;
    color: #004085;
    margin-bottom: 5px;
    font-size: 10pt;
}

.catatan-box .content {
    color: #004085;
    font-size: 9pt;
    line-height: 1.4;
}
</style>
</head>

<body>
<div class="page">
<div class="card">

<!-- KOP SURAT -->
<div class="header">
  <h2>'.strtoupper($perusahaan['nama_perusahaan']).'</h2>
  <div class="subkop">
    '.$perusahaan['alamat'].' - '.$perusahaan['kota'].', '.$perusahaan['provinsi'].'<br>
    Telp: '.$perusahaan['kontak'].' | Email: '.$perusahaan['email'].'
  </div>
</div>

<div class="title">Form Permohonan Edit Data SIMRS</div>

<!-- INFO SECTION -->
<div class="info-section">
    <div class="info-line">
        <span class="label">Nomor Surat</span>: <strong>'.$data['nomor_surat'].'</strong>
    </div>
    <div class="info-line">
        <span class="label">Tanggal Permohonan</span>: '.$tanggal_pengajuan.'
    </div>
    <div class="info-line">
        <span class="label">Status Permohonan</span>: <span class="status-text '.$status_class.'">'.$stempel_status.'</span>
    </div>
    <div class="info-line">
        <span class="label">NIK Pemohon</span>: '.$data['nik'].'
    </div>
    <div class="info-line">
        <span class="label">Nama Pemohon</span>: '.htmlspecialchars($data['nama']).'
    </div>
    <div class="info-line">
        <span class="label">Jabatan</span>: '.htmlspecialchars($data['jabatan']).'
    </div>
    <div class="info-line">
        <span class="label">Unit Kerja</span>: '.htmlspecialchars($data['unit_kerja']).'
    </div>
</div>

<!-- DATA YANG DIMINTA DIUBAH -->
<div class="section-title">Data Lama:</div>
<div class="content-box">'.nl2br(htmlspecialchars($data['data_lama'])).'</div>

<div class="section-title">Data Baru (yang Diajukan):</div>
<div class="content-box">'.nl2br(htmlspecialchars($data['data_baru'])).'</div>

<div class="section-title">Alasan Perubahan:</div>
<div class="content-box">'.nl2br(htmlspecialchars($data['alasan'])).'</div>';

// HASIL PERSETUJUAN ATASAN
$html .= '
<div class="section-title">Hasil Persetujuan Atasan:</div>
<div class="catatan-box">
    <div class="title">Status: '.$stempel_status.'</div>
    <div class="content">
        <strong>Tanggal Persetujuan:</strong> '.$tanggal_acc.'<br>';

if (!empty($data['catatan_atasan'])) {
    $html .= '<strong>Catatan:</strong><br>'.nl2br(htmlspecialchars($data['catatan_atasan']));
} else {
    $html .= '<em>Tidak ada catatan</em>';
}

$html .= '
    </div>
</div>';

// TANDA TANGAN TTE
$html .= '
<table class="tte-table">
<tr>
  <td><div class="role-label">Pemohon</div></td>
  <td><div class="role-label">Atasan Langsung</div></td>
</tr>
<tr>

<!-- PEMOHON -->
<td>';

if ($tte_pemohon) {
    $html .= '
    <img src="'.$qr_pemohon.'" class="qr"><br>
    <div class="tte-name">'.htmlspecialchars($data['nama']).'</div>
    <div class="tte-info">NIK: '.$data['nik'].'</div>
    <div class="tte-info">'.$perusahaan['kota'].', '.date('d-m-Y H:i', strtotime($tte_pemohon['created_at'])).'</div>';
} else {
    $html .= '<div class="not-signed">Belum ditandatangani</div>';
}

$html .= '</td>

<!-- ATASAN -->
<td>';

if ($tte_atasan) {
    $html .= '
    <img src="'.$qr_atasan.'" class="qr"><br>
    <div class="tte-name">'.htmlspecialchars($data['nama_atasan']).'</div>
    <div class="tte-info">NIK: '.$data['nik_atasan'].'</div>
    <div class="tte-info">'.htmlspecialchars($data['jabatan_atasan']).'</div>
    <div class="tte-info">'.$perusahaan['kota'].', '.date('d-m-Y H:i', strtotime($tte_atasan['created_at'])).'</div>';
} else {
    $html .= '<div class="not-signed">Belum disetujui</div>';
}

$html .= '</td>

</tr>
</table>';

$html .= '

<!-- FOOTER -->
<div class="footer">
<strong>Tanda Tangan Elektronik (TTE) Non Sertifikasi</strong><br>
Dokumen ini menggunakan TTE Non Sertifikasi yang sah untuk penggunaan internal perusahaan<br>
sesuai <em>Peraturan Pemerintah Nomor 71 Tahun 2019 tentang Penyelenggaraan Sistem dan Transaksi Elektronik</em><br>
dan <em>Undang-Undang Nomor 11 Tahun 2008 jo. UU No. 19 Tahun 2016 tentang Informasi dan Transaksi Elektronik (ITE)</em>
<div class="legal">
Dokumen ini di-generate melalui aplikasi <strong>FixPoint – Smart Office Management System</strong>
</div>
</div>

</div>
</div>
</body>
</html>';

// ===================================================
// GENERATE PDF (WAJIB remote enabled untuk QR)
// ===================================================
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$pdf = new Dompdf($options);
$pdf->loadHtml($html);
$pdf->setPaper('A4', 'portrait'); // A4 portrait karena banyak konten
$pdf->render();

// ===================================================
// GET PDF OUTPUT & EMBED TOKENS
// ===================================================
$pdf_output = $pdf->output();

// Embed ALL tokens di PDF stream (before %%EOF)
$tokens_to_embed = [];
if ($tte_pemohon) $tokens_to_embed[] = $tte_pemohon['token'];
if ($tte_atasan) $tokens_to_embed[] = $tte_atasan['token'];

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

$filename = 'edit_data_' . $data['nomor_surat'] . '_' . time() . '.pdf';
$filepath = $output_dir . $filename;

// Save PDF output
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
        saveDocumentSigningLog($conn, $tte_atasan['token'], $data['atasan_id'], $filename, $file_hash);
    }
}

// ===================================================
// STREAM PDF TO BROWSER
// ===================================================
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Permohonan_Edit_Data_'.$data['nomor_surat'].'.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($pdf_output));

// Output PDF yang SAMA dengan yang disimpan
echo $pdf_output;
exit;
?>