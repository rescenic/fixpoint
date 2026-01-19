<?php
include 'security.php'; 
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'];

$current_file = basename(__FILE__);

// Cek apakses user boleh mengakses halaman ini
$query = "SELECT 1 FROM akses_menu 
          JOIN menu ON akses_menu.menu_id = menu.id 
          WHERE akses_menu.user_id = '$user_id' AND menu.file_menu = '$current_file'";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) == 0) {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini.'); window.location.href='dashboard.php';</script>";
  exit;
}

// Proses Simpan
if (isset($_POST['simpan'])) {
  $user_id = $_SESSION['user_id'];
  $barang_id = $_POST['barang_id'];
  $catatan = mysqli_real_escape_string($conn, $_POST['catatan']);
  $kondisi_fisik = isset($_POST['kondisi_fisik']) ? implode(", ", $_POST['kondisi_fisik']) : '';
  $fungsi_perangkat = isset($_POST['fungsi_perangkat']) ? implode(", ", $_POST['fungsi_perangkat']) : '';

  // Ambil nama teknisi dari user
  $get_user = mysqli_query($conn, "SELECT nama FROM users WHERE id = '$user_id' LIMIT 1");
  $nama_teknisi = ($get_user && mysqli_num_rows($get_user) > 0) ? mysqli_fetch_assoc($get_user)['nama'] : 'Tidak Diketahui';

  $query = "INSERT INTO maintanance_rutin 
            (user_id, nama_teknisi, barang_id, kondisi_fisik, fungsi_perangkat, catatan, waktu_input)
            VALUES 
            ('$user_id', '$nama_teknisi', '$barang_id', '$kondisi_fisik', '$fungsi_perangkat', '$catatan', NOW())";

  if (mysqli_query($conn, $query)) {
    $_SESSION['flash_message'] = "Data maintenance berhasil disimpan.";
    $_SESSION['flash_type'] = "success";
    echo "<script>location.href='maintenance_rutin.php?tab=data';</script>";
    exit;
  } else {
    $error_message = mysqli_error($conn);
    $_SESSION['flash_message'] = "Gagal menyimpan data: $error_message";
    $_SESSION['flash_type'] = "danger";
  }
}



