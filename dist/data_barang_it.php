<?php
include 'security.php'; // sudah handle session_start + cek login + timeout
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'];

$current_file = basename(__FILE__); // 

// Cek apakah user boleh mengakses halaman ini
$query = "SELECT 1 FROM akses_menu 
          JOIN menu ON akses_menu.menu_id = menu.id 
          WHERE akses_menu.user_id = '$user_id' AND menu.file_menu = '$current_file'";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) == 0) {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini.'); window.location.href='dashboard.php';</script>";
  exit;
}

// ✅ FUNGSI AUTO GENERATE NOMOR BARANG
function generateNoBarang($conn) {
  // Ambil nomor terakhir
  $query = "SELECT no_barang FROM data_barang_it ORDER BY id DESC LIMIT 1";
  $result = mysqli_query($conn, $query);
  
  if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $last_no = $row['no_barang'];
    
    // Ambil angka dari format 00001/INV-IT/RSPH
    $parts = explode('/', $last_no);
    $number = intval($parts[0]);
    $new_number = $number + 1;
  } else {
    // Jika belum ada data, mulai dari 1
    $new_number = 1;
  }
  
  // Format: 00001/INV-IT/RSPH
  $no_barang = sprintf("%05d", $new_number) . "/INV-IT/RSPH";
  
  return $no_barang;
}

// ✅ Proses Simpan
if (isset($_POST['simpan'])) {
  // Auto generate nomor barang
  $no_barang    = generateNoBarang($conn);
  
  $nama_barang  = mysqli_real_escape_string($conn, $_POST['nama_barang']);
  $kategori     = mysqli_real_escape_string($conn, $_POST['kategori']);
  $merk         = mysqli_real_escape_string($conn, $_POST['merk']);
  $spesifikasi  = mysqli_real_escape_string($conn, $_POST['spesifikasi']);
  $ip_address   = mysqli_real_escape_string($conn, $_POST['ip_address']);
  $lokasi       = mysqli_real_escape_string($conn, $_POST['lokasi']);
  $kondisi      = mysqli_real_escape_string($conn, $_POST['kondisi']);
  $tgl_input    = date('Y-m-d H:i:s');

  $query = "INSERT INTO data_barang_it (
    user_id, no_barang, nama_barang, kategori, merk, spesifikasi, ip_address, lokasi, kondisi
  ) VALUES (
    '$user_id', '$no_barang', '$nama_barang', '$kategori', '$merk', '$spesifikasi', '$ip_address', '$lokasi', '$kondisi'
  )";

  if (mysqli_query($conn, $query)) {
    $_SESSION['flash_message'] = "✅ Data barang berhasil disimpan dengan No: <strong>$no_barang</strong>";
    echo "<script>location.href='data_barang_it.php';</script>";
    exit;
  } else {
    $error_message = mysqli_error($conn);
    $_SESSION['flash_message'] = "❌ Gagal menyimpan data: $error_message";
  }
}

// ✅ Generate nomor barang berikutnya untuk preview
$next_no_barang = generateNoBarang($conn);

// ✅ Ambil lokasi
$lokasi_query = mysqli_query($conn, "SELECT nama_unit FROM unit_kerja ORDER BY nama_unit ASC");

// ✅ SEARCH & FILTER
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where = "WHERE 1=1";
if (!empty($search)) {
    $where .= " AND (nama_barang LIKE '%$search%' OR ip_address LIKE '%$search%' OR no_barang LIKE '%$search%')";
}

// ✅ PAGINATION - Data Barang
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; // Jumlah data per halaman (dinamis)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Hitung total data
$total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM data_barang_it $where");
$total_row = mysqli_fetch_assoc($total_query);
$total_data = $total_row['total'];
$total_pages = ceil($total_data / $limit);

