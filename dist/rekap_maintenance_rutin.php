<?php
// ===================================================
// ERROR HANDLING AMAN PHP 8
// ===================================================
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 1);

session_start();
include 'koneksi.php';
require 'dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

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
    return "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($url);
}

// === Fungsi format tanggal Indonesia ===
function tgl_indo($tanggal) {
    if (!$tanggal || $tanggal == "0000-00-00") return "-";
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $split = explode('-', date('Y-m-d', strtotime($tanggal)));
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

// === Ambil user login ===
$nama_user = $_SESSION['nama'] ?? $_SESSION['nama_user'] ?? 'Teknisi';
$user_id = $_SESSION['user_id'] ?? 0;

// Ambil TTE user yang membuat laporan
$tte_pembuat = getTteByUser($conn, $user_id);
$qr_pembuat = $tte_pembuat ? qrTte($tte_pembuat['token']) : '';

// === Filter tanggal dari URL ===
$dari = isset($_GET['dari']) ? $_GET['dari'] : '';
$sampai = isset($_GET['sampai']) ? $_GET['sampai'] : '';

if (!$dari || !$sampai) {
    die('Filter tanggal tidak lengkap.');
}

// === Ambil data maintenance ===
$query = mysqli_query($conn, "
    SELECT mr.*, db.nama_barang, db.lokasi, db.kategori
    FROM maintanance_rutin mr
    JOIN data_barang_it db ON mr.barang_id = db.id
    WHERE DATE(mr.waktu_input) BETWEEN '$dari' AND '$sampai'
    ORDER BY mr.waktu_input DESC
");

$total_data = mysqli_num_rows($query);

// Hitung statistik
$stat_aman = 0;
$stat_persiapan = 0;
$stat_wajib = 0;

mysqli_data_seek($query, 0);
while ($row = mysqli_fetch_assoc($query)) {
    $waktu_input = strtotime($row['waktu_input']);
    $selisih_bulan = floor((time() - $waktu_input) / (30 * 24 * 60 * 60));
    
    if ($selisih_bulan < 1) {
        $stat_aman++;
    } elseif ($selisih_bulan < 2) {
        $stat_persiapan++;
    } else {
        $stat_wajib++;
    }
}

// === Ambil data perusahaan ===
$q_perusahaan = mysqli_query($conn, "SELECT * FROM perusahaan LIMIT 1");
$perusahaan = mysqli_fetch_assoc($q_perusahaan);

// === Template HTML laporan ===
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
    margin: 10px 0 5px 0; 
    font-size: 12pt; 
    font-weight: bold; 
    color: #1f4fd8;
    text-transform: uppercase;
}

.periode {
    text-align: center;
    font-size: 9pt;
    color: #666;
    margin-bottom: 10px;
}

/* TABLE */
table { 
    border-collapse: collapse; 
    width: 100%; 
    margin-top: 5px;
    font-size: 8pt;
}

table, th, td { 
    border: 1px solid #000; 
}

th { 
    background: #f2f2f2; 
    text-align: center;
    padding: 6px 4px;
    font-weight: bold;
    font-size: 8pt;
}

td { 
    padding: 4px;
    vertical-align: middle;
}

td.left { 
    text-align: left; 
}

td.center { 
    text-align: center; 
}

/* STATUS COLORS */
.status-aman {
    color: green;
    font-weight: bold;
}

.status-persiapan {
    color: orange;
    font-weight: bold;
}

.status-wajib {
    color: red;
    font-weight: bold;
}

/* SIGNATURE SECTION */
.signature-section {
    margin-top: 15px;
    width: 100%;
}

.signature-box {
    float: right;
    width: 200px;
    text-align: center;
    font-size: 9pt;
}

.signature-box .location {
    margin-bottom: 5px;
}

.signature-box .label {
    margin-bottom: 3px;
}

.qr {
    width: 50px;
    height: 50px;
    margin: 5px auto;
}

.signature-box .name {
    font-weight: bold;
    text-decoration: underline;
    margin-top: 3px;
    font-size: 9pt;
}

.signature-box .nik {
    font-size: 8pt;
    color: #555;
    margin-top: 2px;
}

/* FOOTER */
.footer { 
    clear: both;
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

/* SUMMARY BOX */
.summary-box {
    margin-top: 8px;
    padding: 8px;
    background-color: #f0f8ff;
    border: 1px solid #2196F3;
    font-size: 9pt;
    line-height: 1.6;
}

.summary-box strong {
    color: #004085;
}

.stat-item {
    display: inline-block;
    margin-right: 15px;
}
</style>
</head>

<body>

<!-- KOP SURAT -->
<div class="header">
  <h2>'.strtoupper($perusahaan['nama_perusahaan']).'</h2>
  <div class="subkop">
    '.$perusahaan['alamat'].' - '.$perusahaan['kota'].', '.$perusahaan['provinsi'].'<br>
    Telp: '.$perusahaan['kontak'].' | Email: '.$perusahaan['email'].'
  </div>
</div>

<div class="title">Laporan Maintenance Rutin Perangkat IT</div>
<div class="periode">
    Periode: '.tgl_indo($dari).' s/d '.tgl_indo($sampai).'
</div>

<!-- SUMMARY -->
<div class="summary-box">
    <strong>Total Data:</strong> '.$total_data.' maintenance | 
    <strong>Dicetak oleh:</strong> '.htmlspecialchars($nama_user).' | 
    <strong>Tanggal Cetak:</strong> '.tgl_indo(date('Y-m-d')).' '.date('H:i').' WIB<br>
    <div style="margin-top: 5px;">
        <span class="stat-item"><strong>Status:</strong></span>
        <span class="stat-item status-aman">✓ Aman: '.$stat_aman.'</span>
        <span class="stat-item status-persiapan">⚠ Persiapan: '.$stat_persiapan.'</span>
        <span class="stat-item status-wajib">✗ Wajib: '.$stat_wajib.'</span>
    </div>
</div>

<table>
<thead>
<tr>
  <th style="width: 3%;">No</th>
  <th style="width: 15%;">Nama Barang</th>
  <th style="width: 8%;">Kategori</th>
  <th style="width: 12%;">Lokasi</th>
  <th style="width: 18%;">Kondisi Fisik</th>
  <th style="width: 18%;">Fungsi Perangkat</th>
  <th style="width: 10%;">Catatan</th>
  <th style="width: 10%;">Teknisi</th>
  <th style="width: 8%;">Waktu</th>
  <th style="width: 8%;">Status</th>
</tr>
</thead>
<tbody>';

$no = 1;
if ($total_data > 0) {
    mysqli_data_seek($query, 0); // Reset pointer
    while ($row = mysqli_fetch_assoc($query)) {
        $waktu_input = strtotime($row['waktu_input']);
        $selisih_bulan = floor((time() - $waktu_input) / (30 * 24 * 60 * 60));

        if ($selisih_bulan < 1) {
            $status_text = 'Aman';
            $status_class = 'status-aman';
            $status_icon = '✓';
        } elseif ($selisih_bulan < 2) {
            $status_text = 'Persiapan';
            $status_class = 'status-persiapan';
            $status_icon = '⚠';
        } else {
            $status_text = 'Wajib';
            $status_class = 'status-wajib';
            $status_icon = '✗';
        }

        // Batasi teks untuk kondisi fisik dan fungsi
        $kondisi_fisik = htmlspecialchars(substr($row['kondisi_fisik'], 0, 80));
        if (strlen($row['kondisi_fisik']) > 80) $kondisi_fisik .= '...';
        
        $fungsi_perangkat = htmlspecialchars(substr($row['fungsi_perangkat'], 0, 80));
        if (strlen($row['fungsi_perangkat']) > 80) $fungsi_perangkat .= '...';
        
        $catatan = !empty($row['catatan']) ? htmlspecialchars(substr($row['catatan'], 0, 40)) : '-';
        if (!empty($row['catatan']) && strlen($row['catatan']) > 40) $catatan .= '...';

        $html .= "
        <tr>
          <td class='center'>{$no}</td>
          <td class='left'>".htmlspecialchars($row['nama_barang'])."</td>
          <td class='center'>".htmlspecialchars($row['kategori'])."</td>
          <td class='center'>".htmlspecialchars($row['lokasi'])."</td>
          <td class='left' style='font-size: 7pt;'>{$kondisi_fisik}</td>
          <td class='left' style='font-size: 7pt;'>{$fungsi_perangkat}</td>
          <td class='left' style='font-size: 7pt;'>{$catatan}</td>
          <td class='center'>".htmlspecialchars($row['nama_teknisi'])."</td>
          <td class='center'>".date('d/m/Y H:i', strtotime($row['waktu_input']))."</td>
          <td class='center {$status_class}'>{$status_icon} {$status_text}</td>
        </tr>";
        $no++;
    }
} else {
    $html .= "<tr><td colspan='10' class='center' style='padding: 20px;'>Tidak ada data untuk periode yang dipilih</td></tr>";
}

$html .= '
</tbody>
</table>

<!-- SIGNATURE SECTION -->
<div class="signature-section">
    <div class="signature-box">
        <div class="location">'.$perusahaan['kota'].', '.tgl_indo(date('Y-m-d')).'</div>
        <div class="label">Dibuat oleh:</div>';

if ($tte_pembuat) {
    $html .= '
        <img src="'.$qr_pembuat.'" class="qr">
        <div class="name">'.htmlspecialchars($nama_user).'</div>
        <div class="nik">'.htmlspecialchars($tte_pembuat['jabatan'] ?? 'Teknisi IT').'</div>';
} else {
    $html .= '
        <div style="margin: 30px 0;"></div>
        <div class="name">'.htmlspecialchars($nama_user).'</div>
        <div class="nik">Teknisi IT</div>';
}

$html .= '
    </div>
</div>

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
$pdf->setPaper('A4', 'landscape'); // Landscape untuk tabel lebar
$pdf->render();

// ===================================================
// GET PDF OUTPUT & EMBED TOKEN
// ===================================================
$pdf_output = $pdf->output();

// Embed token di PDF stream (before %%EOF)
if ($tte_pembuat) {
    $token_text = "\nTTE-TOKEN:" . $tte_pembuat['token'] . "\n";
    $pdf_output = str_replace('%%EOF', $token_text . '%%EOF', $pdf_output);
}

// ===================================================
// STREAM PDF TO BROWSER
// ===================================================
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Laporan_Maintenance_IT_'.date('Ymd').'.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($pdf_output));

// Output PDF
echo $pdf_output;
exit;
?>