$data_barang = mysqli_query($conn, "SELECT * FROM data_barang_it ORDER BY nama_barang ASC");
$activeTab = $_GET['tab'] ?? 'form';
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Maintenance Rutin IT - F.I.X.P.O.I.N.T</title>
  <link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/components.css" />
  <style>
    .flash-center {
      position: fixed;
      top: 20%;
      left: 50%;
      transform: translate(-50%, -50%);
      z-index: 9999;
      min-width: 300px;
      max-width: 90%;
      text-align: center;
      padding: 15px;
      border-radius: 8px;
      font-weight: 500;
      box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }
    
    .table-maintenance {
      font-size: 13px;
      white-space: nowrap;
    }
    
    .table-maintenance thead th {
      background-color: #6777ef !important;
      color: #fff !important;
      font-weight: 600;
      vertical-align: middle;
      text-align: center;
      padding: 12px 8px;
      border: 1px solid #5568d3;
    }
    
    .table-maintenance tbody td {
      vertical-align: middle;
      padding: 10px 8px;
      border: 1px solid #dee2e6;
    }
    
    .table-maintenance tbody tr:hover {
      background-color: #f8f9fa;
    }

    .status-badge {
      font-size: 11px;
      padding: 5px 10px;
      border-radius: 12px;
      font-weight: 600;
      display: inline-block;
      min-width: 120px;
      text-align: center;
    }

    .status-aman {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .status-persiapan {
      background-color: #fff3cd;
      color: #856404;
      border: 1px solid #ffeaa7;
    }

    .status-wajib {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .filter-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      color: white;
    }

    .filter-card .form-control {
      border-radius: 5px;
    }

    .filter-card label {
      font-weight: 600;
      margin-bottom: 5px;
      color: white;
    }

    .btn-action {
      padding: 5px 10px;
      font-size: 12px;
      margin: 2px;
    }

    .info-box {
      background: #e3f2fd;
      border-left: 4px solid #2196f3;
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 5px;
    }

    .info-box i {
      font-size: 20px;
      margin-right: 10px;
      color: #2196f3;
    }

    .card-header h4 i {
      cursor: pointer;
      transition: all 0.3s;
    }

    .card-header h4 i:hover {
      transform: scale(1.2);
    }

    .pagination-wrapper {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 20px;
      padding: 10px 0;
    }

    .pagination-info {
      font-size: 14px;
      color: #000;
      font-weight: 500;
    }

    .form-check-label {
      font-size: 14px;
      margin-left: 5px;
    }

    .detail-list {
      list-style: none;
      padding-left: 0;
      margin: 0;
    }

    .detail-list li {
      padding: 3px 0;
      font-size: 12px;
    }

    .detail-list li:before {
      content: "✓ ";
      color: #28a745;
      font-weight: bold;
      margin-right: 5px;
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

          <?php if (isset($_SESSION['flash_message'])): 
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
              <h4>
                <i class="fas fa-tools"></i> Maintenance Rutin IT
                <i class="fas fa-question-circle text-info ml-2" style="cursor: pointer;" data-toggle="modal" data-target="#infoModal" title="Penjelasan Status"></i>
              </h4>
            </div>

            <div class="card-body">
              <ul class="nav nav-tabs" id="tabMenu" role="tablist">
                <li class="nav-item">
                  <a class="nav-link <?= ($activeTab=='form')?'active':'' ?>" id="form-tab" data-toggle="tab" href="#form" role="tab">
                    <i class="fas fa-edit"></i> Form Maintenance
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link <?= ($activeTab=='data')?'active':'' ?>" id="data-tab" data-toggle="tab" href="#data" role="tab">
                    <i class="fas fa-database"></i> Data Maintenance
                  </a>
                </li>
              </ul>

              <div class="tab-content pt-3">
                <!-- FORM TAB -->
                <div class="tab-pane fade <?= ($activeTab=='form')?'show active':'' ?>" id="form" role="tabpanel">
                  <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>Petunjuk:</strong> Isi form di bawah ini untuk mencatat aktivitas maintenance rutin perangkat IT. Pastikan semua data terisi dengan benar.
                  </div>

                  <form method="POST" id="formMaintenance">
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="barang_id"><i class="fas fa-laptop"></i> Pilih Barang <span class="text-danger">*</span></label>
                          <select name="barang_id" id="barang_id" class="form-control" required>
                            <option value="">-- Pilih Barang IT --</option>
                            <?php mysqli_data_seek($data_barang, 0); ?>
                            <?php while($row = mysqli_fetch_assoc($data_barang)): ?>
                              <option value="<?= $row['id'] ?>">
                                <?= htmlspecialchars($row['nama_barang']) ?> - <?= htmlspecialchars($row['lokasi']) ?>
                              </option>
                            <?php endwhile; ?>
                          </select>
                        </div>

                        <div class="form-group">
                          <label><i class="fas fa-check-circle"></i> Kondisi Fisik</label>
                          <div class="row">
                            <?php
                            $fisik = [
                              'Bodi Utuh' => 'Tidak ada kerusakan fisik', 
                              'Layar Jernih' => 'Display dalam kondisi baik', 
                              'Kabel Normal' => 'Kabel tidak rusak/terkelupas', 
                              'Port Tidak Rusak' => 'Semua port berfungsi', 
                              'Label Aset Jelas' => 'Label masih terbaca', 
                              'Tidak Ada Komponen Longgar' => 'Semua komponen terpasang kuat'
                            ];
                            foreach ($fisik as $key => $desc) {
                              echo "<div class='col-md-6 mb-2'>
                                      <div class='form-check'>
                                        <input class='form-check-input' type='checkbox' name='kondisi_fisik[]' value='$key' id='fisik_".str_replace(' ', '_', $key)."'>
                                        <label class='form-check-label' for='fisik_".str_replace(' ', '_', $key)."' title='$desc'>
                                          $key
                                        </label>
                                      </div>
                                    </div>";
                            }
                            ?>
                          </div>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="form-group">
                          <label><i class="fas fa-cog"></i> Fungsi Perangkat</label>
                          <div class="row">
                            <?php
                            $fungsi = [
                              'Booting Normal' => 'Sistem dapat menyala dengan baik', 
                              'Koneksi Stabil' => 'Jaringan/WiFi berfungsi normal', 
                              'Resolusi Oke' => 'Tampilan sesuai standar', 
                              'USB & Peripheral Terdeteksi' => 'Port berfungsi baik', 
                              'Performa Responsif' => 'Tidak lag/lemot', 
                              'Update OS dan Antivirus Tersedia' => 'Software up to date'
                            ];
                            foreach ($fungsi as $key => $desc) {
                              echo "<div class='col-md-6 mb-2'>
                                      <div class='form-check'>
                                        <input class='form-check-input' type='checkbox' name='fungsi_perangkat[]' value='$key' id='fungsi_".str_replace(' ', '_', $key)."'>
                                        <label class='form-check-label' for='fungsi_".str_replace(' ', '_', $key)."' title='$desc'>
                                          $key
                                        </label>
                                      </div>
                                    </div>";
                            }
                            ?>
                          </div>
                        </div>

                        <div class="form-group">
                          <label for="catatan"><i class="fas fa-comment-alt"></i> Catatan Teknisi</label>
                          <textarea name="catatan" id="catatan" class="form-control" rows="4" placeholder="Tambahkan catatan khusus jika ada masalah atau hal penting lainnya..."></textarea>
                          <small class="form-text text-muted">Opsional - Isi jika ada catatan tambahan</small>
                        </div>

                        <div class="form-group text-right">
                          <button type="submit" name="simpan" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Simpan Data Maintenance
                          </button>
                          <button type="reset" class="btn btn-secondary btn-lg">
                            <i class="fas fa-redo"></i> Reset Form
                          </button>
                        </div>
                      </div>
                    </div>
                  </form>
                </div>

                <!-- DATA TAB -->
                <div class="tab-pane fade <?= ($activeTab=='data')?'show active':'' ?>" id="data" role="tabpanel">
                  
                  <!-- Filter -->
                  <div class="filter-card">
                    <form method="GET" class="form-inline" id="filterForm">
                      <input type="hidden" name="tab" value="data">
                      <div class="form-group mr-3">
                        <label for="dari" class="mr-2"><i class="fas fa-calendar-alt"></i> Dari Tanggal</label>
                        <input type="date" id="dari" name="dari" class="form-control" value="<?= $_GET['dari'] ?? '' ?>" required>
                      </div>
                      <div class="form-group mr-3">
                        <label for="sampai" class="mr-2"><i class="fas fa-calendar-check"></i> Sampai Tanggal</label>
                        <input type="date" id="sampai" name="sampai" class="form-control" value="<?= $_GET['sampai'] ?? '' ?>" required>
                      </div>

                      <button type="submit" class="btn btn-light btn-sm mr-2">
                        <i class="fas fa-filter"></i> Filter
                      </button>

                      <?php if (!empty($_GET['dari']) && !empty($_GET['sampai'])): ?>
                        <a href="rekap_maintenance_rutin.php?dari=<?= urlencode($_GET['dari']) ?>&sampai=<?= urlencode($_GET['sampai']) ?>" 
                           target="_blank" class="btn btn-success btn-sm">
                          <i class="fas fa-print"></i> Cetak Rekap
                        </a>
                      <?php endif; ?>
                    </form>
                  </div>

                  <!-- Tabel Data -->
                  <div class="table-responsive">
                    <table class="table table-bordered table-hover table-maintenance">
                      <thead>
                        <tr>
                          <th style="width: 40px;">No</th>
                          <th style="width: 60px;">Kartu</th>
                          <th style="min-width: 150px;">Nama Barang</th>
                          <th style="min-width: 120px;">Lokasi</th>
                          <th style="min-width: 180px;">Kondisi Fisik</th>
                          <th style="min-width: 180px;">Fungsi Perangkat</th>
                          <th style="min-width: 150px;">Catatan</th>
                          <th style="min-width: 120px;">Teknisi</th>
                          <th style="min-width: 130px;">Waktu Maintenance</th>
                          <th style="min-width: 150px;">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $no = 1;
                        $where = "";
                        if (isset($_GET['dari'], $_GET['sampai']) && $_GET['dari'] && $_GET['sampai']) {
                          $dari = mysqli_real_escape_string($conn, $_GET['dari']);
                          $sampai = mysqli_real_escape_string($conn, $_GET['sampai']);
                          $where = "WHERE DATE(mr.waktu_input) BETWEEN '$dari' AND '$sampai'";
                        }

                        $limit = 10;
                        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
                        if ($page < 1) $page = 1;
                        $offset = ($page - 1) * $limit;

                        $total_query = mysqli_query($conn, "SELECT COUNT(*) as total 
                                                            FROM maintanance_rutin mr 
                                                            JOIN data_barang_it db ON mr.barang_id = db.id 
                                                            $where");
                        $total_data = mysqli_fetch_assoc($total_query)['total'];
                        $total_pages = ceil($total_data / $limit);

                        $query = mysqli_query($conn, "SELECT mr.*, db.nama_barang, db.lokasi, db.kategori 
                                                      FROM maintanance_rutin mr 
                                                      JOIN data_barang_it db ON mr.barang_id = db.id 
                                                      $where
                                                      ORDER BY mr.waktu_input DESC
                                                      LIMIT $limit OFFSET $offset");

                        if (mysqli_num_rows($query) > 0):
                          while ($row = mysqli_fetch_assoc($query)):
                            $waktu_input = strtotime($row['waktu_input']);
                            $now = time();
                            $selisih_bulan = floor(($now - $waktu_input) / (30 * 24 * 60 * 60));

                            if ($selisih_bulan < 1) {
                              $status_text = 'Aman';
                              $status_class = 'status-aman';
                              $status_icon = 'fa-check-circle';
                            } elseif ($selisih_bulan < 2) {
                              $status_text = 'Persiapkan Maintenance';
                              $status_class = 'status-persiapan';
                              $status_icon = 'fa-exclamation-triangle';
                            } else {
                              $status_text = 'Wajib Maintenance';
                              $status_class = 'status-wajib';
                              $status_icon = 'fa-times-circle';
                            }
                            
                            // Parse kondisi fisik dan fungsi perangkat
                            $kondisi_array = explode(', ', $row['kondisi_fisik']);
                            $fungsi_array = explode(', ', $row['fungsi_perangkat']);
                        ?>
                          <tr>
                            <td class="text-center"><?= $offset + $no++ ?></td>
                            <td class="text-center">
                              <a href="cetak_kartu_maintenance_it.php?id=<?= $row['id'] ?>" target="_blank" 
                                 class="btn btn-sm btn-info" title="Cetak Kartu Maintenance">
                                <i class="fas fa-id-card"></i>
                              </a>
                            </td>
                            <td>
                              <strong><?= htmlspecialchars($row['nama_barang']) ?></strong>
                              <br><small class="text-muted"><?= htmlspecialchars($row['kategori']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($row['lokasi']) ?></td>
                            <td>
                              <ul class="detail-list">
                                <?php foreach($kondisi_array as $kondisi): ?>
                                  <li><?= htmlspecialchars($kondisi) ?></li>
                                <?php endforeach; ?>
                              </ul>
                            </td>
                            <td>
                              <ul class="detail-list">
                                <?php foreach($fungsi_array as $fungsi): ?>
                                  <li><?= htmlspecialchars($fungsi) ?></li>
                                <?php endforeach; ?>
                              </ul>
                            </td>
                            <td><?= !empty($row['catatan']) ? htmlspecialchars($row['catatan']) : '<span class="text-muted">-</span>' ?></td>
                            <td><?= htmlspecialchars($row['nama_teknisi']) ?></td>
                            <td class="text-center">
                              <?= date('d/m/Y', strtotime($row['waktu_input'])) ?>
                              <br><small class="text-muted"><?= date('H:i', strtotime($row['waktu_input'])) ?> WIB</small>
                            </td>
                            <td class="text-center">
                              <span class="status-badge <?= $status_class ?>">
                                <i class="fas <?= $status_icon ?>"></i> <?= $status_text ?>
                              </span>
                            </td>
                           
                          </tr>
                        <?php 
                          endwhile;
                        else: ?>
                          <tr>
                            <td colspan="11" class="text-center">
                              <i class="fas fa-inbox"></i> Tidak ada data maintenance. Silakan gunakan filter untuk melihat data.
                            </td>
                          </tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>

                  <!-- Pagination -->
                  <?php if ($total_pages > 1): ?>
                  <div class="pagination-wrapper">
                    <div class="pagination-info">
                      Menampilkan <?= $offset + 1 ?> - <?= min($offset + $limit, $total_data) ?> dari <?= $total_data ?> data
                    </div>
                    <nav>
                      <ul class="pagination mb-0">
                        <!-- Previous -->
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                          <a class="page-link" href="?tab=data&dari=<?= $_GET['dari'] ?? '' ?>&sampai=<?= $_GET['sampai'] ?? '' ?>&page=<?= $page-1 ?>">
                            <span>&laquo;</span>
                          </a>
                        </li>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);

                        if($start > 1): ?>
                          <li class="page-item">
                            <a class="page-link" href="?tab=data&dari=<?= $_GET['dari'] ?? '' ?>&sampai=<?= $_GET['sampai'] ?? '' ?>&page=1">1</a>
                          </li>
                          <?php if($start > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                          <?php endif;
                        endif;

                        for($i = $start; $i <= $end; $i++): ?>
                          <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?tab=data&dari=<?= $_GET['dari'] ?? '' ?>&sampai=<?= $_GET['sampai'] ?? '' ?>&page=<?= $i ?>"><?= $i ?></a>
                          </li>
                        <?php endfor;

                        if($end < $total_pages): 
                          if($end < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                          <?php endif; ?>
                          <li class="page-item">
                            <a class="page-link" href="?tab=data&dari=<?= $_GET['dari'] ?? '' ?>&sampai=<?= $_GET['sampai'] ?? '' ?>&page=<?= $total_pages ?>"><?= $total_pages ?></a>
                          </li>
                        <?php endif; ?>

                        <!-- Next -->
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                          <a class="page-link" href="?tab=data&dari=<?= $_GET['dari'] ?? '' ?>&sampai=<?= $_GET['sampai'] ?? '' ?>&page=<?= $page+1 ?>">
                            <span>&raquo;</span>
                          </a>
                        </li>
                      </ul>
                    </nav>
                  </div>
                  <?php endif; ?>

                </div>
              </div>
            </div>
          </div>

        </div>
      </section>
    </div>
  </div>
</div>

<!-- Modal Penjelasan Status -->
<div class="modal fade" id="infoModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">
          <i class="fas fa-info-circle"></i> Penjelasan Status Maintenance
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body" style="font-size: 14px;">
        <div class="mb-3">
          <span class="status-badge status-aman"><i class="fas fa-check-circle"></i> Aman</span>
          <p class="mt-2">Maintenance terakhir kurang dari <strong>1 bulan</strong> yang lalu. Perangkat dalam kondisi baik dan tidak memerlukan maintenance segera.</p>
        </div>
        <div class="mb-3">
          <span class="status-badge status-persiapan"><i class="fas fa-exclamation-triangle"></i> Persiapkan Maintenance</span>
          <p class="mt-2">Maintenance terakhir antara <strong>1 hingga 2 bulan</strong> yang lalu. Segera jadwalkan maintenance dalam waktu dekat.</p>
        </div>
        <div class="mb-3">
          <span class="status-badge status-wajib"><i class="fas fa-times-circle"></i> Wajib Maintenance</span>
          <p class="mt-2">Maintenance terakhir lebih dari <strong>2 bulan</strong> yang lalu. Maintenance harus segera dilakukan untuk menghindari kerusakan.</p>
        </div>
        <hr>
        <p class="text-muted mb-0"><small><i class="fas fa-lightbulb"></i> <strong>Tips:</strong> Maintenance rutin setiap 3 bulan membantu menjaga performa dan umur perangkat IT.</small></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
      </div>
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

<script>
$(document).ready(function() {
  // Auto hide flash message
  setTimeout(function(){ 
    $("#flashMsg").fadeOut("slow"); 
  }, 3000);

  // Handle tab from URL parameter
  var urlParams = new URLSearchParams(window.location.search);
  var activeTab = urlParams.get('tab');
  if (activeTab) {
    $('#tabMenu a[href="#' + activeTab + '"]').tab('show');
  }

  // Save active tab
  $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    var tabId = $(e.target).attr('href').substring(1);
    var url = new URL(window.location.href);
    url.searchParams.set('tab', tabId);
    window.history.replaceState({}, '', url);
  });

  // Form validation enhancement - HANYA untuk form input maintenance
  $('#formMaintenance').on('submit', function(e) {
    var barangId = $('#barang_id').val();
    if (!barangId) {
      e.preventDefault();
      alert('Silakan pilih barang terlebih dahulu!');
      $('#barang_id').focus();
      return false;
    }
  });
});
</script>

</body>
</html>