// Ambil data dengan limit
$data_barang = mysqli_query($conn, "
  SELECT * FROM data_barang_it 
  $where
  ORDER BY waktu_input DESC 
  LIMIT $start, $limit
");

// ✅ Rekap jumlah per kategori
$rekap_kategori = mysqli_query($conn, "
  SELECT kategori, COUNT(*) AS jumlah 
  FROM data_barang_it 
  GROUP BY kategori
");

// ✅ Rekap jumlah per kondisi
$rekap_kondisi = mysqli_query($conn, "
  SELECT kondisi, COUNT(*) AS jumlah 
  FROM data_barang_it 
  GROUP BY kondisi
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>f.i.x.p.o.i.n.t</title>
    <link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/components.css" />
  
<style>
  #notif-toast {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 9999;
    display: none;
    min-width: 300px;
  }

  .btn-icon-white {
    background: none;
    border: none;
    color: #fff;
    font-size: 1.1rem;
  }

  .modal-lg-custom {
    max-width: 90%;
  }

  .modal-body .btn-outline-secondary:hover {
    background-color: #343a40;
    color: #fff;
    border-color: #343a40;
  }

  /* Biar semua isi kolom tidak pindah ke baris bawah */
  .table-nowrap td,
  .table-nowrap th {
    white-space: nowrap;
  }

  .table thead th {
    background-color: #000 !important;
    color: #fff !important;
  }

  /* Style untuk preview nomor barang */
  .no-barang-preview {
    background: #e3f2fd;
    border: 2px dashed #1976d2;
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
  }

  .no-barang-preview .label {
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
  }

  .no-barang-preview .value {
    font-size: 20px;
    font-weight: bold;
    color: #1976d2;
    font-family: 'Courier New', monospace;
  }

  /* Pagination Style */
  .pagination {
    margin-top: 20px;
  }

  .pagination .page-link {
    color: #6777ef;
    border: 1px solid #e4e6fc;
    padding: 8px 15px;
    margin: 0 3px;
    border-radius: 5px;
    transition: all 0.3s;
  }

  .pagination .page-link:hover {
    background-color: #6777ef;
    color: #fff;
    border-color: #6777ef;
  }

  .pagination .page-item.active .page-link {
    background-color: #6777ef;
    border-color: #6777ef;
    color: #fff;
    font-weight: bold;
  }

  .pagination .page-item.disabled .page-link {
    color: #ccc;
    cursor: not-allowed;
    background-color: #f8f9fa;
  }

  .pagination .page-link i {
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

          <?php if (isset($_SESSION['flash_message'])): ?>
            <div id="notif-toast" class="alert alert-info text-center">
              <?= $_SESSION['flash_message'] ?>
            </div>
            <?php unset($_SESSION['flash_message']); ?>
          <?php endif; ?>

          <div class="card">
            <div class="card-header">
              <h4>Manajemen Data Barang IT</h4>
            </div>
            <div class="card-body">
              <!-- Nav tabs -->
           <ul class="nav nav-tabs" id="dataTab" role="tablist">
  <li class="nav-item">
    <a class="nav-link <?php echo (!isset($_GET['page']) && !isset($_GET['limit']) && !isset($_GET['search']) && !isset($_SESSION['flash_message'])) ? 'active' : ''; ?>" id="input-tab" data-toggle="tab" href="#input" role="tab">Input Barang</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?php echo (isset($_GET['page']) || isset($_GET['limit']) || isset($_GET['search']) || isset($_SESSION['flash_message'])) ? 'active' : ''; ?>" id="data-tab" data-toggle="tab" href="#data" role="tab">Data Barang</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="laporan-tab" data-toggle="tab" href="#laporan" role="tab">Laporan</a>
  </li>
</ul>


              <!-- Tab panes -->
              <div class="tab-content mt-3">
                <!-- Input Barang -->
                <div class="tab-pane fade <?php echo (!isset($_GET['page']) && !isset($_GET['limit']) && !isset($_GET['search']) && !isset($_SESSION['flash_message'])) ? 'show active' : ''; ?>" id="input" role="tabpanel">
                  
                  <!-- Preview Nomor Barang Otomatis -->
                  <div class="no-barang-preview">
                    <div class="label">
                      <i class="fas fa-barcode"></i> Nomor Barang Otomatis (akan digenerate saat simpan):
                    </div>
                    <div class="value">
                      <?= $next_no_barang ?>
                    </div>
                  </div>

                  <form method="POST">
                    <!-- No Barang dihapus karena auto generate -->
                    
                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label>Nama Barang <span class="text-danger">*</span></label>
                        <input type="text" name="nama_barang" class="form-control" required placeholder="Contoh: Printer Epson L3110">
                      </div>
                      <div class="form-group col-md-6">
                        <label>Kategori <span class="text-danger">*</span></label>
                        <select name="kategori" class="form-control" required>
                          <option value="">-- Pilih Kategori --</option>
                          <option value="Printer">Printer</option>
                          <option value="Komputer">Komputer</option>
                          <option value="Aset IT">Aset IT</option>
                        </select>
                      </div>
                    </div>

                    <div class="form-row">
                      <div class="form-group col-md-4">
                        <label>Merk</label>
                        <input type="text" name="merk" class="form-control" placeholder="Contoh: HP, Dell, Epson">
                      </div>
                      <div class="form-group col-md-4">
                        <label>Spesifikasi</label>
                        <input type="text" name="spesifikasi" class="form-control" placeholder="Contoh: Intel Core i5, RAM 8GB">
                      </div>
                      <div class="form-group col-md-4">
                        <label>IP Address</label>
                        <input type="text" name="ip_address" class="form-control" placeholder="Contoh: 192.168.1.100">
                      </div>
                    </div>

                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label>Lokasi <span class="text-danger">*</span></label>
                        <select name="lokasi" class="form-control" required>
                          <option value="">-- Pilih Lokasi --</option>
                          <?php while ($row = mysqli_fetch_assoc($lokasi_query)): ?>
                            <option value="<?= htmlspecialchars($row['nama_unit']) ?>"><?= htmlspecialchars($row['nama_unit']) ?></option>
                          <?php endwhile; ?>
                        </select>
                      </div>
                      <div class="form-group col-md-6">
                        <label>Kondisi <span class="text-danger">*</span></label>
                        <select name="kondisi" class="form-control" required>
                          <option value="">-- Pilih Kondisi --</option>
                          <option value="Baik">Baik</option>
                          <option value="Rusak Ringan">Rusak Ringan</option>
                          <option value="Rusak Berat">Rusak Berat</option>
                        </select>
                      </div>
                    </div>

                    <div class="alert alert-info">
                      <i class="fas fa-info-circle"></i> 
                      <strong>Info:</strong> Nomor barang akan digenerate otomatis dengan format: <code>00001/INV-IT/RSPH</code>
                    </div>

                    <button type="submit" name="simpan" class="btn btn-primary btn-lg">
                      <i class="fas fa-save"></i> Simpan Data Barang
                    </button>
                    <button type="reset" class="btn btn-secondary btn-lg">
                      <i class="fas fa-redo"></i> Reset Form
                    </button>
                  </form>
                </div>

                <!-- Data Barang -->
                <div class="tab-pane fade <?php echo (isset($_GET['page']) || isset($_GET['limit']) || isset($_GET['search']) || isset($_SESSION['flash_message'])) ? 'show active' : ''; ?>" id="data" role="tabpanel">
                  
                  <!-- Search & Actions -->
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <form method="GET" action="" class="form-inline">
                        <div class="input-group" style="width: 100%;">
                          <input type="text" name="search" class="form-control" placeholder="Cari nama barang atau IP address..." value="<?= htmlspecialchars($search) ?>">
                          <div class="input-group-append">
                            <button type="submit" class="btn btn-primary">
                              <i class="fas fa-search"></i> Cari
                            </button>
                            <?php if (!empty($search)): ?>
                            <a href="data_barang_it.php" class="btn btn-secondary">
                              <i class="fas fa-times"></i> Reset
                            </a>
                            <?php endif; ?>
                          </div>
                        </div>
                      </form>
                    </div>
                    <div class="col-md-6 text-right">
                      <a href="cetak_barang_it.php" target="_blank" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Cetak Data
                      </a>
                    </div>
                  </div>

                  <!-- Info Search Result -->
                  <?php if (!empty($search)): ?>
                  <div class="alert alert-info alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="fas fa-info-circle"></i> 
                    Hasil pencarian untuk: <strong>"<?= htmlspecialchars($search) ?>"</strong> - 
                    Ditemukan <strong><?= $total_data ?></strong> data
                  </div>
                  <?php endif; ?>

                  <!-- Limit & Total -->
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                      <small class="text-muted">Total: <strong><?= $total_data ?></strong> data</small>
                    </div>
                    <div class="form-inline">
                      <label class="mr-2">Tampilkan:</label>
                      <select class="form-control form-control-sm" onchange="changeLimit(this.value)">
                        <option value="10" <?= (isset($_GET['limit']) && $_GET['limit'] == 10) ? 'selected' : '' ?>>10</option>
                        <option value="25" <?= (isset($_GET['limit']) && $_GET['limit'] == 25) ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= (isset($_GET['limit']) && $_GET['limit'] == 50) ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= (isset($_GET['limit']) && $_GET['limit'] == 100) ? 'selected' : '' ?>>100</option>
                      </select>
                      <label class="ml-2">per halaman</label>
                    </div>
                  </div>

                  <div class="table-responsive">
                    <table class="table table-bordered table-striped table-nowrap">

                  <thead class="thead-dark">
                          <tr>
                            <th>No</th>
                            <th>No. Barang</th>
                            <th>Nama</th>
                            <th>Kategori</th>
                            <th>Merk</th>
                            <th>Spesifikasi</th>
                            <th>IP</th>
                            <th>Lokasi</th>
                            <th>Kondisi</th>
                            <th>Tanggal Input</th>
                            <th>Aksi</th>
                          </tr>
                        </thead>
                        <tbody>
                        <?php
                        $no = $start + 1; // Nomor dimulai dari posisi data di halaman saat ini
                        while ($barang = mysqli_fetch_assoc($data_barang)) :
                        ?>
                          <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($barang['no_barang']) ?></strong></td>
                            <td><?= htmlspecialchars($barang['nama_barang']) ?></td>
                            <td><?= htmlspecialchars($barang['kategori']) ?></td>
                            <td><?= htmlspecialchars($barang['merk']) ?></td>
                            <td><?= htmlspecialchars($barang['spesifikasi']) ?></td>
                            <td><?= htmlspecialchars($barang['ip_address']) ?></td>
                            <td><?= htmlspecialchars($barang['lokasi']) ?></td>
                            <td>
                              <?php
                              $kondisi_class = '';
                              if ($barang['kondisi'] == 'Baik') $kondisi_class = 'badge-success';
                              elseif ($barang['kondisi'] == 'Rusak Ringan') $kondisi_class = 'badge-warning';
                              elseif ($barang['kondisi'] == 'Rusak Berat') $kondisi_class = 'badge-danger';
                              ?>
                              <span class="badge <?= $kondisi_class ?>">
                                <?= htmlspecialchars($barang['kondisi']) ?>
                              </span>
                            </td>
                           <td><?= date('d-m-Y H:i', strtotime($barang['waktu_input'])) ?></td>
                           <td>
                            <a href="edit_barang.php?id=<?= $barang['id'] ?>" class="btn btn-warning btn-sm" title="Edit">
                              <i class="fas fa-edit"></i>
                            </a>
                            <a href="hapus_barang.php?id=<?= $barang['id'] ?>" class="btn btn-danger btn-sm" title="Hapus" onclick="return confirm('Yakin ingin menghapus data ini?')">
                              <i class="fas fa-trash-alt"></i>
                            </a>
                          </td>
                          </tr>
                        <?php endwhile; ?>
                      </tbody>
                    </table>
                  </div>

                  <!-- PAGINATION -->
                  <?php if ($total_pages > 1): ?>
                  <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-3">
                      <!-- Previous Button -->
                      <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&limit=<?= $limit ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>" tabindex="-1">
                          <i class="fas fa-chevron-left"></i> Previous
                        </a>
                      </li>

                      <?php
                      // Logika pagination yang smart
                      $range = 2; // Jumlah halaman di kiri & kanan halaman aktif
                      
                      // Tombol pertama
                      if ($page > $range + 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1&limit='.$limit.(!empty($search) ? '&search='.urlencode($search) : '').'">1</a></li>';
                        if ($page > $range + 2) {
                          echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                      }

                      // Halaman di sekitar halaman aktif
                      for ($i = max(1, $page - $range); $i <= min($total_pages, $page + $range); $i++) {
                        $active = ($i == $page) ? 'active' : '';
                        echo '<li class="page-item '.$active.'"><a class="page-link" href="?page='.$i.'&limit='.$limit.(!empty($search) ? '&search='.urlencode($search) : '').'">'.$i.'</a></li>';
                      }

                      // Tombol terakhir
                      if ($page < $total_pages - $range) {
                        if ($page < $total_pages - $range - 1) {
                          echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'&limit='.$limit.(!empty($search) ? '&search='.urlencode($search) : '').'">'.$total_pages.'</a></li>';
                      }
                      ?>

                      <!-- Next Button -->
                      <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&limit=<?= $limit ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>">
                          Next <i class="fas fa-chevron-right"></i>
                        </a>
                      </li>
                    </ul>
                  </nav>

                  <!-- Info Pagination -->
                  <div class="text-center mt-2">
                    <small class="text-muted">
                      Menampilkan <?= $start + 1 ?> - <?= min($start + $limit, $total_data) ?> dari <?= $total_data ?> data
                      <?= !empty($search) ? '(hasil pencarian)' : '' ?>
                    </small>
                  </div>
                  <?php endif; ?>

                </div>



                <!-- Tab Laporan -->
<div class="tab-pane fade" id="laporan" role="tabpanel">
  <div class="row">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0">Rekap per Kategori</h5>
        </div>
        <div class="card-body">
          <ul class="list-group">
            <?php while ($kat = mysqli_fetch_assoc($rekap_kategori)) : ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= htmlspecialchars($kat['kategori']) ?>
                <span class="badge badge-primary badge-pill"><?= $kat['jumlah'] ?></span>
              </li>
            <?php endwhile; ?>
          </ul>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card">
        <div class="card-header bg-success text-white">
          <h5 class="mb-0">Rekap per Kondisi</h5>
        </div>
        <div class="card-body">
          <ul class="list-group">
            <?php while ($kon = mysqli_fetch_assoc($rekap_kondisi)) : ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= htmlspecialchars($kon['kondisi']) ?>
                <span class="badge badge-success badge-pill"><?= $kon['jumlah'] ?></span>
              </li>
            <?php endwhile; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>


                    </table>
                  </div>
                </div>
              </div> <!-- End tab-content -->
            </div> <!-- End card-body -->
          </div> <!-- End card -->
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
  $(document).ready(function () {
    var toast = $('#notif-toast');
    if (toast.length) {
      toast.fadeIn(300).delay(3000).fadeOut(500);
    }

    // TIDAK PERLU auto-switch lagi karena sudah di-handle di PHP dengan class 'active'
    // Tab sudah langsung render dengan benar tanpa flicker!
  });

  // Fungsi untuk mengubah limit data per halaman
  function changeLimit(limit) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('limit', limit);
    urlParams.set('page', 1); // Reset ke halaman 1 saat ganti limit
    window.location.href = window.location.pathname + '?' + urlParams.toString();
  }
</script>

</body>
</html>