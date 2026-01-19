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

// === Fungsi hitung lama izin (durasi) ===
function hitungLama($tanggal, $jam_keluar, $jam_kembali_real) {
    if (empty($jam_keluar) || empty($jam_kembali_real)) {
        return "-";
    }

    // waktu mulai = tanggal + jam keluar
    $mulai = strtotime($tanggal . ' ' . $jam_keluar);

    // jam kembali bisa berupa jam saja atau datetime
    if (preg_match('/\d{4}-\d{2}-\d{2}/', $jam_kembali_real)) {
        $selesai = strtotime($jam_kembali_real);
    } else {
        $selesai = strtotime($tanggal . ' ' . $jam_kembali_real);
    }

    if ($selesai <= $mulai) return "-";

    $selisih = $selesai - $mulai;
    $jam = floor($selisih / 3600);
    $menit = floor(($selisih % 3600) / 60);

    return sprintf("%02d:%02d", $jam, $menit);
}

// === Ambil user login ===
$nama_user = $_SESSION['nama'] ?? $_SESSION['nama_user'] ?? 'Petugas';
$user_id = $_SESSION['user_id'] ?? 0;

// Ambil TTE user yang membuat laporan
$tte_pembuat = getTteByUser($conn, $user_id);
$qr_pembuat = $tte_pembuat ? qrTte($tte_pembuat['token']) : '';

// === Filter tanggal dari URL ===
$tgl_dari = $_GET['tgl_dari'] ?? '';
$tgl_sampai = $_GET['tgl_sampai'] ?? '';

$where = "WHERE 1=1";
if (!empty($tgl_dari) && !empty($tgl_sampai)) {
    $where .= " AND tanggal BETWEEN '$tgl_dari' AND '$tgl_sampai'";
} elseif (!empty($tgl_dari)) {
    $where .= " AND tanggal >= '$tgl_dari'";
} elseif (!empty($tgl_sampai)) {
    $where .= " AND tanggal <= '$tgl_sampai'";
}

// === Ambil data izin keluar ===
$query = mysqli_query($conn, "
    SELECT * FROM izin_keluar
    $where
    ORDER BY tanggal DESC, created_at DESC
");

$total_data = mysqli_num_rows($query);

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

<div class="title">Laporan Izin Keluar Pegawai</div>
<div class="periode">
    Periode: '.(!empty($tgl_dari) ? tgl_indo($tgl_dari) : 'Semua') .' s/d '.(!empty($tgl_sampai) ? tgl_indo($tgl_sampai) : 'Semua').'
</div>

<!-- SUMMARY -->
<div class="summary-box">
    <strong>Total Data:</strong> '.$total_data.' izin keluar | 
    <strong>Dicetak oleh:</strong> '.htmlspecialchars($nama_user).' | 
    <strong>Tanggal Cetak:</strong> '.tgl_indo(date('Y-m-d')).' '.date('H:i').' WIB
</div>

<table>
<thead>
<tr>
  <th style="width: 3%;">No</th>
  <th style="width: 15%;">Nama</th>
  <th style="width: 12%;">Bagian</th>
  <th style="width: 10%;">Tanggal</th>
  <th style="width: 7%;">Keluar</th>
  <th style="width: 7%;">Kembali</th>
  <th style="width: 20%;">Keperluan</th>
  <th style="width: 8%;">Atasan</th>
  <th style="width: 8%;">SDM</th>
  <th style="width: 6%;">Durasi</th>
</tr>
</thead>
<tbody>';

$no = 1;
if ($total_data > 0) {
    mysqli_data_seek($query, 0); // Reset pointer
    while ($row = mysqli_fetch_assoc($query)) {
        $lama = hitungLama(
            $row['tanggal'],
            $row['jam_keluar'],
            $row['jam_kembali_real']
        );
        
        // Status badge
        $status_atasan = $row['status_atasan'];
        $status_sdm = $row['status_sdm'];
        
        $badge_atasan = $status_atasan == 'acc' ? '✓' : ($status_atasan == 'tolak' ? '✗' : '-');
        $badge_sdm = $status_sdm == 'acc' ? '✓' : ($status_sdm == 'tolak' ? '✗' : '-');

        $html .= "
        <tr>
          <td class='center'>{$no}</td>
          <td class='left'>".htmlspecialchars($row['nama'])."</td>
          <td class='center'>".htmlspecialchars($row['bagian'])."</td>
          <td class='center'>".date('d/m/Y', strtotime($row['tanggal']))."</td>
          <td class='center'>".htmlspecialchars($row['jam_keluar'])."</td>
          <td class='center'>".($row['jam_kembali_real'] ? date('H:i', strtotime($row['jam_kembali_real'])) : '-')."</td>
          <td class='left'>".htmlspecialchars(substr($row['keperluan'], 0, 50)).(strlen($row['keperluan']) > 50 ? '...' : '')."</td>
          <td class='center'>{$badge_atasan}</td>
          <td class='center'>{$badge_sdm}</td>
          <td class='center'>{$lama}</td>
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
        <div class="nik">'.htmlspecialchars($tte_pembuat['jabatan'] ?? '-').'</div>';
} else {
    $html .= '
        <div style="margin: 30px 0;"></div>
        <div class="name">'.htmlspecialchars($nama_user).'</div>';
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
header('Content-Disposition: inline; filename="Laporan_Izin_Keluar_'.date('Ymd').'.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($pdf_output));

// Output PDF
echo $pdf_output;
exit;
?>