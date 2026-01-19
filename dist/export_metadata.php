<?php
/**
 * File: export_metadata.php
 * Export Metadata Signature ke PDF
 */

require 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('Token tidak ditemukan');
}

// Query TTE
$qTTE = mysqli_query($conn, "
    SELECT t.*, u.nama, u.nik, u.jabatan, u.unit_kerja, u.email,
           p.nama_perusahaan, p.alamat, p.kota, p.provinsi, p.kontak, p.email as email_perusahaan
    FROM tte_user t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN perusahaan p ON 1=1
    WHERE t.token = '".mysqli_real_escape_string($conn, $token)."'
    LIMIT 1
");

$tte = mysqli_fetch_assoc($qTTE);

if (!$tte) {
    die('TTE tidak ditemukan');
}

// Query logs
$qLogs = mysqli_query($conn, "
    SELECT * FROM tte_document_log
    WHERE tte_token = '".mysqli_real_escape_string($conn, $token)."'
    ORDER BY signed_at DESC
    LIMIT 10
");

$logs = [];
while ($log = mysqli_fetch_assoc($qLogs)) {
    $logs[] = $log;
}

$total_docs = count($logs);

function formatTanggal($datetime) {
    if (empty($datetime) || $datetime == '-') return '-';
    return date('d F Y, H:i', strtotime($datetime)) . ' WIB';
}

// Build HTML
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; font-size: 11px; }
.header { text-align: center; background: #667eea; color: white; padding: 20px; margin-bottom: 20px; }
.header h2 { margin: 0; font-size: 18px; }
.section { margin-bottom: 20px; }
.section-title { background: #f3f4f6; padding: 10px; font-weight: bold; margin-bottom: 10px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
table td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
table td:first-child { width: 200px; font-weight: bold; color: #6b7280; }
.hash { font-family: Courier; font-size: 9px; background: #f9fafb; padding: 5px; word-break: break-all; }
.doc-list { font-size: 10px; }
.doc-item { background: #f9fafb; padding: 8px; margin-bottom: 5px; border-left: 3px solid #667eea; }
.footer { text-align: center; font-size: 9px; color: #6b7280; margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 15px; }
</style>
</head>
<body>

<div class="header">
    <h2>METADATA SIGNATURE</h2>
    <div>Tanda Tangan Elektronik Non-Sertifikasi</div>
    <div style="margin-top: 10px; font-size: 10px;">Token: '.htmlspecialchars($token).'</div>
</div>

<div class="section">
    <div class="section-title">INFORMASI PEMILIK TANDA TANGAN</div>
    <table>
        <tr><td>Nama Lengkap</td><td>'.htmlspecialchars($tte['nama']).'</td></tr>
        <tr><td>NIK/NIP</td><td>'.htmlspecialchars($tte['nik']).'</td></tr>
        '.(!empty($tte['no_ktp']) ? '<tr><td>Nomor KTP</td><td>'.htmlspecialchars($tte['no_ktp']).'</td></tr>' : '').'
        <tr><td>Jabatan</td><td>'.htmlspecialchars($tte['jabatan']).'</td></tr>
        <tr><td>Unit/Bagian</td><td>'.htmlspecialchars($tte['unit_kerja']).'</td></tr>
        '.(!empty($tte['email']) ? '<tr><td>Email</td><td>'.htmlspecialchars($tte['email']).'</td></tr>' : '').'
    </table>
</div>

<div class="section">
    <div class="section-title">INFORMASI PERUSAHAAN/INSTANSI</div>
    <table>
        <tr><td>Nama Perusahaan</td><td>'.htmlspecialchars($tte['nama_perusahaan']).'</td></tr>
        <tr><td>Alamat</td><td>'.htmlspecialchars($tte['alamat']).'</td></tr>
        <tr><td>Kota/Provinsi</td><td>'.htmlspecialchars($tte['kota']).', '.htmlspecialchars($tte['provinsi']).'</td></tr>
        <tr><td>Kontak</td><td>'.htmlspecialchars($tte['kontak']).'</td></tr>
        <tr><td>Email</td><td>'.htmlspecialchars($tte['email_perusahaan']).'</td></tr>
    </table>
</div>

<div class="section">
    <div class="section-title">INFORMASI TEKNIS TTE</div>
    <table>
        <tr><td>Token ID (Hash)</td><td><div class="hash">'.htmlspecialchars($tte['token']).'</div></td></tr>
        <tr><td>File Hash (SHA-256)</td><td><div class="hash">'.htmlspecialchars($tte['file_hash']).'</div></td></tr>
        <tr><td>Dibuat Pada</td><td>'.formatTanggal($tte['created_at']).'</td></tr>
        <tr><td>Status</td><td>'.strtoupper($tte['status']).'</td></tr>
        <tr><td>IP Address</td><td>'.htmlspecialchars($tte['ip_address'] ?? '-').'</td></tr>
    </table>
</div>

<div class="section">
    <div class="section-title">STATISTIK PENGGUNAAN</div>
    <table>
        <tr><td>Total Dokumen</td><td>'.$total_docs.' Dokumen</td></tr>
    </table>
</div>';

if (!empty($logs)) {
    $html .= '
<div class="section">
    <div class="section-title">RIWAYAT DOKUMEN</div>
    <div class="doc-list">';
    
    foreach ($logs as $idx => $log) {
        $html .= '
        <div class="doc-item">
            <strong>#'.($idx + 1).' - '.htmlspecialchars($log['document_name']).'</strong><br>
            Ditandatangani: '.formatTanggal($log['signed_at']).'<br>
            Hash: '.substr($log['document_hash'], 0, 40).'...
        </div>';
    }
    
    $html .= '
    </div>
</div>';
}

$html .= '
<div class="footer">
    Metadata Signature ini di-generate otomatis oleh sistem<br>
    <strong>FixPoint – Smart Office Management System</strong><br>
    Dicetak pada: '.date('d F Y, H:i').' WIB
</div>

</body>
</html>';

// Generate PDF
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);

$pdf = new Dompdf($options);
$pdf->loadHtml($html);
$pdf->setPaper('A4', 'portrait');
$pdf->render();

// Output
$filename = 'Metadata_Signature_' . substr($token, 0, 8) . '_' . date('Ymd') . '.pdf';
$pdf->stream($filename, ['Attachment' => true]);
?>