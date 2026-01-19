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
$nama_user = $_SESSION['nama'] ?? $_SESSION['nama_user'] ?? 'Petugas';
$user_id = $_SESSION['user_id'] ?? 0;

// Ambil TTE user yang membuat laporan
$tte_pembuat = getTteByUser($conn, $user_id);
$qr_pembuat = $tte_pembuat ? qrTte($tte_pembuat['token']) : '';

// === Filter kategori dan kondisi dari URL ===
$kategori = $_GET['kategori'] ?? '';
$kondisi = $_GET['kondisi'] ?? '';
$lokasi = $_GET['lokasi'] ?? '';

$where = "WHERE 1=1";
if (!empty($kategori)) {
    $where .= " AND kategori = '".mysqli_real_escape_string($conn, $kategori)."'";
}
if (!empty($kondisi)) {
    $where .= " AND kondisi = '".mysqli_real_escape_string($conn, $kondisi)."'";
}
if (!empty($lokasi)) {
    $where .= " AND lokasi = '".mysqli_real_escape_string($conn, $lokasi)."'";
}

// === Ambil data barang IT ===
$query = mysqli_query($conn, "
    SELECT * FROM data_barang_it
    $where
    ORDER BY waktu_input DESC
");

$total_data = mysqli_num_rows($query);

// === Ambil data perusahaan ===
$q_perusahaan = mysqli_query($conn, "SELECT * FROM perusahaan LIMIT 1");
$perusahaan = mysqli_fetch_assoc($q_perusahaan);

// === Hitung rekap per kategori ===
$rekap = [];
$rekap_query = mysqli_query($conn, "
    SELECT 
        kategori, 
        COUNT(*) as jumlah 
    FROM data_barang_it 
    $where
    GROUP BY kategori
");
while ($r = mysqli_fetch_assoc($rekap_query)) {
    $rekap[$r['kategori']] = $r['jumlah'];
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

/* KONDISI BADGE */
.badge {
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 7pt;
    font-weight: bold;
}

.badge-baik {
    background-color: #d4edda;
    color: #155724;
}

.badge-rusak-ringan {
    background-color: #fff3cd;
    color: #856404;
}

.badge-rusak-berat {
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

<div class="title">Laporan Data Inventaris Barang IT</div>
<div class="periode">
    Per Tanggal: '.tgl_indo(date('Y-m-d')).'
    '.(!empty($kategori) ? '| Kategori: '.$kategori : '').'
    '.(!empty($kondisi) ? '| Kondisi: '.$kondisi : '').'
    '.(!empty($lokasi) ? '| Lokasi: '.$lokasi : '').'
</div>

<!-- SUMMARY -->
<div class="summary-box">
    <strong>Total Barang:</strong> '.$total_data.' item | 
    <strong>Dicetak oleh:</strong> '.htmlspecialchars($nama_user).' | 
    <strong>Tanggal Cetak:</strong> '.tgl_indo(date('Y-m-d')).' '.date('H:i').' WIB
</div>

<!-- REKAP PER KATEGORI -->
<div class="rekap-box">
    <strong>Rekap per Kategori:</strong> ';

foreach ($rekap as $kat => $jml) {
    $html .= '<span class="rekap-item">'.$kat.': <strong>'.$jml.'</strong></span>';
}

$html .= '
</div>

<table>
<thead>
<tr>
  <th style="width: 3%;">No</th>
  <th style="width: 12%;">No. Barang</th>
  <th style="width: 15%;">Nama Barang</th>
  <th style="width: 10%;">Kategori</th>
  <th style="width: 10%;">Merk</th>
  <th style="width: 15%;">Spesifikasi</th>
  <th style="width: 10%;">IP Address</th>
  <th style="width: 12%;">Lokasi</th>
  <th style="width: 8%;">Kondisi</th>
  <th style="width: 10%;">Tanggal Input</th>
</tr>
</thead>
<tbody>';

$no = 1;
if ($total_data > 0) {
    mysqli_data_seek($query, 0); // Reset pointer
    while ($row = mysqli_fetch_assoc($query)) {
        // Badge kondisi
        $kondisi_class = '';
        if ($row['kondisi'] == 'Baik') {
            $kondisi_class = 'badge-baik';
        } elseif ($row['kondisi'] == 'Rusak Ringan') {
            $kondisi_class = 'badge-rusak-ringan';
        } elseif ($row['kondisi'] == 'Rusak Berat') {
            $kondisi_class = 'badge-rusak-berat';
        }

        $html .= "
        <tr>
          <td class='center'>{$no}</td>
          <td class='center'><strong>".htmlspecialchars($row['no_barang'])."</strong></td>
          <td class='left'>".htmlspecialchars($row['nama_barang'])."</td>
          <td class='center'>".htmlspecialchars($row['kategori'])."</td>
          <td class='center'>".htmlspecialchars($row['merk'])."</td>
          <td class='left'>".htmlspecialchars(substr($row['spesifikasi'], 0, 40)).(strlen($row['spesifikasi']) > 40 ? '...' : '')."</td>
          <td class='center'>".htmlspecialchars($row['ip_address'])."</td>
          <td class='center'>".htmlspecialchars($row['lokasi'])."</td>
          <td class='center'><span class='badge {$kondisi_class}'>".htmlspecialchars($row['kondisi'])."</span></td>
          <td class='center'>".date('d/m/Y H:i', strtotime($row['waktu_input']))."</td>
        </tr>";
        $no++;
    }
} else {
    $html .= "<tr><td colspan='10' class='center' style='padding: 20px;'>Tidak ada data barang IT untuk filter yang dipilih</td></tr>";
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
header('Content-Disposition: inline; filename="Laporan_Barang_IT_'.date('Ymd_His').'.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($pdf_output));

// Output PDF
echo $pdf_output;
exit;
?>