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

function formatTanggal($tanggal) {
    return $tanggal ? date('d-m-Y H:i', strtotime($tanggal)) : '-';
}

function hitungDurasi($mulai, $selesai) {
    if (!$mulai || !$selesai) return '-';
    $start = new DateTime($mulai);
    $end = new DateTime($selesai);
    $interval = $start->diff($end);
    $jam = $interval->h + ($interval->days * 24);
    $menit = $interval->i;
    return "{$jam}j {$menit}m";
}

// === Ambil user login ===
$nama_user = $_SESSION['nama'] ?? $_SESSION['nama_user'] ?? 'Petugas';
$user_id = $_SESSION['user_id'] ?? 0;

// Ambil TTE user yang membuat laporan
$tte_pembuat = getTteByUser($conn, $user_id);
$qr_pembuat = $tte_pembuat ? qrTte($tte_pembuat['token']) : '';

// === Filter dari URL ===
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$dari_tanggal = isset($_GET['dari_tanggal']) ? $_GET['dari_tanggal'] : '';
$sampai_tanggal = isset($_GET['sampai_tanggal']) ? $_GET['sampai_tanggal'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

if ($dari_tanggal) $dari_tanggal = date('Y-m-d', strtotime($dari_tanggal));
if ($sampai_tanggal) $sampai_tanggal = date('Y-m-d', strtotime($sampai_tanggal));

$where = "WHERE 1=1";
if (!empty($keyword)) {
    $kw = mysqli_real_escape_string($conn, $keyword);
    $where .= " AND (nik LIKE '%$kw%' OR nama LIKE '%$kw%' OR nomor_tiket LIKE '%$kw%')";
}
if (!empty($dari_tanggal) && !empty($sampai_tanggal)) {
    $where .= " AND DATE(tanggal_input) BETWEEN '$dari_tanggal' AND '$sampai_tanggal'";
}
if (!empty($filter_status)) {
    $status_escaped = mysqli_real_escape_string($conn, $filter_status);
    $where .= " AND status = '$status_escaped'";
}

// === Ambil data tiket IT Software ===
$query = mysqli_query($conn, "
    SELECT * FROM tiket_it_software
    $where
    ORDER BY tanggal_input DESC
");

$total_data = mysqli_num_rows($query);

// === Ambil data perusahaan ===
$q_perusahaan = mysqli_query($conn, "SELECT * FROM perusahaan LIMIT 1");
$perusahaan = mysqli_fetch_assoc($q_perusahaan);

// === Hitung rekap per status ===
$rekap = [];
$rekap_query = mysqli_query($conn, "
    SELECT 
        status, 
        COUNT(*) as jumlah 
    FROM tiket_it_software 
    $where
    GROUP BY status
");
while ($r = mysqli_fetch_assoc($rekap_query)) {
    $rekap[$r['status']] = $r['jumlah'];
}

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
    font-size: 7pt;
}

table, th, td { 
    border: 1px solid #000; 
}

th { 
    background: #f2f2f2; 
    text-align: center;
    padding: 6px 4px;
    font-weight: bold;
    font-size: 7pt;
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

/* STATUS BADGE */
.badge {
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 6pt;
    font-weight: bold;
}

.badge-menunggu {
    background-color: #fff3cd;
    color: #856404;
}

.badge-diproses {
    background-color: #d1ecf1;
    color: #0c5460;
}

.badge-selesai {
    background-color: #d4edda;
    color: #155724;
}

.badge-tidak-bisa {
    background-color: #f8d7da;
    color: #721c24;
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
    padding: 6px;
    background-color: #f0f8ff;
    border: 1px solid #2196F3;
    font-size: 9pt;
}

.summary-box strong {
    color: #004085;
}

/* REKAP BOX */
.rekap-box {
    margin-top: 8px;
    padding: 6px;
    background-color: #fff3cd;
    border: 1px solid #ffc107;
    font-size: 8pt;
    display: inline-block;
    width: 100%;
}

.rekap-item {
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

<div class="title">Laporan Handling Time Tiket IT Software</div>
<div class="periode">';

if (!empty($dari_tanggal) && !empty($sampai_tanggal)) {
    $html .= 'Periode: '.tgl_indo($dari_tanggal).' s/d '.tgl_indo($sampai_tanggal);
} else {
    $html .= 'Per Tanggal: '.tgl_indo(date('Y-m-d'));
}

if (!empty($filter_status)) {
    $html .= ' | Status: '.$filter_status;
}
if (!empty($keyword)) {
    $html .= ' | Keyword: '.htmlspecialchars($keyword);
}

$html .= '
</div>

<!-- SUMMARY -->
<div class="summary-box">
    <strong>Total Tiket:</strong> '.$total_data.' tiket | 
    <strong>Dicetak oleh:</strong> '.htmlspecialchars($nama_user).' | 
    <strong>Tanggal Cetak:</strong> '.tgl_indo(date('Y-m-d')).' '.date('H:i').' WIB
</div>

<!-- REKAP PER STATUS -->
<div class="rekap-box">
    <strong>Rekap per Status:</strong> ';

foreach ($rekap as $sts => $jml) {
    $html .= '<span class="rekap-item">'.$sts.': <strong>'.$jml.'</strong></span>';
}

$html .= '
</div>

<table>
<thead>
<tr>
  <th style="width: 3%;">No</th>
  <th style="width: 8%;">No Tiket</th>
  <th style="width: 6%;">NIK</th>
  <th style="width: 10%;">Nama</th>
  <th style="width: 8%;">Jabatan</th>
  <th style="width: 10%;">Unit Kerja</th>
  <th style="width: 7%;">Kategori</th>
  <th style="width: 12%;">Kendala</th>
  <th style="width: 7%;">Status</th>
  <th style="width: 8%;">Teknisi</th>
  <th style="width: 8%;">Tgl Input</th>
  <th style="width: 8%;">Diproses</th>
  <th style="width: 8%;">Selesai</th>
  <th style="width: 5%;">Respon</th>
  <th style="width: 5%;">Total</th>
</tr>
</thead>
<tbody>';

$no = 1;
if ($total_data > 0) {
    mysqli_data_seek($query, 0); // Reset pointer
    while ($row = mysqli_fetch_assoc($query)) {
        // Badge status
        $status_class = '';
        $status_lower = strtolower($row['status']);
        if ($status_lower == 'menunggu') {
            $status_class = 'badge-menunggu';
        } elseif ($status_lower == 'diproses') {
            $status_class = 'badge-diproses';
        } elseif ($status_lower == 'selesai') {
            $status_class = 'badge-selesai';
        } elseif (strpos($status_lower, 'tidak') !== false) {
            $status_class = 'badge-tidak-bisa';
        }

        $kendala_short = strlen($row['kendala']) > 40 ? substr($row['kendala'], 0, 40).'...' : $row['kendala'];

        $html .= "
        <tr>
          <td class='center'>{$no}</td>
          <td class='center'><strong>".htmlspecialchars($row['nomor_tiket'])."</strong></td>
          <td class='center'>".htmlspecialchars($row['nik'])."</td>
          <td class='left'>".htmlspecialchars($row['nama'])."</td>
          <td class='left'>".htmlspecialchars($row['jabatan'])."</td>
          <td class='left'>".htmlspecialchars($row['unit_kerja'])."</td>
          <td class='center'>".htmlspecialchars($row['kategori'])."</td>
          <td class='left'>".htmlspecialchars($kendala_short)."</td>
          <td class='center'><span class='badge {$status_class}'>".htmlspecialchars($row['status'])."</span></td>
          <td class='left'>".htmlspecialchars($row['teknisi_nama'])."</td>
          <td class='center'>".formatTanggal($row['tanggal_input'])."</td>
          <td class='center'>".formatTanggal($row['waktu_diproses'])."</td>
          <td class='center'>".formatTanggal($row['waktu_selesai'])."</td>
          <td class='center'>".hitungDurasi($row['tanggal_input'], $row['waktu_diproses'])."</td>
          <td class='center'>".hitungDurasi($row['tanggal_input'], $row['waktu_selesai'])."</td>
        </tr>";
        $no++;
    }
} else {
    $html .= "<tr><td colspan='15' class='center' style='padding: 20px;'>Tidak ada data tiket untuk filter yang dipilih</td></tr>";
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
        <div class="nik">'.htmlspecialchars($tte_pembuat['jabatan'] ?? 'Staff IT').'</div>';
} else {
    $html .= '
        <div style="margin: 30px 0;"></div>
        <div class="name">'.htmlspecialchars($nama_user).'</div>
        <div class="nik">Staff IT</div>';
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
header('Content-Disposition: inline; filename="Laporan_Handling_Time_Software_'.date('Ymd_His').'.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($pdf_output));

// Output PDF
echo $pdf_output;
exit;
?>