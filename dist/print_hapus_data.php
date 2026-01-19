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
    return "https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=" . urlencode($url);
}

// ===================================================
// VALIDASI
// ===================================================
if (!isset($_GET['id'])) {
    die('ID permintaan tidak ditemukan.');
}

$id = intval($_GET['id']);

// ================= AMBIL DATA PERMINTAAN =================
$query = $conn->query("
    SELECT p.*, u.nik, u.nama, u.jabatan, u.unit_kerja 
    FROM permintaan_hapus_data p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.id = '$id'
");

$data = $query->fetch_assoc();

if (!$data) {
    die('Data tidak ditemukan.');
}

// ================= TTE PEMOHON & VALIDATOR =================
$tte_pemohon = getTteByUser($conn, $data['user_id']);

// Ambil user_id validator (updated_by berisi nama, kita cari user_id-nya)
$validator_user_id = null;
if ($data['updated_by']) {
    $qValidator = $conn->query("SELECT id FROM users WHERE nama = '".$data['updated_by']."' LIMIT 1");
    if ($qValidator && $qValidator->num_rows > 0) {
        $validator_user_id = $qValidator->fetch_assoc()['id'];
    }
}

$tte_validator = $validator_user_id ? getTteByUser($conn, $validator_user_id) : null;

$qr_pemohon = $tte_pemohon ? qrTte($tte_pemohon['token']) : '';
$qr_validator = $tte_validator ? qrTte($tte_validator['token']) : '';

// ================= KONVERSI STATUS =================
$status_config = [
    "Menunggu" => ["text" => "MENUNGGU VERIFIKASI", "color" => "#856404"],
    "Diproses" => ["text" => "SEDANG DIPROSES", "color" => "#004085"],
    "Disetujui" => ["text" => "DISETUJUI", "color" => "#0c5460"],
    "Ditolak"  => ["text" => "DITOLAK", "color" => "#721c24"],
    "Selesai"  => ["text" => "SELESAI - DATA TELAH DIHAPUS", "color" => "#155724"]
];

$status_info = $status_config[$data['status']] ?? ["text" => "-", "color" => "#000"];
$stempel_status = $status_info['text'];
$status_color = $status_info['color'];

// ================= DATA PERUSAHAAN =================
$q_perusahaan = $conn->query("SELECT * FROM perusahaan LIMIT 1");
$perusahaan = $q_perusahaan->fetch_assoc();

// ================= FORMAT TANGGAL =================
$tanggal = date('d F Y', strtotime($data['tanggal'] ?? 'now'));

// ================= VALIDATOR INFO =================
$validator_nama = $data['updated_by'] ?: "Belum diverifikasi";
$validator_nik = "-";
$validator_jabatan = "Petugas IT";

if ($validator_user_id) {
    $qValInfo = $conn->query("SELECT nik, jabatan FROM users WHERE id = '$validator_user_id' LIMIT 1");
    if ($qValInfo && $qValInfo->num_rows > 0) {
        $valInfo = $qValInfo->fetch_assoc();
        $validator_nik = $valInfo['nik'];
        $validator_jabatan = $valInfo['jabatan'] ?? 'Petugas IT';
    }
}

// ================= HTML =================
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
    line-height: 1.4;
}

.page { 
    width: 100%; 
    padding: 0px; 
}

.card { 
    border: 2px solid #2c3e50; 
    padding: 15px; 
    border-radius: 5px;
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
    font-size: 8pt;
    color: #555;
    line-height: 1.4;
}

/* TITLE */
.title { 
    text-align: center; 
    margin: 10px 0 15px 0; 
    font-size: 12pt; 
    font-weight: bold; 
    color: #1f4fd8;
    text-transform: uppercase;
    line-height: 1.2;
}

/* STATUS BOX */
.status-box {
    text-align: center;
    padding: 10px;
    margin: 10px 0;
    border: 2px solid '.$status_color.';
    background-color: rgba('.hexToRgb($status_color).', 0.1);
    border-radius: 5px;
}

.status-text {
    font-weight: bold;
    font-size: 11pt;
    color: '.$status_color.';
}

/* INFO TABLE */
.info-table {
    width: 100%;
    border-collapse: collapse;
    margin: 10px 0;
}

