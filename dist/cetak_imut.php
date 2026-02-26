<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 1);

session_start();
include 'koneksi.php';
require 'dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

date_default_timezone_set('Asia/Jakarta');

$user_id   = $_SESSION['user_id'] ?? 0;
$nama_user = $_SESSION['nama'] ?? $_SESSION['nama_user'] ?? 'Petugas';
if ($user_id == 0) {
    echo "<script>alert('Belum login');location.href='login.php';</script>"; exit;
}

// TTE
function getTteByUser($conn, $uid){
    if(empty($uid)) return null;
    $q = mysqli_query($conn,"SELECT * FROM tte_user WHERE user_id='$uid' AND status='aktif' ORDER BY created_at DESC LIMIT 1");
    return mysqli_fetch_assoc($q) ?: null;
}
function qrTte($token){
    $url = "http://".$_SERVER['HTTP_HOST']."/cek_tte.php?token=".$token;
    return "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=".urlencode($url);
}
function tgl_indo($tgl){
    if(!$tgl||$tgl=="0000-00-00") return "-";
    $b=[1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $s=explode('-',date('Y-m-d',strtotime($tgl)));
    return $s[2].' '.$b[(int)$s[1]].' '.$s[0];
}

$tte_pembuat = getTteByUser($conn, $user_id);
$qr_pembuat  = $tte_pembuat ? qrTte($tte_pembuat['token']) : '';

// FILTER
$bulan        = isset($_GET['bulan'])   ? intval($_GET['bulan'])   : date('n');
$tahun        = isset($_GET['tahun'])   ? intval($_GET['tahun'])   : date('Y');
$filter_jenis = isset($_GET['jenis'])   ? mysqli_real_escape_string($conn,$_GET['jenis']) : '';
$filter_status= isset($_GET['status'])  ? mysqli_real_escape_string($conn,$_GET['status']) : '';
$filter_unit  = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;

$namaBulan=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$jumlahHari = date('t', mktime(0,0,0,$bulan,1,$tahun));

// DATA PERUSAHAAN
$perusahaan = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM perusahaan LIMIT 1"));

// AMBIL DATA INDIKATOR
$whereBase = "WHERE MONTH(h.tanggal)=$bulan AND YEAR(h.tanggal)=$tahun";
if ($filter_jenis) $whereBase .= " AND h.jenis_indikator='$filter_jenis'";
if ($filter_unit > 0) {
    $whereBase .= " AND (h.jenis_indikator='unit' AND EXISTS (
        SELECT 1 FROM indikator_unit iu WHERE iu.id_unit=h.id_indikator AND iu.unit_id=$filter_unit
    ))";
}

