<?php
// rekap_laporan_input_harian.php
include 'security.php';
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'] ?? 0;
$nama_user = '';
if ($user_id > 0) {
    $qUser = mysqli_query($conn, "SELECT nama FROM users WHERE id = '".intval($user_id)."' LIMIT 1");
    if ($qUser && $rowU = mysqli_fetch_assoc($qUser)) {
        $nama_user = $rowU['nama'];
    }
}

// akses menu
$current_file = basename(__FILE__);
$rAkses = mysqli_query($conn, "SELECT 1 FROM akses_menu 
            JOIN menu ON akses_menu.menu_id = menu.id 
            WHERE akses_menu.user_id = '".intval($user_id)."' 
            AND menu.file_menu = '".mysqli_real_escape_string($conn,$current_file)."'");
if (!$rAkses || mysqli_num_rows($rAkses) == 0) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini.'); window.location.href='dashboard.php';</script>";
    exit;
}

// Filter
$bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');
$jenis_indikator = isset($_GET['jenis']) ? mysqli_real_escape_string($conn, $_GET['jenis']) : '';
$id_indikator = isset($_GET['indikator']) ? intval($_GET['indikator']) : 0;

// Proses Validasi
if (isset($_POST['validasi'])) {
    $jenis = mysqli_real_escape_string($conn, $_POST['jenis_validasi']);
    $id_ind = intval($_POST['id_indikator_validasi']);
    $bulan_val = intval($_POST['bulan_validasi']);
    $tahun_val = intval($_POST['tahun_validasi']);
    $status = mysqli_real_escape_string($conn, $_POST['status_validasi']);
    $catatan = mysqli_real_escape_string($conn, $_POST['catatan_validasi']);
    
    // Update semua data untuk indikator ini di bulan yang sama
    $q = "UPDATE indikator_harian SET 
          status_validasi='$status',
          validasi_oleh='$nama_user',
          validasi_tanggal=NOW(),
          catatan_validasi='$catatan'
          WHERE jenis_indikator='$jenis' 
          AND id_indikator='$id_ind'
          AND MONTH(tanggal)='$bulan_val' 
          AND YEAR(tanggal)='$tahun_val'";
    
    if (mysqli_query($conn, $q)) {
        $_SESSION['flash_message'] = "Validasi berhasil disimpan untuk semua data bulan ini.";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Gagal validasi: " . mysqli_error($conn);
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: rekap_laporan_input_harian.php?bulan=$bulan&tahun=$tahun&jenis=$jenis_indikator&indikator=$id_indikator");
    exit;
}

// Ambil daftar indikator untuk filter
$listNasional = mysqli_query($conn, "SELECT id_nasional AS id, nama_indikator, penanggung_jawab FROM indikator_nasional ORDER BY nama_indikator");
$listRS = mysqli_query($conn, "SELECT id_rs AS id, nama_indikator, penanggung_jawab FROM indikator_rs ORDER BY nama_indikator");
$listUnit = mysqli_query($conn, "SELECT id_unit AS id, nama_indikator, penanggung_jawab FROM indikator_unit ORDER BY nama_indikator");

$indikatorList = [];
while($row = mysqli_fetch_assoc($listNasional)) $indikatorList['nasional'][] = $row;
while($row = mysqli_fetch_assoc($listRS)) $indikatorList['rs'][] = $row;
while($row = mysqli_fetch_assoc($listUnit)) $indikatorList['unit'][] = $row;

// Jumlah hari dalam bulan
$jumlahHari = date('t', mktime(0, 0, 0, $bulan, 1, $tahun));

// Query data berdasarkan filter
$whereClause = "WHERE MONTH(h.tanggal) = $bulan AND YEAR(h.tanggal) = $tahun";
if ($jenis_indikator) {
    $whereClause .= " AND h.jenis_indikator = '$jenis_indikator'";
}
if ($id_indikator > 0) {
    $whereClause .= " AND h.id_indikator = $id_indikator";
}

$modals = [];
$chartDataArray = []; // Array untuk menyimpan data chart
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Rekap Laporan Input Harian</title>
  <link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <style>
    .table-rekap { font-size: 11px; }
    .table-rekap th, .table-rekap td { 
      padding: 4px; 
      text-align: center; 
      vertical-align: middle;
      border: 1px solid #dee2e6;
    }
    .table-rekap th { background: #f8f9fa; font-weight: 600; }
    .table-rekap .indikator-name { 
      text-align: left; 
      font-weight: 500;
      min-width: 250px;
      max-width: 350px;
    }
    .flash-center {
      position: fixed; top: 20%; left: 50%; transform: translate(-50%, -50%);
      z-index: 9999; min-width: 300px; max-width: 90%; text-align: center;
      padding: 15px; border-radius: 8px; font-weight: 500;
      box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }
    .badge-validasi { font-size: 10px; padding: 3px 6px; }
    .filter-card { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
    .btn-validasi { padding: 2px 8px; font-size: 11px; }
    .day-cell { min-width: 30px; }
    .total-cell { background: #e9ecef; font-weight: 700; }
    .formula-text { font-size: 10px; color: #000; font-style: italic; margin-bottom: 10px; }
    .validasi-info { 
      background: #d4edda; 
      padding: 8px; 
      border-radius: 5px; 
      margin-top: 5px;
      font-size: 11px;
    }
    .validasi-info.ditolak { background: #f8d7da; }
    
    /* Chart Modal */
    .chart-container {
      position: relative;
      height: 400px;
      width: 100%;
    }
    
    /* Print Styles */
    @media print {
      .main-sidebar, .navbar, .card-header-action, .filter-card, .btn, .no-print {
        display: none !important;
      }
      .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
      }
      .card {
        border: none !important;
        box-shadow: none !important;
      }
      .table-rekap {
        font-size: 9px;
      }
      .table-rekap th, .table-rekap td {
        padding: 2px;
      }
      body {
        background: white !important;
      }
      .page-break {
        page-break-after: always;
      }
      @page {
        size: A4 landscape;
        margin: 10mm;
      }
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

        <?php if(isset($_SESSION['flash_message'])): 
            $flashType = $_SESSION['flash_type'] ?? 'info';
        ?>
          <div class="alert alert-<?= $flashType ?> flash-center" id="flashMsg">
            <i class="fas fa-<?= $flashType=='success'?'check-circle':($flashType=='danger'?'exclamation-circle':'info-circle') ?>"></i>
            <?= htmlspecialchars($_SESSION['flash_message']); 
                unset($_SESSION['flash_message']); 
                unset($_SESSION['flash_type']); 
            ?>
          </div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header">
            <h4><i class="fas fa-file-alt"></i> Rekap Laporan Input Harian</h4>
            <div class="card-header-action">
              <button class="btn btn-success" onclick="exportExcel()">
                <i class="fas fa-file-excel"></i> Export Excel
              </button>
              <button class="btn btn-primary" onclick="printLaporan()">
                <i class="fas fa-print"></i> Cetak Laporan
              </button>
            </div>
          </div>
          <div class="card-body">
            
            <!-- Filter -->
            <div class="filter-card">
              <form method="GET" id="filterForm">
                <div class="row">
                  <div class="col-md-2">
                    <div class="form-group mb-2">
                      <label><i class="fas fa-calendar"></i> Bulan</label>
                      <select name="bulan" class="form-control" onchange="this.form.submit()">
                        <?php for($m=1; $m<=12; $m++): ?>
                          <option value="<?= $m ?>" <?= ($bulan==$m)?'selected':'' ?>>
                            <?= date('F', mktime(0,0,0,$m,1)) ?>
                          </option>
                        <?php endfor; ?>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <div class="form-group mb-2">
                      <label><i class="fas fa-calendar-alt"></i> Tahun</label>
                      <select name="tahun" class="form-control" onchange="this.form.submit()">
                        <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                          <option value="<?= $y ?>" <?= ($tahun==$y)?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-group mb-2">
                      <label><i class="fas fa-layer-group"></i> Jenis Indikator</label>
                      <select name="jenis" id="jenisFilter" class="form-control" onchange="updateIndikatorFilter()">
                        <option value="">-- Semua Jenis --</option>
                        <option value="nasional" <?= ($jenis_indikator=='nasional')?'selected':'' ?>>Indikator Nasional</option>
                        <option value="rs" <?= ($jenis_indikator=='rs')?'selected':'' ?>>Indikator RS</option>
                        <option value="unit" <?= ($jenis_indikator=='unit')?'selected':'' ?>>Indikator Unit</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-group mb-2">
                      <label><i class="fas fa-list"></i> Indikator</label>
                      <select name="indikator" id="indikatorFilter" class="form-control select2">
                        <option value="">-- Semua Indikator --</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <div class="form-group mb-2">
                      <label>&nbsp;</label>
                      <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-search"></i> Filter
                      </button>
                    </div>
                  </div>
                </div>
              </form>
            </div>

            <!-- Tabel Rekap -->
            <?php
            // Query untuk mendapatkan indikator yang ada datanya
            $qIndikator = mysqli_query($conn, "SELECT DISTINCT h.jenis_indikator, h.id_indikator
                                               FROM indikator_harian h
                                               $whereClause
                                               ORDER BY h.jenis_indikator, h.id_indikator");
            
            if (mysqli_num_rows($qIndikator) > 0):
              $chartIndex = 0;
              while($indRow = mysqli_fetch_assoc($qIndikator)):
                $jenis = $indRow['jenis_indikator'];
                $idInd = $indRow['id_indikator'];
                $chartIndex++;
                
                // Ambil data indikator dan penanggung jawab
                $namaIndikator = '';
                $penanggungJawab = '';
                $standar = 0;
                $numeratorDef = '';
                $denominatorDef = '';
                
                if ($jenis == 'nasional') {
                    $qInfo = mysqli_query($conn, "SELECT n.nama_indikator, n.standar, n.numerator, n.denominator, n.penanggung_jawab 
                                                   FROM indikator_nasional n
                                                   WHERE n.id_nasional = $idInd");
                } elseif ($jenis == 'rs') {
                    $qInfo = mysqli_query($conn, "SELECT r.nama_indikator, r.standar, r.numerator, r.denominator, u.nama as penanggung_jawab 
                                                   FROM indikator_rs r
                                                   LEFT JOIN users u ON r.penanggung_jawab = u.id
                                                   WHERE r.id_rs = $idInd");
                } else {
                    $qInfo = mysqli_query($conn, "SELECT i.nama_indikator, i.standar, i.numerator, i.denominator, u.nama as penanggung_jawab 
                                                   FROM indikator_unit i
                                                   LEFT JOIN users u ON i.penanggung_jawab = u.id
                                                   WHERE i.id_unit = $idInd");
                }
                
                if ($qInfo && $infoRow = mysqli_fetch_assoc($qInfo)) {
                    $namaIndikator = $infoRow['nama_indikator'];
                    $penanggungJawab = $infoRow['penanggung_jawab'] ?? '-';
                    $standar = $infoRow['standar'];
                    $numeratorDef = $infoRow['numerator'] ?? '';
                    $denominatorDef = $infoRow['denominator'] ?? '';
                }
                
                // Cek apakah user adalah penanggung jawab
                $isPeranggungJawab = ($nama_user == $penanggungJawab);
                
                // Query data harian untuk indikator ini
                $qData = mysqli_query($conn, "SELECT h.*, DAY(h.tanggal) as hari
                                              FROM indikator_harian h
                                              WHERE h.jenis_indikator = '$jenis' 
                                              AND h.id_indikator = $idInd
                                              AND MONTH(h.tanggal) = $bulan 
                                              AND YEAR(h.tanggal) = $tahun
                                              ORDER BY h.tanggal");
                
                $dataHarian = [];
                $totalNumerator = 0;
                $totalDenominator = 0;
                $statusValidasi = '';
                $catatanValidasi = '';
                $validasiOleh = '';
                $validasiTanggal = '';
                
                // Array untuk grafik
                $chartLabels = [];
                $chartData = [];
                
                // Inisialisasi semua tanggal dengan 0
                for ($d = 1; $d <= $jumlahHari; $d++) {
                    $chartLabels[] = $d;
                    $chartData[] = 0;
                }
                
                while($dataRow = mysqli_fetch_assoc($qData)) {
                    $hari = $dataRow['hari'];
                    $dataHarian[$hari] = $dataRow;
                    $totalNumerator += $dataRow['numerator'];
                    $totalDenominator += $dataRow['denominator'];
                    
                    // Data untuk grafik - hitung persentase per hari
                    $persen = ($dataRow['denominator'] > 0) ? ($dataRow['numerator'] / $dataRow['denominator'] * 100) : 0;
                    $chartData[$hari - 1] = round($persen, 2); // Array index starts from 0
                    
                    // Status validasi
                    if($dataRow['status_validasi']) {
                        $statusValidasi = $dataRow['status_validasi'];
                        $catatanValidasi = $dataRow['catatan_validasi'];
                        $validasiOleh = $dataRow['validasi_oleh'];
                        $validasiTanggal = $dataRow['validasi_tanggal'];
                    }
                }
                
                $persentaseTotal = ($totalDenominator > 0) ? ($totalNumerator / $totalDenominator * 100) : 0;
                ?>
                
                <div class="mb-4">
                  <h6 class="mb-2">
                    <span class="badge badge-primary"><?= strtoupper($jenis) ?></span>
                    <?= htmlspecialchars($namaIndikator) ?>
                    <small class="text-muted">(Penanggung Jawab: <?= htmlspecialchars($penanggungJawab) ?>)</small>
                  </h6>
                  <div class="formula-text">
                    <strong>Numerator:</strong> <?= htmlspecialchars($numeratorDef) ?> | 
                    <strong>Denominator:</strong> <?= htmlspecialchars($denominatorDef) ?> | 
                    <strong>Standar:</strong> <?= $standar ?>%
                  </div>
                  
                  <div class="table-responsive">
                    <table class="table table-bordered table-rekap table-sm">
                      <thead>
                        <tr>
                          <th rowspan="2" class="indikator-name">Indikator Mutu</th>
                          <?php for($d=1; $d<=$jumlahHari; $d++): ?>
                            <th class="day-cell"><?= $d ?></th>
                          <?php endfor; ?>
                          <th rowspan="2" class="total-cell">Total</th>
                          <th rowspan="2">Capaian</th>
                          <th rowspan="2" width="200">Validasi</th>
                          <th rowspan="2" width="80">Grafik</th>
                        </tr>
                      </thead>
                      <tbody>
                        <!-- Row Numerator -->
                        <tr>
                          <td class="indikator-name">Numerator</td>
                          <?php for($d=1; $d<=$jumlahHari; $d++): ?>
                            <td><?= isset($dataHarian[$d]) ? $dataHarian[$d]['numerator'] : '-' ?></td>
                          <?php endfor; ?>
                          <td class="total-cell"><?= $totalNumerator ?></td>
                          <td rowspan="2" class="total-cell">
                            <strong><?= number_format($persentaseTotal, 2) ?>%</strong>
                            <?php if($persentaseTotal >= $standar): ?>
                              <br><span class="badge badge-success">Tercapai</span>
                            <?php else: ?>
                              <br><span class="badge badge-danger">Tidak Tercapai</span>
                            <?php endif; ?>
                          </td>
                          <td rowspan="2">
                            <?php if($statusValidasi == 'tervalidasi'): ?>
                              <div class="validasi-info">
                                <i class="fas fa-check-circle text-success"></i> <strong>TERVALIDASI</strong>
                                <br><small>Oleh: <?= htmlspecialchars($validasiOleh) ?></small>
                                <br><small>Tanggal: <?= date('d/m/Y H:i', strtotime($validasiTanggal)) ?></small>
                                <?php if($catatanValidasi): ?>
                                  <br><small>Catatan: <?= htmlspecialchars($catatanValidasi) ?></small>
                                <?php endif; ?>
                              </div>
                            <?php elseif($statusValidasi == 'ditolak'): ?>
                              <div class="validasi-info ditolak">
                                <i class="fas fa-times-circle text-danger"></i> <strong>DITOLAK</strong>
                                <br><small>Oleh: <?= htmlspecialchars($validasiOleh) ?></small>
                                <br><small>Tanggal: <?= date('d/m/Y H:i', strtotime($validasiTanggal)) ?></small>
                                <?php if($catatanValidasi): ?>
                                  <br><small>Alasan: <?= htmlspecialchars($catatanValidasi) ?></small>
                                <?php endif; ?>
                              </div>
                            <?php elseif($isPeranggungJawab): ?>
                              <button class="btn btn-warning btn-validasi" data-toggle="modal" data-target="#validasiModal<?= $jenis ?>_<?= $idInd ?>">
                                <i class="fas fa-check-double"></i> Validasi Data
                              </button>
                              <br><small class="text-muted">Belum divalidasi</small>
                            <?php else: ?>
                              <div class="text-center">
                                <i class="fas fa-hourglass-half text-warning"></i>
                                <br><small class="text-muted">Belum divalidasi</small>
                              </div>
                            <?php endif; ?>
                          </td>
                          <td rowspan="2" class="text-center">
                            <button class="btn btn-info btn-sm" 
                                    data-toggle="modal" 
                                    data-target="#chartModal<?= $chartIndex ?>"
                                    title="Lihat Grafik">
                              <i class="fas fa-chart-line"></i>
                            </button>
                          </td>
                        </tr>
                        <!-- Row Denominator -->
                        <tr>
                          <td class="indikator-name">Denominator</td>
                          <?php for($d=1; $d<=$jumlahHari; $d++): ?>
                            <td><?= isset($dataHarian[$d]) ? $dataHarian[$d]['denominator'] : '-' ?></td>
                          <?php endfor; ?>
                          <td class="total-cell"><?= $totalDenominator ?></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                
                <?php 
                // Modal Validasi (hanya untuk penanggung jawab)
                if($isPeranggungJawab && !$statusValidasi):
                  ob_start(); 
                  ?>
                  <div class="modal fade" id="validasiModal<?= $jenis ?>_<?= $idInd ?>" tabindex="-1">
                    <div class="modal-dialog">
                      <div class="modal-content">
                        <form method="POST">
                          <div class="modal-header bg-warning text-white">
                            <h5 class="modal-title"><i class="fas fa-check-double"></i> Validasi Data Indikator</h5>
                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                          </div>
                          <div class="modal-body">
                            <input type="hidden" name="jenis_validasi" value="<?= $jenis ?>">
                            <input type="hidden" name="id_indikator_validasi" value="<?= $idInd ?>">
                            <input type="hidden" name="bulan_validasi" value="<?= $bulan ?>">
                            <input type="hidden" name="tahun_validasi" value="<?= $tahun ?>">
                            
                            <div class="alert alert-info">
                              <i class="fas fa-info-circle"></i> Validasi akan diterapkan untuk <strong>semua data</strong> indikator ini di bulan <?= date('F Y', mktime(0,0,0,$bulan,1,$tahun)) ?>
                            </div>
                            
                            <div class="form-group">
                              <label><strong>Indikator:</strong></label>
                              <p class="font-weight-bold"><?= htmlspecialchars($namaIndikator) ?></p>
                            </div>
                            <div class="row">
                              <div class="col-md-6">
                                <div class="form-group">
                                  <label><strong>Total Numerator:</strong></label>
                                  <p class="text-primary font-weight-bold"><?= $totalNumerator ?></p>
                                </div>
                              </div>
                              <div class="col-md-6">
                                <div class="form-group">
                                  <label><strong>Total Denominator:</strong></label>
                                  <p class="text-primary font-weight-bold"><?= $totalDenominator ?></p>
                                </div>
                              </div>
                            </div>
                            <div class="form-group">
                              <label><strong>Capaian:</strong></label>
                              <p class="font-weight-bold text-<?= ($persentaseTotal >= $standar) ? 'success' : 'danger' ?>">
                                <?= number_format($persentaseTotal, 2) ?>% 
                                <?php if($persentaseTotal >= $standar): ?>
                                  <span class="badge badge-success">Tercapai (Standar: <?= $standar ?>%)</span>
                                <?php else: ?>
                                  <span class="badge badge-danger">Tidak Tercapai (Standar: <?= $standar ?>%)</span>
                                <?php endif; ?>
                              </p>
                            </div>
                            <hr>
                            <div class="form-group">
                              <label>Status Validasi <span class="text-danger">*</span></label>
                              <select name="status_validasi" class="form-control" required>
                                <option value="">-- Pilih Status --</option>
                                <option value="tervalidasi">✓ Tervalidasi (Data Benar)</option>
                                <option value="ditolak">✗ Ditolak (Data Perlu Perbaikan)</option>
                              </select>
                            </div>
                            <div class="form-group">
                              <label>Catatan/Keterangan</label>
                              <textarea name="catatan_validasi" class="form-control" rows="3" placeholder="Masukkan catatan validasi atau alasan penolakan (opsional)"></textarea>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="submit" name="validasi" class="btn btn-primary">
                              <i class="fas fa-save"></i> Simpan Validasi
                            </button>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                              <i class="fas fa-times"></i> Batal
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                  <?php 
                  $modals[] = ob_get_clean();
                endif;
                
                // Modal Grafik (HTML only, no script)
                ob_start();
                ?>
                <div class="modal fade" id="chartModal<?= $chartIndex ?>" tabindex="-1" role="dialog">
                  <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width:95%;">
                    <div class="modal-content" style="height:90vh;">
                      <div class="modal-header bg-info text-white">
                        <h5 class="modal-title">
                          <i class="fas fa-chart-line"></i> Grafik Persentase Harian - <?= htmlspecialchars($namaIndikator) ?>
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal">
                          <span aria-hidden="true">&times;</span>
                        </button>
                      </div>
                      <div class="modal-body" style="height:calc(100% - 120px);">
                        <div class="alert alert-info">
                          <div class="row">
                            <div class="col-md-4">
                              <strong>Periode:</strong> <?= date('F Y', mktime(0,0,0,$bulan,1,$tahun)) ?>
                            </div>
                            <div class="col-md-4">
                              <strong>Standar:</strong> <?= $standar ?>%
                            </div>
                            <div class="col-md-4">
                              <strong>Capaian Rata-rata:</strong> 
                              <span class="badge badge-<?= ($persentaseTotal >= $standar) ? 'success' : 'danger' ?>">
                                <?= number_format($persentaseTotal, 2) ?>%
                              </span>
                            </div>
                          </div>
                        </div>
                        <canvas id="chart<?= $chartIndex ?>" style="width:100%; height:100%;"></canvas>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                      </div>
                    </div>
                  </div>
                </div>
                <?php 
                $modals[] = ob_get_clean();
                
                // Simpan data chart untuk di-render nanti
                $chartDataArray[] = [
                  'index' => $chartIndex,
                  'labels' => $chartLabels,
                  'data' => $chartData,
                  'standar' => $standar
                ];
                ?>
                
              <?php endwhile;
            else: ?>
              <div class="alert alert-info text-center">
                <i class="fas fa-info-circle"></i> Tidak ada data untuk periode dan filter yang dipilih.
              </div>
            <?php endif; ?>

          </div>
        </div>

        </div>
      </section>
    </div>
  </div>
</div>

<script src="assets/modules/jquery.min.js"></script>
<script src="assets/modules/popper.js"></script>
<script src="assets/modules/bootstrap/js/bootstrap.min.js"></script>
<script src="assets/modules/nicescroll/jquery.nicescroll.min.js"></script>
<script src="assets/modules/moment.min.js"></script>
<script src="assets/js/stisla.js"></script>
<script src="assets/js/scripts.js"></script>
<script src="assets/js/custom.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function(){
  setTimeout(function(){ $("#flashMsg").fadeOut("slow"); }, 3000);
  
  $('.select2').select2({
    placeholder: "-- Semua Indikator --",
    allowClear: true,
    width: '100%'
  });
  
  var indikatorData = <?= json_encode($indikatorList) ?>;
  
  function updateIndikatorFilter() {
    var jenis = $('#jenisFilter').val();
    var $indFilter = $('#indikatorFilter');
    var currentValue = $indFilter.val();
    
    $indFilter.empty().append('<option value="">-- Semua Indikator --</option>');
    
    if(jenis && indikatorData[jenis]) {
      indikatorData[jenis].forEach(function(opt) {
        var selected = (opt.id == currentValue) ? 'selected' : '';
        $indFilter.append('<option value="'+opt.id+'" '+selected+'>'+opt.nama_indikator+'</option>');
      });
    }
    
    $indFilter.trigger('change');
  }
  
  // Initialize on load
  updateIndikatorFilter();
  
  // Set selected value if exists
  <?php if($id_indikator > 0): ?>
  $('#indikatorFilter').val(<?= $id_indikator ?>).trigger('change');
  <?php endif; ?>
});

// Fungsi Print
function printLaporan() {
  window.print();
}

// Fungsi Export Excel
function exportExcel() {
  var periode = "<?= date('F_Y', mktime(0,0,0,$bulan,1,$tahun)) ?>";
  var filename = "Rekap_Laporan_Harian_" + periode + ".xls";
  
  var tables = document.querySelectorAll('.table-rekap');
  var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
  html += '<head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Laporan</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
  html += '<body>';
  html += '<h2>REKAP LAPORAN INPUT HARIAN</h2>';
  html += '<p>Periode: <?= date('F Y', mktime(0,0,0,$bulan,1,$tahun)) ?></p>';
  html += '<br>';
  
  tables.forEach(function(table) {
    html += table.outerHTML;
    html += '<br><br>';
  });
  
  html += '</body></html>';
  
  var blob = new Blob([html], {
    type: 'application/vnd.ms-excel'
  });
  
  var link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  link.click();
}

window.updateIndikatorFilter = function() {
  var jenis = $('#jenisFilter').val();
  var $indFilter = $('#indikatorFilter');
  var indikatorData = <?= json_encode($indikatorList) ?>;
  
  $indFilter.empty().append('<option value="">-- Semua Indikator --</option>');
  
  if(jenis && indikatorData[jenis]) {
    indikatorData[jenis].forEach(function(opt) {
      $indFilter.append('<option value="'+opt.id+'">'+opt.nama_indikator+'</option>');
    });
  }
  
  $indFilter.trigger('change');
};
</script>

<?php
foreach ($modals as $m) echo $m;
?>

<!-- Chart Scripts - Loaded AFTER all modals -->
<script>
$(document).ready(function() {
  // Object untuk menyimpan chart instances
  const chartInstances = {};
  
  // Data untuk semua charts
  const chartDataCollection = <?= json_encode($chartDataArray) ?>;
  
  console.log('Total charts to initialize:', chartDataCollection.length);
  console.log('Chart data:', chartDataCollection);
  
  // Setup event handler untuk setiap modal
  chartDataCollection.forEach(function(chartInfo) {
    const chartIndex = chartInfo.index;
    const modalId = '#chartModal' + chartIndex;
    const canvasId = 'chart' + chartIndex;
    
    console.log('Setting up chart ' + chartIndex);
    
    // Event saat modal dibuka
    $(modalId).on('shown.bs.modal', function () {
      console.log('Modal opened for chart ' + chartIndex);
      
      const canvas = document.getElementById(canvasId);
      if (!canvas) {
        console.error('Canvas not found:', canvasId);
        return;
      }
      
      const ctx = canvas.getContext('2d');
      const labels = chartInfo.labels;
      const data = chartInfo.data;
      const standar = chartInfo.standar;
      
      console.log('Chart ' + chartIndex + ' - Labels:', labels);
      console.log('Chart ' + chartIndex + ' - Data:', data);
      console.log('Chart ' + chartIndex + ' - Standar:', standar);
      
      // Destroy existing chart if any
      if (chartInstances[chartIndex]) {
        chartInstances[chartIndex].destroy();
      }
      
      // Create standard line
      const standardLine = Array(labels.length).fill(standar);
      
      // Create new chart
      chartInstances[chartIndex] = new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: 'Persentase Harian (%)',
            data: data,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.4,
            fill: true,
            borderWidth: 3,
            pointBackgroundColor: 'rgb(75, 192, 192)',
            pointBorderColor: '#fff',
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBorderWidth: 2
          }, {
            label: 'Target Standar (' + standar + '%)',
            data: standardLine,
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'transparent',
            tension: 0,
            fill: false,
            borderWidth: 2,
            borderDash: [10, 5],
            pointRadius: 0
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: {
            duration: 800
          },
          interaction: {
            mode: 'index',
            intersect: false
          },
          plugins: {
            legend: {
              display: true,
              position: 'top'
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  let label = context.dataset.label || '';
                  if (label) {
                    label += ': ';
                  }
                  label += context.parsed.y.toFixed(2) + '%';
                  
                  if (context.datasetIndex === 0 && context.parsed.y > 0) {
                    if (context.parsed.y >= standar) {
                      label += ' ✓ Tercapai';
                    } else {
                      label += ' ✗ Tidak Tercapai';
                    }
                  }
                  
                  return label;
                }
              }
            },
            title: {
              display: true,
              text: 'Grafik Persentase Capaian Harian'
            }
          },
          scales: {
            x: {
              ticks: {
                autoSkip: false,
                maxRotation: 0,
                minRotation: 0,
                font: { size: 11 }
              },
              title: {
                display: true,
                text: 'Tanggal'
              },
              grid: {
                display: false
              }
            },
            y: {
              beginAtZero: true,
              max: 100,
              title: {
                display: true,
                text: 'Persentase (%)'
              },
              grid: {
                color: 'rgba(0,0,0,0.05)'
              },
              ticks: {
                callback: function(value) {
                  return value + '%';
                }
              }
            }
          }
        }
      });
      
      console.log('Chart ' + chartIndex + ' created successfully!');
    });
    
    // Event saat modal ditutup
    $(modalId).on('hidden.bs.modal', function () {
      if (chartInstances[chartIndex]) {
        chartInstances[chartIndex].destroy();
        delete chartInstances[chartIndex];
        console.log('Chart ' + chartIndex + ' destroyed');
      }
    });
  });
});
</script>

</body>
</html>