.info-table td {
    padding: 5px;
    border-bottom: 1px solid #ddd;
    font-size: 9pt;
}

.info-table .label {
    width: 180px;
    font-weight: bold;
    vertical-align: top;
}

/* SECTION TITLE */
.section-title {
    font-weight: bold;
    margin-top: 12px;
    margin-bottom: 5px;
    font-size: 10pt;
    color: #2c3e50;
    border-bottom: 1px solid #ccc;
    padding-bottom: 3px;
}

/* CONTENT BOX */
.content-box {
    border: 1px solid #999; 
    padding: 8px; 
    min-height: 40px; 
    line-height: 1.4; 
    background-color: #f9f9f9;
    text-align: justify;
    font-size: 9pt;
    border-radius: 3px;
    margin-bottom: 8px;
}

/* CATATAN ADMIN BOX */
.admin-note-box {
    margin-top: 10px;
    padding: 8px;
    background-color: #e7f3ff;
    border: 1px solid #2196F3;
    border-radius: 3px;
}

.admin-note-box .title {
    font-weight: bold;
    color: #004085;
    margin-bottom: 5px;
    font-size: 9pt;
}

.admin-note-box .content {
    color: #004085;
    font-size: 9pt;
    line-height: 1.4;
}

/* REJECTION BOX */
.rejection-box {
    margin-top: 10px;
    padding: 8px;
    background-color: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 3px;
}

.rejection-box .title {
    font-weight: bold;
    color: #856404;
    margin-bottom: 5px;
    font-size: 9pt;
}

.rejection-box .content {
    color: #856404;
    font-size: 9pt;
    line-height: 1.4;
}

/* TTE TABLE */
.tte-table { 
    width: 100%; 
    margin-top: 15px; 
    border-collapse: collapse; 
    font-size: 9pt; 
}

.tte-table td { 
    width: 50%; 
    text-align: center; 
    vertical-align: top; 
    padding: 10px; 
    border-top: 1px solid #999; 
}

.tte-table .role-label {
    font-weight: bold;
    margin-bottom: 5px;
    font-size: 9pt;
}

.qr { 
    width: 60px; 
    height: 60px;
    margin: 5px 0; 
}

.tte-name { 
    font-weight: bold; 
    text-decoration: underline; 
    margin-top: 3px;
    font-size: 9pt;
    line-height: 1.2;
}

.tte-info {
    font-size: 8pt;
    color: #555;
    margin-top: 2px;
    line-height: 1.2;
}

.not-signed {
    font-style: italic;
    color: #999;
    padding: 15px 0;
    font-size: 9pt;
}

