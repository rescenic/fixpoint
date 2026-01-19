<?php
include 'security.php'; 
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'];
$current_file = basename(__FILE__);

// Cek akses menu
$query = "SELECT 1 FROM akses_menu 
          JOIN menu ON akses_menu.menu_id = menu.id 
          WHERE akses_menu.user_id = '$user_id' AND menu.file_menu = '$current_file'";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) == 0) {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini.'); window.location.href='dashboard.php';</script>";
  exit;
}

// Ambil filter tanggal dari GET
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Pagination settings
$limit = 10; // Data per halaman
$page_hardware = isset($_GET['page_hardware']) ? (int)$_GET['page_hardware'] : 1;
$page_software = isset($_GET['page_software']) ? (int)$_GET['page_software'] : 1;

if ($page_hardware < 1) $page_hardware = 1;
if ($page_software < 1) $page_software = 1;

$start_hardware = ($page_hardware - 1) * $limit;
$start_software = ($page_software - 1) * $limit;

// Validasi dan format tanggal untuk query
$where_hardware = '';
$where_software = '';

if ($start_date && $end_date) {
    $start_datetime = date('Y-m-d 00:00:00', strtotime($start_date));
    $end_datetime = date('Y-m-d 23:59:59', strtotime($end_date));
    
    $where_hardware = " WHERE tanggal_ba BETWEEN '$start_datetime' AND '$end_datetime' ";
    $where_software = " WHERE tanggal_ba BETWEEN '$start_datetime' AND '$end_datetime' ";
}

// Query count untuk pagination Hardware
$count_hardware_query = "SELECT COUNT(*) as total FROM berita_acara $where_hardware";
$count_hardware_result = mysqli_query($conn, $count_hardware_query);
$total_hardware = mysqli_fetch_assoc($count_hardware_result)['total'];
$total_pages_hardware = ceil($total_hardware / $limit);

// Query count untuk pagination Software
$count_software_query = "SELECT COUNT(*) as total FROM berita_acara_software $where_software";
$count_software_result = mysqli_query($conn, $count_software_query);
$total_software = mysqli_fetch_assoc($count_software_result)['total'];
$total_pages_software = ceil($total_software / $limit);

// Query data hardware dengan filter dan pagination
$query_hardware = "SELECT * FROM berita_acara $where_hardware ORDER BY tanggal_ba DESC LIMIT $start_hardware, $limit";
$result_hardware = mysqli_query($conn, $query_hardware);

// Query data software dengan filter dan pagination
$query_software = "SELECT * FROM berita_acara_software $where_software ORDER BY tanggal_ba DESC LIMIT $start_software, $limit";
$result_software = mysqli_query($conn, $query_software);

