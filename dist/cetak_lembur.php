<?php
// ===================================================
// ERROR HANDLING
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
// CEK LOGIN
// ===================================================
$user_id   = $_SESSION['user_id'] ?? 0;
$nama_user = $_SESSION['nama']    ?? $_SESSION['nama_user'] ?? 'Petugas';
if ($user_id == 0) {
    echo "<script>alert('Anda belum login');location.href='login.php';</script>";
    exit;
}

// ===================================================
// FUNCTION TTE — SAMA PERSIS DENGAN laporan_izin_keluar.php
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

// ===================================================
// AMBIL TTE USER YANG MENCETAK
// ===================================================
$tte_pembuat = getTteByUser($conn, $user_id);
$qr_pembuat  = $tte_pembuat ? qrTte($tte_pembuat['token']) : '';

// ===================================================
// HELPER TANGGAL INDONESIA
// ===================================================
function tgl_indo($tanggal) {
    if (!$tanggal || $tanggal == "0000-00-00") return "-";
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $split = explode('-', date('Y-m-d', strtotime($tanggal)));
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

// ===================================================
// FILTER DARI URL
// ===================================================
$filterNama   = $_GET['nama']      ?? '';
$filterNik    = $_GET['nik']       ?? '';
$filterUnit   = $_GET['unit']      ?? '';
$filterStatus = $_GET['status']    ?? '';
$tgl_awal     = $_GET['tgl_awal']  ?? date('Y-m-01');
$tgl_akhir    = $_GET['tgl_akhir'] ?? date('Y-m-d');

// ===================================================
// BUILD QUERY DINAMIS
// ===================================================
$where = "WHERE DATE(lp.lembur_mulai) BETWEEN '$tgl_awal' AND '$tgl_akhir'";
if ($filterNama)   $where .= " AND lp.nama LIKE '%" . mysqli_real_escape_string($conn, $filterNama) . "%'";
if ($filterNik)    $where .= " AND lp.nik  LIKE '%" . mysqli_real_escape_string($conn, $filterNik)  . "%'";
if ($filterUnit)   $where .= " AND lp.unit LIKE '%" . mysqli_real_escape_string($conn, $filterUnit)  . "%'";
if ($filterStatus === 'pending')
    $where .= " AND (lp.status_atasan='pending' OR lp.status_sdm='pending')";
elseif ($filterStatus === 'disetujui')
    $where .= " AND lp.status_atasan='disetujui' AND lp.status_sdm='disetujui'";
elseif ($filterStatus === 'ditolak')
    $where .= " AND (lp.status_atasan='ditolak' OR lp.status_sdm='ditolak')";

// ===================================================
// AMBIL DATA
// ===================================================
$query = mysqli_query($conn, "
    SELECT lp.*,
           ll.aktual_pelaksanaan,
           u_a.nama AS nama_atasan,
           u_s.nama AS nama_sdm
    FROM lembur_pengajuan lp
    LEFT JOIN lembur_laporan ll ON ll.lembur_id = lp.id
    LEFT JOIN users u_a ON u_a.id = lp.acc_oleh_atasan
    LEFT JOIN users u_s ON u_s.id = lp.acc_oleh_sdm
    $where
    ORDER BY lp.lembur_mulai ASC
");

$total_data = mysqli_num_rows($query);

// ===================================================
// DATA PERUSAHAAN
// ===================================================
$q_perusahaan = mysqli_query($conn, "SELECT * FROM perusahaan LIMIT 1");
$perusahaan   = mysqli_fetch_assoc($q_perusahaan);

// ===================================================
// HITUNG SUMMARY & KUMPULKAN ROWS
// ===================================================
$total_jam   = 0;
$jml_setuju  = 0;
$jml_pending = 0;
$jml_tolak   = 0;
$rows        = [];

if ($total_data > 0) {
    mysqli_data_seek($query, 0);
    while ($r = mysqli_fetch_assoc($query)) {
        $rows[]     = $r;
        $total_jam += floatval($r['total_jam']);
        if ($r['status_atasan']=='disetujui' && $r['status_sdm']=='disetujui') $jml_setuju++;
        elseif ($r['status_atasan']=='pending' || $r['status_sdm']=='pending')  $jml_pending++;
        elseif ($r['status_atasan']=='ditolak' || $r['status_sdm']=='ditolak')  $jml_tolak++;
    }
}

// Label filter untuk header PDF
$label_periode = tgl_indo($tgl_awal) . ' s/d ' . tgl_indo($tgl_akhir);
$label_filter  = '';
if ($filterNama)   $label_filter .= ' | Nama: '   . htmlspecialchars($filterNama);
if ($filterNik)    $label_filter .= ' | NIK: '    . htmlspecialchars($filterNik);
if ($filterUnit)   $label_filter .= ' | Unit: '   . htmlspecialchars($filterUnit);
if ($filterStatus) $label_filter .= ' | Status: ' . ucfirst($filterStatus);

// ===================================================
// TEMPLATE HTML — MENGIKUTI GAYA laporan_izin_keluar.php
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
table, th, td { border: 1px solid #000; }
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
td.left   { text-align: left; }
td.center { text-align: center; }

/* SUMMARY BOX */
.summary-box {
    margin-top: 8px;
    padding: 6px;
    background-color: #f0f8ff;
    border: 1px solid #2196F3;
    font-size: 9pt;
}
.summary-box strong { color: #004085; }

/* SIGNATURE SECTION — SAMA PERSIS DENGAN laporan_izin_keluar.php */
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
.signature-box .location { margin-bottom: 5px; }
.signature-box .label    { margin-bottom: 3px; }
.qr {
    width: 80px;
    height: 80px;
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

/* FOOTER — SAMA PERSIS */
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
.footer strong { color: #2c3e50; }
.footer .legal {
    margin-top: 4px;
    font-size: 6.5pt;
    color: #888;
    font-style: italic;
}

/* Badge status */
.badge-ok      { color: #155724; background:#d4edda; padding:1px 5px; border-radius:3px; font-size:7pt; }
.badge-pending { color: #856404; background:#fff3cd; padding:1px 5px; border-radius:3px; font-size:7pt; }
.badge-tolak   { color: #721c24; background:#f8d7da; padding:1px 5px; border-radius:3px; font-size:7pt; }

/* Baris total */
.tr-total td { background: #f2f2f2; font-weight: bold; }
</style>
</head>
<body>

<!-- KOP SURAT -->
<div class="header">
  <h2>' . strtoupper($perusahaan['nama_perusahaan']) . '</h2>
  <div class="subkop">
    ' . $perusahaan['alamat'] . ' - ' . $perusahaan['kota'] . ', ' . $perusahaan['provinsi'] . '<br>
    Telp: ' . $perusahaan['kontak'] . ' | Email: ' . $perusahaan['email'] . '
  </div>
</div>

<div class="title">Laporan Data Lembur Karyawan</div>
<div class="periode">
    Periode: ' . $label_periode . ($label_filter ? '<br><small>' . $label_filter . '</small>' : '') . '
</div>

<table>
<thead>
<tr>
  <th style="width:3%">No</th>
  <th style="width:12%">No Surat</th>
  <th style="width:13%">Nama</th>
  <th style="width:9%">Unit</th>
  <th style="width:9%">Mulai</th>
  <th style="width:9%">Selesai</th>
  <th style="width:5%">Jam</th>
  <th style="width:14%">Jenis Pekerjaan</th>
  <th style="width:8%">Atasan</th>
  <th style="width:8%">SDM</th>
  <th style="width:10%">Catatan SDM</th>
</tr>
</thead>
<tbody>';

$no = 1;
if (count($rows) > 0) {
    foreach ($rows as $row) {
        $sa    = $row['status_atasan'];
        $ss    = $row['status_sdm'];

        $cls_a = $sa=='disetujui' ? 'badge-ok' : ($sa=='ditolak' ? 'badge-tolak' : 'badge-pending');
        $cls_s = $ss=='disetujui' ? 'badge-ok' : ($ss=='ditolak' ? 'badge-tolak' : 'badge-pending');

        // Nama atasan & SDM di bawah badge
        $info_a = $row['nama_atasan']
            ? '<br><small style="color:#555;font-size:6.5pt">oleh: ' . htmlspecialchars($row['nama_atasan']) . '</small>'
            : '';
        $info_s = $row['nama_sdm']
            ? '<br><small style="color:#555;font-size:6.5pt">oleh: ' . htmlspecialchars($row['nama_sdm']) . '</small>'
            : '';

        $html .= '
        <tr>
          <td class="center">' . $no . '</td>
          <td class="center" style="font-size:7pt">' . htmlspecialchars($row['no_surat']) . '</td>
          <td class="left">
            <strong>' . htmlspecialchars($row['nama']) . '</strong><br>
            <small style="color:#777;font-size:7pt">' . htmlspecialchars($row['nik']) . '</small><br>
            <small style="color:#777;font-size:6.5pt">' . htmlspecialchars($row['jabatan'] ?? '') . '</small>
          </td>
          <td class="center" style="font-size:8pt">' . htmlspecialchars($row['unit']) . '</td>
          <td class="center">' . date('d/m/Y', strtotime($row['lembur_mulai']))  . '<br>' . date('H:i', strtotime($row['lembur_mulai']))  . '</td>
          <td class="center">' . date('d/m/Y', strtotime($row['lembur_selesai'])) . '<br>' . date('H:i', strtotime($row['lembur_selesai'])) . '</td>
          <td class="center"><strong>' . $row['total_jam'] . '</strong></td>
          <td class="left">' . htmlspecialchars($row['jenis_pekerjaan']) . '</td>
          <td class="center"><span class="' . $cls_a . '">' . ucfirst($sa) . '</span>' . $info_a . '</td>
          <td class="center"><span class="' . $cls_s . '">' . ucfirst($ss) . '</span>' . $info_s . '</td>
          <td class="left" style="font-size:7.5pt">' . htmlspecialchars($row['catatan_sdm'] ?: '-') . '</td>
        </tr>';
        $no++;
    }

    // Baris total
    $html .= '
    <tr class="tr-total">
      <td colspan="6" class="center">TOTAL KESELURUHAN</td>
      <td class="center">' . number_format($total_jam, 1) . ' Jam</td>
      <td colspan="4"></td>
    </tr>';

} else {
    $html .= '<tr><td colspan="11" class="center" style="padding:20px">Tidak ada data untuk periode yang dipilih</td></tr>';
}

$html .= '
</tbody>
</table>

<!-- SUMMARY — dipindah ke bawah tabel -->
<div class="summary-box">
    <strong>Total Pengajuan:</strong> ' . $total_data . ' lembur &nbsp;|&nbsp;
    <strong>Total Jam:</strong> ' . number_format($total_jam, 1) . ' Jam &nbsp;|&nbsp;
    <strong>Disetujui:</strong> ' . $jml_setuju . ' &nbsp;|&nbsp;
    <strong>Pending:</strong> ' . $jml_pending . ' &nbsp;|&nbsp;
    <strong>Ditolak:</strong> ' . $jml_tolak . '<br>
    <strong>Dicetak oleh:</strong> ' . htmlspecialchars($nama_user) . ' &nbsp;|&nbsp;
    <strong>Tanggal Cetak:</strong> ' . tgl_indo(date('Y-m-d')) . ' ' . date('H:i') . ' WIB
</div>

<!-- SIGNATURE SECTION — SAMA PERSIS DENGAN laporan_izin_keluar.php -->
<div class="signature-section">
    <div class="signature-box">
        <div class="location">' . $perusahaan['kota'] . ', ' . tgl_indo(date('Y-m-d')) . '</div>
        <div class="label">Dibuat oleh:</div>';

// === INI BAGIAN TTE / QR — SAMA PERSIS DENGAN laporan_izin_keluar.php ===
if ($tte_pembuat) {
    $html .= '
        <img src="' . $qr_pembuat . '" class="qr">
        <div class="name">' . htmlspecialchars($nama_user) . '</div>
        <div class="nik">' . htmlspecialchars($tte_pembuat['jabatan'] ?? '-') . '</div>';
} else {
    // Jika tidak ada TTE aktif, tampilkan ruang tanda tangan manual
    $html .= '
        <div style="margin: 40px 0;"></div>
        <div class="name">' . htmlspecialchars($nama_user) . '</div>';
}

$html .= '
    </div>
</div>

<!-- FOOTER — SAMA PERSIS DENGAN laporan_izin_keluar.php -->
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
// GENERATE PDF — isRemoteEnabled=true WAJIB untuk QR image
// ===================================================
$options = new Options();
$options->set('isRemoteEnabled', true);   // <-- WAJIB agar QR dari URL external bisa tampil
$options->set('isHtml5ParserEnabled', true);

$pdf = new Dompdf($options);
$pdf->loadHtml($html);
$pdf->setPaper('A4', 'landscape');
$pdf->render();

// ===================================================
// EMBED TOKEN TTE DI PDF STREAM
// ===================================================
$pdf_output = $pdf->output();

if ($tte_pembuat) {
    $token_text = "\nTTE-TOKEN:" . $tte_pembuat['token'] . "\n";
    $pdf_output = str_replace('%%EOF', $token_text . '%%EOF', $pdf_output);
}

// ===================================================
// STREAM PDF KE BROWSER
// ===================================================
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Laporan_Lembur_' . date('Ymd') . '.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($pdf_output));

echo $pdf_output;
exit;
?>