/* FOOTER */
.footer { 
    margin-top: 15px; 
    font-size: 7pt; 
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
    font-size: 6.5pt;
    color: #888;
    font-style: italic;
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

<div class="title">Form Permohonan Penghapusan Data SIMRS</div>

<!-- STATUS BOX -->
<div class="status-box">
    <div class="status-text">'.$stempel_status.'</div>
</div>

<!-- INFO TABLE -->
<table class="info-table">
    <tr>
        <td class="label">Nomor Surat</td>
        <td>: <strong>'.$data['nomor_surat'].'</strong></td>
    </tr>
    <tr>
        <td class="label">Tanggal Permohonan</td>
        <td>: '.$tanggal.'</td>
    </tr>
    <tr>
        <td class="label">NIK Pemohon</td>
        <td>: '.$data['nik'].'</td>
    </tr>
    <tr>
        <td class="label">Nama Pemohon</td>
        <td>: '.htmlspecialchars($data['nama']).'</td>
    </tr>
    <tr>
        <td class="label">Jabatan</td>
        <td>: '.htmlspecialchars($data['jabatan']).'</td>
    </tr>
    <tr>
        <td class="label">Unit Kerja</td>
        <td>: '.htmlspecialchars($data['unit_kerja']).'</td>
    </tr>
</table>

<!-- DATA YANG AKAN DIHAPUS -->
<div class="section-title">Data yang Akan Dihapus:</div>
<div class="content-box">'.htmlspecialchars($data['data_terkait'] ?? '-').'</div>

<!-- ALASAN -->
<div class="section-title">Alasan Penghapusan:</div>
<div class="content-box">'.htmlspecialchars($data['alasan'] ?? '-').'</div>

<!-- KRONOLOGI -->
<div class="section-title">Kronologi Lengkap:</div>
<div class="content-box">'.nl2br(htmlspecialchars($data['kronologi'])).'</div>';

// CATATAN ADMIN (jika ada)
if (!empty($data['catatan_admin'])) {
    $html .= '
<div class="admin-note-box">
    <div class="title">📝 Catatan Admin:</div>
    <div class="content">'.nl2br(htmlspecialchars($data['catatan_admin'])).'</div>
</div>';
}

// ALASAN PENOLAKAN (jika ditolak) - backward compatibility
if ($data['status'] == 'Ditolak' && !empty($data['alasan_tolak'])) {
    $html .= '
<div class="rejection-box">
    <div class="title">❌ Alasan Penolakan:</div>
    <div class="content">'.nl2br(htmlspecialchars($data['alasan_tolak'])).'</div>
</div>';
}

// WAKTU UPDATE (jika ada)
if ($data['updated_status_at']) {
    $html .= '
<div style="margin-top: 10px; font-size: 8pt; color: #666; text-align: right;">
    <em>Diperbarui pada: '.date('d F Y, H:i', strtotime($data['updated_status_at'])).' WIB oleh '.htmlspecialchars($validator_nama).'</em>
</div>';
}

// TANDA TANGAN TTE
$html .= '
<table class="tte-table">
<tr>
  <td><div class="role-label">Pemohon</div></td>
  <td><div class="role-label">Petugas IT - SIMRS</div></td>
</tr>
<tr>

<!-- PEMOHON -->
<td>';
if ($tte_pemohon) {
    $html .= '
    <img src="'.$qr_pemohon.'" class="qr"><br>
    <div class="tte-name">'.htmlspecialchars($data['nama']).'</div>
    <div class="tte-info">NIK: '.$data['nik'].'</div>
    <div class="tte-info">'.htmlspecialchars($data['jabatan']).'</div>
    <div class="tte-info">'.$perusahaan['kota'].', '.date('d F Y H:i', strtotime($tte_pemohon['created_at'])).'</div>';
} else {
    $html .= '<div class="not-signed">Belum ditandatangani</div>';
}
$html .= '</td>

<!-- VALIDATOR/PETUGAS IT -->
<td>';
if ($tte_validator) {
    $html .= '
    <img src="'.$qr_validator.'" class="qr"><br>
    <div class="tte-name">'.htmlspecialchars($validator_nama).'</div>
    <div class="tte-info">NIK: '.$validator_nik.'</div>
    <div class="tte-info">'.htmlspecialchars($validator_jabatan).'</div>
    <div class="tte-info">'.$perusahaan['kota'].', '.date('d F Y H:i', strtotime($tte_validator['created_at'])).'</div>';
} else {
    $html .= '<div class="not-signed">Belum diverifikasi</div>';
}
$html .= '</td>
</tr>
</table>

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

// Helper function untuk konversi hex ke RGB
function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "$r, $g, $b";
}

// ===================================================
// GENERATE PDF (WAJIB remote enabled untuk QR)
// ===================================================
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$pdf = new Dompdf($options);
$pdf->loadHtml($html);
$pdf->setPaper('A4', 'portrait'); // Portrait untuk dokumen formal
$pdf->render();

// ===================================================
// GET PDF OUTPUT & EMBED TOKENS
// ===================================================
$pdf_output = $pdf->output();

// Embed ALL tokens di PDF stream (before %%EOF)
$tokens_to_embed = [];
if ($tte_pemohon) $tokens_to_embed[] = $tte_pemohon['token'];
if ($tte_validator) $tokens_to_embed[] = $tte_validator['token'];

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

$filename = 'hapus_data_' . str_replace('/', '_', $data['nomor_surat']) . '_' . time() . '.pdf';
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
    
    // Log validator TTE
    if ($tte_validator) {
        saveDocumentSigningLog($conn, $tte_validator['token'], $validator_user_id, $filename, $file_hash);
    }
}

// ===================================================
// STREAM PDF TO BROWSER
// ===================================================
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Permohonan_Hapus_Data_'.str_replace('/', '_', $data['nomor_surat']).'.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($pdf_output));

// Output PDF yang SAMA dengan yang disimpan
echo $pdf_output;
exit;
?>