// Function untuk generate query string pagination
function get_query_string($exclude = []) {
    $params = $_GET;
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    return http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport" />
  <title>f.i.x.p.o.i.n.t - Berita Acara IT</title>

  <link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/components.css" />

  <style>
    .table-responsive-custom {
      width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    .table-responsive-custom table {
      width: 100%;
      min-width: 1500px;
      white-space: nowrap;
    }

    .table thead th {
      background-color: #000 !important;
      color: #fff !important;
    }
    
    .pagination-wrapper {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 20px;
    }
    
    .pagination-info {
      font-size: 14px;
      color: #000;
      font-weight: 500;
    }
    
    .btn-cetak {
      padding: 5px 10px;
      font-size: 12px;
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

          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h4><i class="fas fa-tools text-warning mr-2"></i>Data Berita Acara IT</h4>

             <form class="form-inline" method="GET" action="<?= $current_file ?>">
  <div class="form-group mr-2">
    <label for="start_date" class="mr-2 mb-0 font-weight-bold">Dari</label>
    <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>" required />
  </div>
  <div class="form-group mr-2">
    <label for="end_date" class="mr-2 mb-0 font-weight-bold">Sampai</label>
    <input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>" required />
  </div>
  <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-filter"></i> Filter</button>

  <?php if ($start_date && $end_date): ?>
    <a href="cetak_berita_acara_it.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" 
       target="_blank" 
       class="btn btn-success" 
       title="Cetak Laporan Periode">
      <i class="fas fa-print"></i> Cetak Laporan Periode
    </a>
  <?php endif; ?>
</form>

            </div>

            <div class="card-body">
              <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item">
                  <a class="nav-link active" id="hardware-tab" data-toggle="tab" href="#hardware" role="tab">BA IT Hardware</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" id="software-tab" data-toggle="tab" href="#software" role="tab">BA IT Software</a>
                </li>
              </ul>

              <div class="tab-content mt-4" id="myTabContent">

                <!-- BA IT Hardware Tab -->
                <div class="tab-pane fade show active" id="hardware" role="tabpanel">
                  <div class="table-responsive-custom">
                    <table class="table table-bordered table-striped table-hover table-sm">
                      <thead>
                        <tr>
                          <th width="40">No</th>
                          <th>Nomor BA</th>
                          <th>Nomor Tiket</th>
                          <th>Tanggal</th>
                          <th>NIK</th>
                          <th>Nama Pelapor</th>
                          <th>Jabatan</th>
                          <th>Unit Kerja</th>
                          <th>Kategori</th>
                          <th>Kendala</th>
                          <th>Catatan Teknisi</th>
                          <th>Tanggal BA</th>
                          <th>Teknisi</th>
                          <th>Dibuat</th>
                          <th width="100">Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php 
                        if(mysqli_num_rows($result_hardware) > 0):
                          $no = $start_hardware + 1;
                          while($row = mysqli_fetch_assoc($result_hardware)): 
                        ?>
                          <tr>
                            <td class="text-center"><?= $no++; ?></td>
                            <td><?= htmlspecialchars($row['nomor_ba']); ?></td>
                            <td><?= htmlspecialchars($row['nomor_tiket']); ?></td>
                            <td><?= date('d-m-Y H:i', strtotime($row['tanggal'])); ?></td>
                            <td><?= htmlspecialchars($row['nik']); ?></td>
                            <td><?= htmlspecialchars($row['nama_pelapor']); ?></td>
                            <td><?= htmlspecialchars($row['jabatan']); ?></td>
                            <td><?= htmlspecialchars($row['unit_kerja']); ?></td>
                            <td><?= htmlspecialchars($row['kategori']); ?></td>
                            <td><?= nl2br(htmlspecialchars($row['kendala'])); ?></td>
                            <td><?= nl2br(htmlspecialchars($row['catatan_teknisi'])); ?></td>
                            <td><?= date('d-m-Y H:i', strtotime($row['tanggal_ba'])); ?></td>
                            <td><?= htmlspecialchars($row['teknisi']); ?></td>
                            <td><?= date('d-m-Y H:i', strtotime($row['created_at'])); ?></td>
                            <td class="text-center">
                              <a href="cetak_berita_acara.php?id=<?= $row['id'] ?>" 
                                 target="_blank" 
                                 class="btn btn-info btn-sm btn-cetak" 
                                 title="Cetak BA">
                                <i class="fas fa-print"></i> Cetak
                              </a>
                            </td>
                          </tr>
                        <?php 
                          endwhile;
                        else: ?>
                          <tr>
                            <td colspan="15" class="text-center">Data berita acara hardware belum tersedia.</td>
                          </tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                  
                  <!-- Pagination Hardware -->
                  <?php if ($total_pages_hardware > 1): ?>
                  <div class="pagination-wrapper">
                    <div class="pagination-info">
                      Menampilkan <?= $start_hardware + 1 ?> - <?= min($start_hardware + $limit, $total_hardware) ?> dari <?= $total_hardware ?> data
                    </div>
                    <nav>
                      <ul class="pagination mb-0">
                        <li class="page-item <?= ($page_hardware <= 1) ? 'disabled' : '' ?>">
                          <a class="page-link" href="?<?= get_query_string(['page_hardware']) ?>&page_hardware=<?= $page_hardware - 1 ?>">
                            <span>&laquo;</span>
                          </a>
                        </li>

                        <?php
                        $start_page = max(1, $page_hardware - 2);
                        $end_page = min($total_pages_hardware, $page_hardware + 2);

                        if($start_page > 1): ?>
                          <li class="page-item"><a class="page-link" href="?<?= get_query_string(['page_hardware']) ?>&page_hardware=1">1</a></li>
                          <?php if($start_page > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                          <?php endif;
                        endif;

                        for($i = $start_page; $i <= $end_page; $i++): ?>
                          <li class="page-item <?= ($i == $page_hardware) ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= get_query_string(['page_hardware']) ?>&page_hardware=<?= $i ?>"><?= $i ?></a>
                          </li>
                        <?php endfor;

                        if($end_page < $total_pages_hardware): 
                          if($end_page < $total_pages_hardware - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                          <?php endif; ?>
                          <li class="page-item"><a class="page-link" href="?<?= get_query_string(['page_hardware']) ?>&page_hardware=<?= $total_pages_hardware ?>"><?= $total_pages_hardware ?></a></li>
                        <?php endif; ?>

                        <li class="page-item <?= ($page_hardware >= $total_pages_hardware) ? 'disabled' : '' ?>">
                          <a class="page-link" href="?<?= get_query_string(['page_hardware']) ?>&page_hardware=<?= $page_hardware + 1 ?>">
                            <span>&raquo;</span>
                          </a>
                        </li>
                      </ul>
                    </nav>
                  </div>
                  <?php endif; ?>
                </div>

                <!-- BA IT Software Tab -->
                <div class="tab-pane fade" id="software" role="tabpanel">
                  <div class="table-responsive-custom">
                    <table class="table table-bordered table-striped table-hover table-sm">
                      <thead>
                        <tr>
                          <th width="40">No</th>
                          <th>Nomor BA</th>
                          <th>Nomor Tiket</th>
                          <th>Tanggal</th>
                          <th>NIK</th>
                          <th>Nama Pelapor</th>
                          <th>Jabatan</th>
                          <th>Unit Kerja</th>
                          <th>Kategori</th>
                          <th>Kendala</th>
                          <th>Catatan Teknisi</th>
                          <th>Tanggal BA</th>
                          <th>Teknisi</th>
                          <th>Dibuat</th>
                          <th width="100">Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php 
                        if(mysqli_num_rows($result_software) > 0):
                          $no = $start_software + 1;
                          while($row = mysqli_fetch_assoc($result_software)): 
                        ?>
                          <tr>
                            <td class="text-center"><?= $no++; ?></td>
                            <td><?= htmlspecialchars($row['nomor_ba']); ?></td>
                            <td><?= htmlspecialchars($row['nomor_tiket']); ?></td>
                            <td><?= date('d-m-Y H:i', strtotime($row['tanggal'])); ?></td>
                            <td><?= htmlspecialchars($row['nik']); ?></td>
                            <td><?= htmlspecialchars($row['nama_pelapor']); ?></td>
                            <td><?= htmlspecialchars($row['jabatan']); ?></td>
                            <td><?= htmlspecialchars($row['unit_kerja']); ?></td>
                            <td><?= htmlspecialchars($row['kategori']); ?></td>
                            <td><?= nl2br(htmlspecialchars($row['kendala'])); ?></td>
                            <td><?= nl2br(htmlspecialchars($row['catatan_teknisi'])); ?></td>
                            <td><?= date('d-m-Y H:i', strtotime($row['tanggal_ba'])); ?></td>
                            <td><?= htmlspecialchars($row['teknisi']); ?></td>
                            <td><?= date('d-m-Y H:i', strtotime($row['created_at'])); ?></td>
                            <td class="text-center">
                              <a href="cetak_ba_software.php?id=<?= $row['id'] ?>" 
                                 target="_blank" 
                                 class="btn btn-info btn-sm btn-cetak" 
                                 title="Cetak BA">
                                <i class="fas fa-print"></i> Cetak
                              </a>
                            </td>
                          </tr>
                        <?php 
                          endwhile;
                        else: ?>
                          <tr>
                            <td colspan="15" class="text-center">Data berita acara software belum tersedia.</td>
                          </tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                  
                  <!-- Pagination Software -->
                  <?php if ($total_pages_software > 1): ?>
                  <div class="pagination-wrapper">
                    <div class="pagination-info">
                      Menampilkan <?= $start_software + 1 ?> - <?= min($start_software + $limit, $total_software) ?> dari <?= $total_software ?> data
                    </div>
                    <nav>
                      <ul class="pagination mb-0">
                        <li class="page-item <?= ($page_software <= 1) ? 'disabled' : '' ?>">
                          <a class="page-link" href="?<?= get_query_string(['page_software']) ?>&page_software=<?= $page_software - 1 ?>">
                            <span>&laquo;</span>
                          </a>
                        </li>

                        <?php
                        $start_page = max(1, $page_software - 2);
                        $end_page = min($total_pages_software, $page_software + 2);

                        if($start_page > 1): ?>
                          <li class="page-item"><a class="page-link" href="?<?= get_query_string(['page_software']) ?>&page_software=1">1</a></li>
                          <?php if($start_page > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                          <?php endif;
                        endif;

                        for($i = $start_page; $i <= $end_page; $i++): ?>
                          <li class="page-item <?= ($i == $page_software) ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= get_query_string(['page_software']) ?>&page_software=<?= $i ?>"><?= $i ?></a>
                          </li>
                        <?php endfor;

                        if($end_page < $total_pages_software): 
                          if($end_page < $total_pages_software - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                          <?php endif; ?>
                          <li class="page-item"><a class="page-link" href="?<?= get_query_string(['page_software']) ?>&page_software=<?= $total_pages_software ?>"><?= $total_pages_software ?></a></li>
                        <?php endif; ?>

                        <li class="page-item <?= ($page_software >= $total_pages_software) ? 'disabled' : '' ?>">
                          <a class="page-link" href="?<?= get_query_string(['page_software']) ?>&page_software=<?= $page_software + 1 ?>">
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

<script src="assets/modules/jquery.min.js"></script>
<script src="assets/modules/popper.js"></script>
<script src="assets/modules/bootstrap/js/bootstrap.min.js"></script>
<script src="assets/modules/nicescroll/jquery.nicescroll.min.js"></script>
<script src="assets/modules/moment.min.js"></script>
<script src="assets/js/stisla.js"></script>
<script src="assets/js/scripts.js"></script>
<script src="assets/js/custom.js"></script>

<script>
// Simpan tab aktif
$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
  localStorage.setItem('activeBATab', $(e.target).attr('href'));
});

// Restore tab aktif
$(document).ready(function() {
  var activeTab = localStorage.getItem('activeBATab');
  if (activeTab) {
    $('#myTab a[href="' + activeTab + '"]').tab('show');
  }
});
</script>

</body>
</html>