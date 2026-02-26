<?php
// capaian_imut.php
include 'security.php';
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id   = $_SESSION['user_id'] ?? 0;
$nama_user = '';
if ($user_id > 0) {
    $qUser = mysqli_query($conn, "SELECT nama FROM users WHERE id='" . intval($user_id) . "' LIMIT 1");
    if ($qUser && $rowU = mysqli_fetch_assoc($qUser)) $nama_user = $rowU['nama'];
}

// Cek akses menu
$current_file = basename(__FILE__);
$rAkses = mysqli_query($conn, "SELECT 1 FROM akses_menu
    JOIN menu ON akses_menu.menu_id = menu.id
    WHERE akses_menu.user_id='" . intval($user_id) . "'
    AND menu.file_menu='" . mysqli_real_escape_string($conn, $current_file) . "'");
if (!$rAkses || mysqli_num_rows($rAkses) == 0) {
    echo "<script>alert('Anda tidak memiliki akses.');window.location.href='dashboard.php';</script>";
    exit;
}

// ===================================================
// FILTER
// ===================================================
$bulan         = isset($_GET['bulan'])   ? intval($_GET['bulan'])   : date('n');
$tahun         = isset($_GET['tahun'])   ? intval($_GET['tahun'])   : date('Y');
$filter_jenis  = isset($_GET['jenis'])   ? mysqli_real_escape_string($conn, $_GET['jenis']) : '';
$filter_status = isset($_GET['status'])  ? mysqli_real_escape_string($conn, $_GET['status']) : ''; // tercapai / tidak_tercapai / belum_validasi
$filter_unit   = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;

$namaBulan = ['', 'Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];

$jumlahHari = date('t', mktime(0, 0, 0, $bulan, 1, $tahun));

// ===================================================
// DAFTAR UNIT untuk filter
// ===================================================
$listUnit = mysqli_query($conn, "SELECT id, nama_unit FROM unit_kerja ORDER BY nama_unit");

// ===================================================
// AMBIL SEMUA INDIKATOR YANG ADA DATA DI BULAN INI
// ===================================================
$whereBase = "WHERE MONTH(h.tanggal)=$bulan AND YEAR(h.tanggal)=$tahun";
if ($filter_jenis)  $whereBase .= " AND h.jenis_indikator='$filter_jenis'";
if ($filter_unit > 0) {
    $whereBase .= " AND (
        (h.jenis_indikator='unit' AND EXISTS (
            SELECT 1 FROM indikator_unit iu WHERE iu.id_unit=h.id_indikator AND iu.unit_id=$filter_unit
        ))
    )";
}

$qInd = mysqli_query($conn, "SELECT DISTINCT h.jenis_indikator, h.id_indikator
    FROM indikator_harian h $whereBase
    ORDER BY h.jenis_indikator, h.id_indikator");

// ===================================================
// KUMPULKAN DATA SEMUA INDIKATOR
// ===================================================
$allData    = [];
$rekapRingkas = ['total' => 0, 'tercapai' => 0, 'tidak_tercapai' => 0,
                 'tervalidasi' => 0, 'belum_validasi' => 0, 'total_jam' => 0];

while ($indRow = mysqli_fetch_assoc($qInd)) {
    $jenis = $indRow['jenis_indikator'];
    $idInd = $indRow['id_indikator'];

    // Ambil info indikator
    if ($jenis == 'nasional') {
        $qInfo = mysqli_query($conn, "SELECT nama_indikator, standar, numerator AS num_def, denominator AS den_def,
            penanggung_jawab, '' AS nama_unit FROM indikator_nasional WHERE id_nasional=$idInd");
    } elseif ($jenis == 'rs') {
        $qInfo = mysqli_query($conn, "SELECT r.nama_indikator, r.standar, r.numerator AS num_def, r.denominator AS den_def,
            u.nama AS penanggung_jawab, '' AS nama_unit
            FROM indikator_rs r LEFT JOIN users u ON r.penanggung_jawab=u.id WHERE r.id_rs=$idInd");
    } else {
        $qInfo = mysqli_query($conn, "SELECT iu.nama_indikator, iu.standar, iu.numerator AS num_def, iu.denominator AS den_def,
            u.nama AS penanggung_jawab, uk.nama_unit
            FROM indikator_unit iu
            LEFT JOIN users u  ON iu.penanggung_jawab=u.id
            LEFT JOIN unit_kerja uk ON iu.unit_id=uk.id
            WHERE iu.id_unit=$idInd");
    }

    if (!$qInfo || !($info = mysqli_fetch_assoc($qInfo))) continue;

    // Ambil data harian
    $qData = mysqli_query($conn, "SELECT DAY(tanggal) AS hari, numerator, denominator,
        status_validasi, validasi_oleh, validasi_tanggal, tte_token, catatan_validasi
        FROM indikator_harian h $whereBase
        AND h.jenis_indikator='$jenis' AND h.id_indikator=$idInd
        ORDER BY tanggal");

    $harian          = [];
    $totalNum        = 0;
    $totalDen        = 0;
    $statusValidasi  = '';
    $validasiOleh    = '';
    $validasiTanggal = '';
    $tteToken        = '';
    $catatanValidasi = '';

    while ($d = mysqli_fetch_assoc($qData)) {
        $harian[$d['hari']] = $d;
        $totalNum += $d['numerator'];
        $totalDen += $d['denominator'];
        if (!empty($d['status_validasi'])) {
            $statusValidasi  = $d['status_validasi'];
            $validasiOleh    = $d['validasi_oleh'];
            $validasiTanggal = $d['validasi_tanggal'];
            $tteToken        = $d['tte_token'];
            $catatanValidasi = $d['catatan_validasi'];
        }
    }

    $persen = ($totalDen > 0) ? round($totalNum / $totalDen * 100, 2) : 0;
    $standar = floatval($info['standar']);
    $tercapai = ($persen >= $standar);

    // Filter status
    if ($filter_status == 'tercapai' && !$tercapai) continue;
    if ($filter_status == 'tidak_tercapai' && $tercapai) continue;
    if ($filter_status == 'belum_validasi' && $statusValidasi == 'tervalidasi') continue;

    $allData[] = [
        'jenis'           => $jenis,
        'id'              => $idInd,
        'nama'            => $info['nama_indikator'],
        'standar'         => $standar,
        'num_def'         => $info['num_def'],
        'den_def'         => $info['den_def'],
        'pj'              => $info['penanggung_jawab'],
        'unit'            => $info['nama_unit'] ?? '',
        'harian'          => $harian,
        'totalNum'        => $totalNum,
        'totalDen'        => $totalDen,
        'persen'          => $persen,
        'tercapai'        => $tercapai,
        'statusValidasi'  => $statusValidasi,
        'validasiOleh'    => $validasiOleh,
        'validasiTanggal' => $validasiTanggal,
        'tteToken'        => $tteToken,
        'catatanValidasi' => $catatanValidasi,
    ];

    // Rekap ringkas
    $rekapRingkas['total']++;
    if ($tercapai) $rekapRingkas['tercapai']++; else $rekapRingkas['tidak_tercapai']++;
    if ($statusValidasi == 'tervalidasi') $rekapRingkas['tervalidasi']++; else $rekapRingkas['belum_validasi']++;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Capaian Indikator Mutu – PMKP</title>
  <link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <style>
    .table-rekap { font-size: 11px; }
    .table-rekap th, .table-rekap td {
      padding: 4px;
      text-align: center;
      vertical-align: middle;
      border: 1px solid #dee2e6;
    }
    .table-rekap th { background: #f8f9fa; font-weight: 600; }
    .table-rekap .cell-nama {
      text-align: left;
      font-weight: 500;
      min-width: 120px;
    }
    .day-cell { min-width: 28px; }
    .total-cell { background: #e9ecef; font-weight: 700; }
    .formula-text { font-size: 10px; color: #555; font-style: italic; margin-bottom: 8px; }
    .flash-center {
      position: fixed; top: 20%; left: 50%; transform: translate(-50%, -50%);
      z-index: 9999; min-width: 300px; max-width: 90%; text-align: center;
      padding: 15px; border-radius: 8px; font-weight: 500;
      box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }
    .filter-card { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
    .num-row td  { background: #f0fff0; }
    .den-row td  { background: #fffbf0; }
    .pct-row td  { background: #f0f4ff; font-size: 10px; font-style: italic; }
    .pct-ok   { color: #155724; font-weight: 700; }
    .pct-fail { color: #721c24; font-weight: 700; }
    .validasi-info { font-size: 10px; }
    .chart-modal-body { position: relative; height: 400px; }
    @media print {
      .main-sidebar, .navbar, .card-header-action, .filter-card, .btn, .no-print { display: none !important; }
      .main-content { margin-left: 0 !important; padding: 0 !important; }
      .card { border: none !important; box-shadow: none !important; }
      .table-rekap { font-size: 8px; }
      .table-rekap th, .table-rekap td { padding: 2px; }
      @page { size: A4 landscape; margin: 10mm; }
    }
  </style>
</head>
<body>
<div id="app">
  <div class="main-wrapper main-wrapper-1">
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
      <section class="section">
        <div class="section-body">

        <?php if(isset($_SESSION['flash_message'])): $ft = $_SESSION['flash_type'] ?? 'info'; ?>
          <div class="alert alert-<?= $ft ?> flash-center" id="flashMsg">
            <i class="fas fa-<?= $ft=='success'?'check-circle':($ft=='danger'?'exclamation-circle':'info-circle') ?>"></i>
            <?= htmlspecialchars($_SESSION['flash_message']); unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
          </div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header">
            <h4><i class="fas fa-chart-pie"></i> Capaian Indikator Mutu – PMKP</h4>
            <div class="card-header-action no-print">
              <button class="btn btn-danger btn-sm" onclick="window.open('cetak_imut.php?<?= http_build_query(['bulan'=>$bulan,'tahun'=>$tahun,'jenis'=>$filter_jenis,'status'=>$filter_status,'unit_id'=>$filter_unit]) ?>','_blank')">
                <i class="fas fa-file-pdf"></i> Cetak PDF
              </button>
              <button class="btn btn-success btn-sm ml-1" onclick="exportExcel()">
                <i class="fas fa-file-excel"></i> Export Excel
              </button>
              <button class="btn btn-secondary btn-sm ml-1" onclick="window.print()">
                <i class="fas fa-print"></i> Print
              </button>
            </div>
          </div>
          <div class="card-body">

            <!-- FILTER -->
            <div class="filter-card no-print">
              <form method="GET">
                <div class="row">
                  <div class="col-md-2">
                    <div class="form-group mb-2">
                      <label><i class="fas fa-calendar"></i> Bulan</label>
                      <select name="bulan" class="form-control" onchange="this.form.submit()">
                        <?php for($m=1;$m<=12;$m++): ?>
                        <option value="<?=$m?>" <?=($bulan==$m)?'selected':''?>><?=$namaBulan[$m]?></option>
                        <?php endfor; ?>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <div class="form-group mb-2">
                      <label><i class="fas fa-calendar-alt"></i> Tahun</label>
                      <select name="tahun" class="form-control" onchange="this.form.submit()">
                        <?php for($y=date('Y')-2;$y<=date('Y')+1;$y++): ?>
                        <option value="<?=$y?>" <?=($tahun==$y)?'selected':''?>><?=$y?></option>
                        <?php endfor; ?>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <div class="form-group mb-2">
                      <label><i class="fas fa-layer-group"></i> Jenis</label>
                      <select name="jenis" class="form-control">
                        <option value="">-- Semua Jenis --</option>
                        <option value="nasional" <?=($filter_jenis=='nasional')?'selected':''?>>Nasional</option>
                        <option value="rs"       <?=($filter_jenis=='rs')?'selected':''?>>RS</option>
                        <option value="unit"     <?=($filter_jenis=='unit')?'selected':''?>>Unit</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <div class="form-group mb-2">
                      <label><i class="fas fa-hospital"></i> Unit Kerja</label>
                      <select name="unit_id" class="form-control">
                        <option value="0">-- Semua Unit --</option>
                        <?php mysqli_data_seek($listUnit,0); while($u=mysqli_fetch_assoc($listUnit)): ?>
                        <option value="<?=$u['id']?>" <?=($filter_unit==$u['id'])?'selected':''?>><?=htmlspecialchars($u['nama_unit'])?></option>
                        <?php endwhile; ?>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <div class="form-group mb-2">
                      <label><i class="fas fa-flag"></i> Status Capaian</label>
                      <select name="status" class="form-control">
                        <option value="">-- Semua Status --</option>
                        <option value="tercapai"       <?=($filter_status=='tercapai')?'selected':''?>>Tercapai</option>
                        <option value="tidak_tercapai" <?=($filter_status=='tidak_tercapai')?'selected':''?>>Tidak Tercapai</option>
                        <option value="belum_validasi" <?=($filter_status=='belum_validasi')?'selected':''?>>Belum Validasi</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <div class="form-group mb-2">
                      <label>&nbsp;</label>
                      <div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                        <a href="capaian_imut.php" class="btn btn-secondary ml-1"><i class="fas fa-redo"></i></a>
                      </div>
                    </div>
                  </div>
                </div>
              </form>
            </div>

            <!-- SUMMARY CARDS -->
            <div class="row mb-3 no-print">
              <?php
              $cards = [
                ['label'=>'Total Indikator', 'val'=>$rekapRingkas['total'],          'sub'=>$namaBulan[$bulan].' '.$tahun, 'color'=>'#6c757d'],
                ['label'=>'Tercapai',        'val'=>$rekapRingkas['tercapai'],        'sub'=>'&ge; Standar',                'color'=>'#28a745'],
                ['label'=>'Tidak Tercapai',  'val'=>$rekapRingkas['tidak_tercapai'],  'sub'=>'&lt; Standar',                'color'=>'#dc3545'],
                ['label'=>'Tervalidasi TTE', 'val'=>$rekapRingkas['tervalidasi'],     'sub'=>'Sudah ditandatangani',        'color'=>'#007bff'],
                ['label'=>'Belum Validasi',  'val'=>$rekapRingkas['belum_validasi'],  'sub'=>'Perlu tindak lanjut',         'color'=>'#fd7e14'],
                ['label'=>'% Capaian',       'val'=>($rekapRingkas['total']>0?round($rekapRingkas['tercapai']/$rekapRingkas['total']*100):0).'%', 'sub'=>'dari total indikator', 'color'=>'#6f42c1'],
              ];
              foreach($cards as $c): ?>
              <div class="col-md-2 col-sm-4 mb-2">
                <div style="background:<?= $c['color'] ?>;border-radius:8px;padding:8px 12px;color:#fff;height:70px;display:flex;flex-direction:column;justify-content:center;">
                  <div style="font-size:10px;font-weight:700;text-transform:uppercase;opacity:.85;letter-spacing:.3px;line-height:1.2"><?= $c['label'] ?></div>
                  <div style="font-size:22px;font-weight:800;line-height:1.1;margin:2px 0"><?= $c['val'] ?></div>
                  <div style="font-size:10px;opacity:.75"><?= $c['sub'] ?></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>

            <!-- TABEL DATA PER INDIKATOR -->
            <?php if(count($allData) > 0): ?>
              <?php $chartIndex = 0; foreach($allData as $i => $d): $chartIndex++; ?>

              <div class="mb-4">
                <h6 class="mb-1">
                  <span class="badge badge-<?= $d['jenis']=='nasional'?'primary':($d['jenis']=='rs'?'success':'info') ?>">
                    <?= strtoupper($d['jenis']) ?>
                  </span>
                  <?= htmlspecialchars($d['nama']) ?>
                  <?php if($d['unit']): ?>
                    <span class="badge badge-light border"><?= htmlspecialchars($d['unit']) ?></span>
                  <?php endif; ?>
                  <small class="text-muted ml-1">(PJ: <?= htmlspecialchars($d['pj']) ?>)</small>
                  <?php if($d['tercapai']): ?>
                    <span class="badge badge-success ml-1"><i class="fas fa-check-circle"></i> Tercapai</span>
                  <?php else: ?>
                    <span class="badge badge-danger ml-1"><i class="fas fa-times-circle"></i> Tidak Tercapai</span>
                  <?php endif; ?>
                  <button class="btn btn-info btn-sm ml-2 no-print" data-toggle="modal" data-target="#chartModal<?= $chartIndex ?>">
                    <i class="fas fa-chart-bar"></i> Grafik
                  </button>
                </h6>
                <div class="formula-text">
                  <strong>Numerator:</strong> <?= htmlspecialchars($d['num_def']) ?> &nbsp;|&nbsp;
                  <strong>Denominator:</strong> <?= htmlspecialchars($d['den_def']) ?> &nbsp;|&nbsp;
                  <strong>Standar:</strong> <?= $d['standar'] ?>%
                </div>

                <div class="table-responsive">
                  <table class="table table-bordered table-rekap table-sm">
                    <thead>
                      <tr>
                        <th rowspan="2" class="cell-nama">Komponen</th>
                        <?php for($dd=1;$dd<=$jumlahHari;$dd++): ?>
                        <th class="day-cell"><?=$dd?></th>
                        <?php endfor; ?>
                        <th rowspan="2" class="total-cell">Total</th>
                        <th rowspan="2" style="min-width:80px">Capaian</th>
                        <th rowspan="2" style="min-width:130px">Validasi TTE</th>
                      </tr>
                    </thead>
                    <tbody>
                      <!-- Numerator -->
                      <tr class="num-row">
                        <td class="cell-nama">Numerator</td>
                        <?php for($dd=1;$dd<=$jumlahHari;$dd++): ?>
                        <td class="day-cell"><?= isset($d['harian'][$dd]) ? $d['harian'][$dd]['numerator'] : '-' ?></td>
                        <?php endfor; ?>
                        <td class="total-cell"><?= $d['totalNum'] ?></td>
                        <td rowspan="3" class="total-cell text-center" style="vertical-align:middle">
                          <strong><?= number_format($d['persen'],2) ?>%</strong>
                          <?php if($d['tercapai']): ?>
                            <br><span class="badge badge-success">Tercapai</span>
                          <?php else: ?>
                            <br><span class="badge badge-danger">Tidak Tercapai</span>
                          <?php endif; ?>
                          <br><small class="text-muted">standar <?= $d['standar'] ?>%</small>
                        </td>
                        <td rowspan="3" class="text-center" style="vertical-align:middle">
                          <?php if($d['statusValidasi']=='tervalidasi'): ?>
                            <div class="text-success mb-1">
                              <i class="fas fa-check-circle"></i> <strong>TERVALIDASI</strong>
                            </div>
                            <?php if($d['tteToken']): ?>
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=70x70&data=<?= urlencode('http://'.$_SERVER['HTTP_HOST'].'/cek_tte.php?token='.$d['tteToken']) ?>" style="width:70px;height:70px" alt="QR TTE">
                            <?php endif; ?>
                            <div class="validasi-info text-muted mt-1">
                              Oleh: <strong><?= htmlspecialchars($d['validasiOleh']) ?></strong><br>
                              <?= $d['validasiTanggal'] ? date('d/m/Y H:i',strtotime($d['validasiTanggal'])) : '' ?>
                            </div>
                          <?php elseif($d['statusValidasi']=='ditolak'): ?>
                            <div class="text-danger">
                              <i class="fas fa-times-circle"></i> <strong>DITOLAK</strong>
                            </div>
                            <small class="text-muted"><?= htmlspecialchars($d['catatanValidasi']) ?></small>
                          <?php else: ?>
                            <div class="text-warning">
                              <i class="fas fa-hourglass-half"></i>
                              <br><small class="text-muted">Belum Divalidasi</small>
                            </div>
                          <?php endif; ?>
                        </td>
                      </tr>
                      <!-- Denominator -->
                      <tr class="den-row">
                        <td class="cell-nama">Denominator</td>
                        <?php for($dd=1;$dd<=$jumlahHari;$dd++): ?>
                        <td class="day-cell"><?= isset($d['harian'][$dd]) ? $d['harian'][$dd]['denominator'] : '-' ?></td>
                        <?php endfor; ?>
                        <td class="total-cell"><?= $d['totalDen'] ?></td>
                      </tr>
                      <!-- % Harian -->
                      <tr class="pct-row">
                        <td class="cell-nama" style="font-size:10px">% Harian</td>
                        <?php for($dd=1;$dd<=$jumlahHari;$dd++):
                          $ph = isset($d['harian'][$dd]) && $d['harian'][$dd]['denominator']>0
                            ? round($d['harian'][$dd]['numerator']/$d['harian'][$dd]['denominator']*100,1) : null;
                        ?>
                        <td class="day-cell <?= $ph!==null?($ph>=$d['standar']?'pct-ok':'pct-fail'):'' ?>">
                          <?= $ph!==null ? $ph.'%' : '-' ?>
                        </td>
                        <?php endfor; ?>
                        <td class="total-cell" style="font-size:10px"><?= number_format($d['persen'],2) ?>%</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <?php endforeach; ?>

            <?php else: ?>
              <div class="alert alert-info text-center">
                <i class="fas fa-info-circle"></i> Tidak ada data indikator untuk periode dan filter yang dipilih.
              </div>
            <?php endif; ?>

          </div>
        </div>

        </div>
      </section>
    </div>
  </div>
</div>

<!-- MODAL GRAFIK - di luar main-wrapper, setelah semua library dimuat -->
<?php 
$chartIndex = 0;
foreach($allData as $i => $d):
    $chartIndex++;
    $chartLabels = [];
    $chartData   = [];
    for($dd=1;$dd<=$jumlahHari;$dd++){
        $chartLabels[] = $dd;
        $ph = isset($d['harian'][$dd]) && $d['harian'][$dd]['denominator']>0
            ? round($d['harian'][$dd]['numerator']/$d['harian'][$dd]['denominator']*100,2) : 0;
        $chartData[] = $ph;
    }
?>
<div class="modal fade" id="chartModal<?= $chartIndex ?>" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width:95%">
    <div class="modal-content" style="height:90vh">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">
          <i class="fas fa-chart-bar"></i> Grafik Capaian Harian – <?= htmlspecialchars($d['nama']) ?>
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" style="height:calc(100% - 115px)">
        <div class="alert alert-info py-2">
          <div class="row">
            <div class="col-md-4"><strong>Periode:</strong> <?= $namaBulan[$bulan].' '.$tahun ?></div>
            <div class="col-md-4"><strong>Standar:</strong> <?= $d['standar'] ?>%</div>
            <div class="col-md-4"><strong>Capaian:</strong>
              <span class="badge badge-<?= $d['tercapai']?'success':'danger' ?>"><?= number_format($d['persen'],2) ?>%</span>
            </div>
          </div>
        </div>
        <canvas id="myChart<?= $chartIndex ?>" style="width:100%;height:100%"></canvas>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script src="assets/modules/jquery.min.js"></script>
<script src="assets/modules/popper.js"></script>
<script src="assets/modules/bootstrap/js/bootstrap.min.js"></script>
<script src="assets/modules/nicescroll/jquery.nicescroll.min.js"></script>
<script src="assets/modules/moment.min.js"></script>
<script src="assets/js/stisla.js"></script>
<script src="assets/js/scripts.js"></script>
<script src="assets/js/custom.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
(function(){
  var chartLabels = <?= json_encode($chartLabels) ?>;
  var chartData   = <?= json_encode($chartData) ?>;
  var standar     = <?= $d['standar'] ?>;
  var myChart;

  $('#chartModal<?= $chartIndex ?>').on('shown.bs.modal', function(){
    var ctx = document.getElementById('myChart<?= $chartIndex ?>');
    if(myChart) myChart.destroy();
    myChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: chartLabels,
        datasets: [{
          label: 'Capaian Harian (%)',
          data: chartData,
          backgroundColor: chartData.map(function(v){ return v>=standar?'rgba(40,167,69,.8)':'rgba(220,53,69,.7)'; }),
          borderColor:     chartData.map(function(v){ return v>=standar?'rgba(40,167,69,1)':'rgba(220,53,69,1)'; }),
          borderWidth: 2, borderRadius: 4
        },{
          label: 'Standar ('+standar+'%)',
          data: Array(chartLabels.length).fill(standar),
          type: 'line',
          borderColor: 'rgba(255,193,7,1)',
          backgroundColor: 'transparent',
          borderWidth: 3,
          borderDash: [8,4],
          pointRadius: 0
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: true, position: 'top' },
          tooltip: {
            callbacks: {
              title: function(c){ return 'Tanggal '+c[0].label+' <?= $namaBulan[$bulan].' '.$tahun ?>'; },
              label: function(c){
                var v = c.parsed.y;
                var l = c.dataset.label+': '+v.toFixed(2)+'%';
                if(c.datasetIndex===0) l += v>=standar?' \u2713 Tercapai':' \u2717 Tidak Tercapai';
                return l;
              }
            }
          }
        },
        scales: {
          x: { title: { display:true, text:'Tanggal' } },
          y: { beginAtZero:true, max:100,
               title: { display:true, text:'Persentase (%)' },
               ticks: { callback: function(v){ return v+'%'; } } }
        }
      }
    });
  });
  $('#chartModal<?= $chartIndex ?>').on('hidden.bs.modal', function(){
    if(myChart){ myChart.destroy(); myChart=null; }
  });
})();
</script>


<script>
$(document).ready(function(){
  setTimeout(function(){ $('#flashMsg').fadeOut('slow'); }, 3000);
});

function exportExcel(){
  var periode  = '<?= $namaBulan[$bulan].'_'.$tahun ?>';
  var filename = 'Capaian_IMUT_' + periode + '.xls';
  var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office">';
  html += '<head><meta charset="UTF-8"></head><body>';
  html += '<h2>CAPAIAN INDIKATOR MUTU – PMKP</h2>';
  html += '<p>Periode: <?= $namaBulan[$bulan].' '.$tahun ?></p><br>';
  document.querySelectorAll('.table-rekap').forEach(function(t){
    html += t.outerHTML + '<br><br>';
  });
  html += '</body></html>';
  var blob = new Blob([html], { type: 'application/vnd.ms-excel' });
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = filename;
  a.click();
}
</script>
</body>
</html>