$qInd = mysqli_query($conn,"SELECT DISTINCT h.jenis_indikator, h.id_indikator
    FROM indikator_harian h $whereBase ORDER BY h.jenis_indikator, h.id_indikator");

$allData    = [];
$rekapRingkas = ['total'=>0,'tercapai'=>0,'tidak_tercapai'=>0,'tervalidasi'=>0,'belum_validasi'=>0];

while($indRow = mysqli_fetch_assoc($qInd)){
    $jenis = $indRow['jenis_indikator'];
    $idInd = $indRow['id_indikator'];

    if($jenis=='nasional'){
        $qInfo=mysqli_query($conn,"SELECT nama_indikator,standar,numerator AS num_def,denominator AS den_def,penanggung_jawab,'' AS nama_unit FROM indikator_nasional WHERE id_nasional=$idInd");
    } elseif($jenis=='rs'){
        $qInfo=mysqli_query($conn,"SELECT r.nama_indikator,r.standar,r.numerator AS num_def,r.denominator AS den_def,u.nama AS penanggung_jawab,'' AS nama_unit FROM indikator_rs r LEFT JOIN users u ON r.penanggung_jawab=u.id WHERE r.id_rs=$idInd");
    } else {
        $qInfo=mysqli_query($conn,"SELECT iu.nama_indikator,iu.standar,iu.numerator AS num_def,iu.denominator AS den_def,u.nama AS penanggung_jawab,uk.nama_unit FROM indikator_unit iu LEFT JOIN users u ON iu.penanggung_jawab=u.id LEFT JOIN unit_kerja uk ON iu.unit_id=uk.id WHERE iu.id_unit=$idInd");
    }
    if(!$qInfo||!($info=mysqli_fetch_assoc($qInfo))) continue;

    $qData=mysqli_query($conn,"SELECT DAY(tanggal) AS hari, numerator, denominator,
        status_validasi, validasi_oleh, validasi_tanggal, tte_token, catatan_validasi
        FROM indikator_harian h $whereBase
        AND h.jenis_indikator='$jenis' AND h.id_indikator=$idInd ORDER BY tanggal");

    $harian=[]; $totalNum=0; $totalDen=0;
    $statusValidasi=''; $validasiOleh=''; $validasiTanggal=''; $tteToken=''; $catatanValidasi='';

    while($d=mysqli_fetch_assoc($qData)){
        $harian[$d['hari']]=$d;
        $totalNum+=$d['numerator']; $totalDen+=$d['denominator'];
        if(!empty($d['status_validasi'])){
            $statusValidasi=$d['status_validasi']; $validasiOleh=$d['validasi_oleh'];
            $validasiTanggal=$d['validasi_tanggal']; $tteToken=$d['tte_token'];
            $catatanValidasi=$d['catatan_validasi'];
        }
    }

    $persen   = ($totalDen>0) ? round($totalNum/$totalDen*100,2) : 0;
    $standar  = floatval($info['standar']);
    $tercapai = ($persen >= $standar);

    if($filter_status=='tercapai'       && !$tercapai) continue;
    if($filter_status=='tidak_tercapai' && $tercapai)  continue;
    if($filter_status=='belum_validasi' && $statusValidasi=='tervalidasi') continue;

    $allData[]=['jenis'=>$jenis,'id'=>$idInd,'nama'=>$info['nama_indikator'],'standar'=>$standar,
                'num_def'=>$info['num_def'],'den_def'=>$info['den_def'],
                'pj'=>$info['penanggung_jawab'],'unit'=>$info['nama_unit']??'',
                'harian'=>$harian,'totalNum'=>$totalNum,'totalDen'=>$totalDen,
                'persen'=>$persen,'tercapai'=>$tercapai,
                'statusValidasi'=>$statusValidasi,'validasiOleh'=>$validasiOleh,
                'validasiTanggal'=>$validasiTanggal,'tteToken'=>$tteToken,'catatanValidasi'=>$catatanValidasi];

    $rekapRingkas['total']++;
    if($tercapai) $rekapRingkas['tercapai']++; else $rekapRingkas['tidak_tercapai']++;
    if($statusValidasi=='tervalidasi') $rekapRingkas['tervalidasi']++; else $rekapRingkas['belum_validasi']++;
}

$pctCapaian = $rekapRingkas['total']>0 ? round($rekapRingkas['tercapai']/$rekapRingkas['total']*100) : 0;

// ===================================================
// BUILD HTML PDF
// ===================================================
ob_start(); ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; margin:0; padding:12px; line-height:1.3; }

/* KOP */
.header { text-align:center; border-bottom:2px solid #2c3e50; padding-bottom:8px; margin-bottom:10px; }
.header h2 { margin:0 0 4px 0; font-size:13pt; color:#2c3e50; font-weight:bold; }
.header .subkop { font-size:8pt; color:#555; line-height:1.4; }

/* TITLE */
.title { text-align:center; margin:8px 0 3px 0; font-size:12pt; font-weight:bold; color:#1f4fd8; text-transform:uppercase; }
.periode { text-align:center; font-size:8.5pt; color:#555; margin-bottom:10px; }

/* RINGKASAN BOX */
.ringkasan-box {
    border:1px solid #2196F3; background:#f0f8ff;
    padding:6px 10px; font-size:8.5pt; margin-bottom:10px;
}
.ringkasan-box strong { color:#004085; }
.ringkasan-grid { display:table; width:100%; }
.ringkasan-cell { display:table-cell; text-align:center; padding:5px; border:1px solid #dee2e6; }
.rg-val  { font-size:16pt; font-weight:800; }
.rg-label{ font-size:7pt; color:#888; }
.c-total { color:#6c757d; } .c-ok { color:#28a745; } .c-fail { color:#dc3545; }
.c-valid { color:#007bff; } .c-wait { color:#ffc107; } .c-pct  { color:#6f42c1; }

/* TABLE RINGKASAN */
table { border-collapse:collapse; width:100%; margin-bottom:8px; font-size:7.5pt; }
th,td { border:1px solid #555; padding:3px 4px; vertical-align:middle; }
th { background:#343a40; color:#fff; text-align:center; font-weight:bold; }
.td-c { text-align:center; }
.td-l { text-align:left; }
.ok-badge   { color:#155724; background:#d4edda; padding:1px 5px; border-radius:3px; font-size:7pt; }
.fail-badge { color:#721c24; background:#f8d7da; padding:1px 5px; border-radius:3px; font-size:7pt; }
.v-badge    { color:#004085; background:#cce5ff; padding:1px 5px; border-radius:3px; font-size:7pt; }
.w-badge    { color:#856404; background:#fff3cd; padding:1px 5px; border-radius:3px; font-size:7pt; }
.j-nasional { background:#cce5ff; color:#004085; padding:1px 5px; border-radius:3px; font-size:7pt; }
.j-rs       { background:#d4edda; color:#155724; padding:1px 5px; border-radius:3px; font-size:7pt; }
.j-unit     { background:#d1ecf1; color:#0c5460; padding:1px 5px; border-radius:3px; font-size:7pt; }

/* TABLE HARIAN */
.tbl-harian th  { background:#495057; font-size:7pt; padding:2px 3px; }
.th-sub         { background:#6c757d !important; }
.td-day         { font-size:7pt; padding:2px; min-width:18px; }
.td-total       { background:#e9ecef; font-weight:700; }
.td-persen      { min-width:60px; }
.num-row        { background:#e8f5e9; }
.den-row        { background:#fff8e1; }
.pct-row        { background:#f0f4ff; font-style:italic; }
.pct-ok         { color:#155724; font-weight:700; }
.pct-fail       { color:#721c24; font-weight:700; }

/* BLOK INDIKATOR */
.blok { margin-bottom:14px; page-break-inside:avoid; }
.blok-title { font-size:9.5pt; font-weight:bold; margin-bottom:3px; }
.blok-formula { font-size:7.5pt; color:#555; font-style:italic; margin-bottom:4px; }

/* VALIDASI */
.validasi-ok   { color:#155724; }
.validasi-fail { color:#721c24; }
.validasi-wait { color:#856404; }
.qr-img        { width:55px; height:55px; }
.validasi-info { font-size:7pt; color:#555; }

/* SIGNATURE */
.signature-section { margin-top:12px; width:100%; overflow:hidden; }
.signature-box { float:right; width:200px; text-align:center; font-size:8.5pt; }
.qr-sig { width:55px; height:55px; margin:5px auto; }
.sig-name { font-weight:bold; text-decoration:underline; font-size:8.5pt; }
.sig-sub  { font-size:7.5pt; color:#555; }

/* FOOTER */
.footer { clear:both; margin-top:12px; font-size:6.5pt; text-align:center; color:#666;
          border-top:1px solid #ccc; padding-top:6px; line-height:1.4; }
.footer .legal { font-style:italic; font-size:6pt; color:#999; margin-top:3px; }

/* PAGE BREAK */
.page-break { page-break-after:always; }
</style>
</head>
<body>

<!-- KOP -->
<div class="header">
  <h2><?= strtoupper($perusahaan['nama_perusahaan']) ?></h2>
  <div class="subkop">
    <?= $perusahaan['alamat'] ?> &ndash; <?= $perusahaan['kota'] ?>, <?= $perusahaan['provinsi'] ?><br>
    Telp: <?= $perusahaan['kontak'] ?> &nbsp;|&nbsp; Email: <?= $perusahaan['email'] ?>
  </div>
</div>

<div class="title">Laporan Capaian Indikator Mutu (IMUT)</div>
<div class="periode">
    Periode: <?= $namaBulan[$bulan].' '.$tahun ?>
    <?php if($filter_jenis): ?> &nbsp;|&nbsp; Jenis: <?= strtoupper($filter_jenis) ?><?php endif; ?>
    <?php if($filter_status): ?> &nbsp;|&nbsp; Status: <?= ucwords(str_replace('_',' ',$filter_status)) ?><?php endif; ?>
</div>

<!-- RINGKASAN -->
<div class="ringkasan-box">
    <strong>Ringkasan:</strong>
    Total Indikator: <strong><?= $rekapRingkas['total'] ?></strong> &nbsp;|&nbsp;
    Tercapai: <strong style="color:#28a745"><?= $rekapRingkas['tercapai'] ?></strong> &nbsp;|&nbsp;
    Tidak Tercapai: <strong style="color:#dc3545"><?= $rekapRingkas['tidak_tercapai'] ?></strong> &nbsp;|&nbsp;
    Tervalidasi TTE: <strong style="color:#007bff"><?= $rekapRingkas['tervalidasi'] ?></strong> &nbsp;|&nbsp;
    Belum Validasi: <strong style="color:#856404"><?= $rekapRingkas['belum_validasi'] ?></strong> &nbsp;|&nbsp;
    % Capaian: <strong style="color:#6f42c1"><?= $pctCapaian ?>%</strong><br>
    <strong>Dicetak oleh:</strong> <?= htmlspecialchars($nama_user) ?> &nbsp;|&nbsp;
    <strong>Tanggal Cetak:</strong> <?= tgl_indo(date('Y-m-d')).' '.date('H:i') ?> WIB
</div>

<?php if(count($allData) > 0): ?>

<!-- ====================================================
     TABEL 1 — RINGKASAN SEMUA INDIKATOR
==================================================== -->
<p style="font-weight:bold;font-size:9pt;margin:8px 0 4px 0">A. Ringkasan Capaian Seluruh Indikator</p>
<table>
<thead>
<tr>
  <th style="width:3%">No</th>
  <th style="width:25%">Nama Indikator</th>
  <th style="width:6%">Jenis</th>
  <th style="width:14%">Unit / PJ</th>
  <th style="width:6%">Standar</th>
  <th style="width:7%">Num</th>
  <th style="width:7%">Den</th>
  <th style="width:8%">Capaian</th>
  <th style="width:10%">Status</th>
  <th style="width:14%">Validasi TTE</th>
</tr>
</thead>
<tbody>
<?php foreach($allData as $i => $d): ?>
<tr>
  <td class="td-c"><?= $i+1 ?></td>
  <td class="td-l"><?= htmlspecialchars($d['nama']) ?></td>
  <td class="td-c"><span class="j-<?= $d['jenis'] ?>"><?= strtoupper($d['jenis']) ?></span></td>
  <td class="td-l">
    <?php if($d['unit']): ?><small style="color:#777"><?= htmlspecialchars($d['unit']) ?></small><br><?php endif; ?>
    <?= htmlspecialchars($d['pj']) ?>
  </td>
  <td class="td-c"><?= $d['standar'] ?>%</td>
  <td class="td-c"><?= $d['totalNum'] ?></td>
  <td class="td-c"><?= $d['totalDen'] ?></td>
  <td class="td-c"><span class="<?= $d['tercapai']?'ok-badge':'fail-badge' ?>"><?= number_format($d['persen'],2) ?>%</span></td>
  <td class="td-c">
    <?php if($d['tercapai']): ?>
      <span class="ok-badge">&#10003; Tercapai</span>
    <?php else: ?>
      <span class="fail-badge">&#10007; Tidak Tercapai</span>
    <?php endif; ?>
  </td>
  <td class="td-c">
    <?php if($d['statusValidasi']=='tervalidasi'): ?>
      <span class="v-badge">&#10003; Tervalidasi</span>
      <?php if($d['tteToken']): ?>
      <br><img src="https://api.qrserver.com/v1/create-qr-code/?size=50x50&data=<?= urlencode('http://'.$_SERVER['HTTP_HOST'].'/cek_tte.php?token='.$d['tteToken']) ?>" style="width:35px;height:35px">
      <?php endif; ?>
    <?php elseif($d['statusValidasi']=='ditolak'): ?>
      <span class="fail-badge">Ditolak</span>
    <?php else: ?>
      <span class="w-badge">Belum Validasi</span>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="page-break"></div>

<!-- ====================================================
     TABEL 2 — DATA HARIAN PER INDIKATOR
==================================================== -->
<p style="font-weight:bold;font-size:9pt;margin:8px 0 4px 0">B. Data Harian per Indikator</p>

<?php foreach($allData as $i => $d): ?>
<div class="blok">
    <div class="blok-title">
        <?= $i+1 ?>. 
        <span class="j-<?= $d['jenis'] ?>"><?= strtoupper($d['jenis']) ?></span>
        &nbsp;<?= htmlspecialchars($d['nama']) ?>
        <?php if($d['unit']): ?>&nbsp;<small style="color:#777">(<?= htmlspecialchars($d['unit']) ?>)</small><?php endif; ?>
        &nbsp;&mdash;&nbsp;
        <?php if($d['tercapai']): ?>
            <span class="ok-badge">&#10003; Tercapai <?= number_format($d['persen'],2) ?>%</span>
        <?php else: ?>
            <span class="fail-badge">&#10007; Tidak Tercapai <?= number_format($d['persen'],2) ?>%</span>
        <?php endif; ?>
    </div>
    <div class="blok-formula">
        <strong>Numerator:</strong> <?= htmlspecialchars($d['num_def']) ?> &nbsp;|&nbsp;
        <strong>Denominator:</strong> <?= htmlspecialchars($d['den_def']) ?> &nbsp;|&nbsp;
        <strong>Standar:</strong> <?= $d['standar'] ?>% &nbsp;|&nbsp;
        <strong>PJ:</strong> <?= htmlspecialchars($d['pj']) ?>
    </div>

    <table class="tbl-harian">
    <thead>
      <tr>
        <th style="width:70px; text-align:left">Komponen</th>
        <?php for($dd=1;$dd<=$jumlahHari;$dd++): ?>
        <th class="td-day"><?=$dd?></th>
        <?php endfor; ?>
        <th class="td-total" style="width:35px">Total</th>
        <th class="td-persen" style="width:60px">Capaian</th>
        <th style="width:90px">Validasi TTE</th>
      </tr>
    </thead>
    <tbody>
      <!-- Numerator -->
      <tr class="num-row">
        <td class="td-l">Numerator</td>
        <?php for($dd=1;$dd<=$jumlahHari;$dd++): ?>
        <td class="td-c td-day"><?= isset($d['harian'][$dd]) ? $d['harian'][$dd]['numerator'] : '-' ?></td>
        <?php endfor; ?>
        <td class="td-c td-total"><?= $d['totalNum'] ?></td>
        <td rowspan="3" class="td-c td-persen" style="vertical-align:middle">
          <span class="<?= $d['tercapai']?'ok-badge':'fail-badge' ?>"><?= number_format($d['persen'],2) ?>%</span><br>
          <small style="font-size:6.5pt;color:#888">standar <?= $d['standar'] ?>%</small>
        </td>
        <td rowspan="3" class="td-c" style="vertical-align:middle">
          <?php if($d['statusValidasi']=='tervalidasi'): ?>
            <div class="validasi-ok"><strong>&#10003; TERVALIDASI</strong></div>
            <?php if($d['tteToken']): ?>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=55x55&data=<?= urlencode('http://'.$_SERVER['HTTP_HOST'].'/cek_tte.php?token='.$d['tteToken']) ?>" class="qr-img">
            <?php endif; ?>
            <div class="validasi-info">
                oleh: <?= htmlspecialchars($d['validasiOleh']) ?><br>
                <?= $d['validasiTanggal'] ? date('d/m/Y H:i',strtotime($d['validasiTanggal'])) : '' ?>
            </div>
          <?php elseif($d['statusValidasi']=='ditolak'): ?>
            <div class="validasi-fail"><strong>&#10007; DITOLAK</strong></div>
            <div class="validasi-info"><?= htmlspecialchars($d['catatanValidasi']) ?></div>
          <?php else: ?>
            <div class="validasi-wait">Belum Validasi</div>
          <?php endif; ?>
        </td>
      </tr>
      <!-- Denominator -->
      <tr class="den-row">
        <td class="td-l">Denominator</td>
        <?php for($dd=1;$dd<=$jumlahHari;$dd++): ?>
        <td class="td-c td-day"><?= isset($d['harian'][$dd]) ? $d['harian'][$dd]['denominator'] : '-' ?></td>
        <?php endfor; ?>
        <td class="td-c td-total"><?= $d['totalDen'] ?></td>
      </tr>
      <!-- % Harian -->
      <tr class="pct-row">
        <td class="td-l" style="font-size:7pt">% Harian</td>
        <?php for($dd=1;$dd<=$jumlahHari;$dd++):
            $ph = isset($d['harian'][$dd]) && $d['harian'][$dd]['denominator']>0
                ? round($d['harian'][$dd]['numerator']/$d['harian'][$dd]['denominator']*100,1) : null;
        ?>
        <td class="td-c td-day <?= $ph!==null?($ph>=$d['standar']?'pct-ok':'pct-fail'):'' ?>">
            <?= $ph!==null ? $ph.'%' : '-' ?>
        </td>
        <?php endfor; ?>
        <td class="td-c td-total" style="font-size:7pt"><?= number_format($d['persen'],2) ?>%</td>
      </tr>
    </tbody>
    </table>
</div>
<?php endforeach; ?>

<!-- KETERANGAN BAWAH -->
<div style="margin-top:8px; padding:5px 8px; border:1px solid #dee2e6; background:#f8f9fa; font-size:7.5pt;">
    <strong>Keterangan:</strong>
    <span class="ok-badge">&#10003; Tercapai</span> = Capaian &ge; Standar &nbsp;&nbsp;
    <span class="fail-badge">&#10007; Tidak Tercapai</span> = Capaian &lt; Standar &nbsp;&nbsp;
    Baris hijau = Numerator &nbsp;&nbsp; Baris kuning = Denominator &nbsp;&nbsp; Baris biru = % Harian
</div>

<?php else: ?>
<div style="text-align:center;padding:30px;color:#888">Tidak ada data untuk periode yang dipilih.</div>
<?php endif; ?>

<!-- TANDA TANGAN -->
<div class="signature-section">
  <div class="signature-box">
    <div><?= $perusahaan['kota'] ?>, <?= tgl_indo(date('Y-m-d')) ?></div>
    <div style="margin-bottom:3px">Dicetak oleh / Petugas PMKP:</div>
    <?php if($tte_pembuat): ?>
    <img src="<?= $qr_pembuat ?>" class="qr-sig">
    <?php else: ?>
    <div style="margin:35px 0"></div>
    <?php endif; ?>
    <div class="sig-name"><?= htmlspecialchars($nama_user) ?></div>
    <div class="sig-sub"><?= htmlspecialchars($tte_pembuat['jabatan'] ?? '') ?></div>
  </div>
</div>

<!-- FOOTER -->
<div class="footer">
  <strong>Tanda Tangan Elektronik (TTE) Non Sertifikasi</strong><br>
  Dokumen ini menggunakan TTE Non Sertifikasi yang sah untuk penggunaan internal perusahaan<br>
  sesuai <em>PP Nomor 71 Tahun 2019</em> dan <em>UU ITE No. 11 Tahun 2008 jo. UU No. 19 Tahun 2016</em>
  <div class="legal">Dokumen di-generate melalui <strong>FixPoint &ndash; Smart Office Management System</strong></div>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

// GENERATE PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$pdf = new Dompdf($options);
$pdf->loadHtml($html);
$pdf->setPaper('A4', 'landscape');
$pdf->render();

$out = $pdf->output();
if($tte_pembuat){
    $out = str_replace('%%EOF', "\nTTE-TOKEN:".$tte_pembuat['token']."\n".'%%EOF', $out);
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Capaian_IMUT_'.$namaBulan[$bulan].'_'.$tahun.'.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: '.strlen($out));
echo $out;
exit;
?>