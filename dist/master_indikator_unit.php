<?php
// master_indikator_unit.php
include 'security.php';
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'] ?? 0;
$nama_user = $_SESSION['nama_user'] ?? '';
$activeTab = 'data';
$modals = [];

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

// proses simpan
if (isset($_POST['simpan'])) {
    $id_rs           = intval($_POST['id_rs']);
    $unit_id         = intval($_POST['unit_id']);
    $nama_indikator  = mysqli_real_escape_string($conn, $_POST['nama_indikator']);
    $definisi        = mysqli_real_escape_string($conn, $_POST['definisi']);
    $numerator       = mysqli_real_escape_string($conn, $_POST['numerator']);
    $denominator     = mysqli_real_escape_string($conn, $_POST['denominator']);
    $standar         = mysqli_real_escape_string($conn, $_POST['standar']);
    $sumber_data     = mysqli_real_escape_string($conn, $_POST['sumber_data']);
    $frekuensi       = mysqli_real_escape_string($conn, $_POST['frekuensi']);
    $penanggung_jawab= intval($_POST['penanggung_jawab']); // ambil ID user

    if ($nama_indikator && $standar !== '' && $unit_id) {
        $q = "INSERT INTO indikator_unit 
                (id_rs, unit_id, nama_indikator, definisi, numerator, denominator, standar, sumber_data, frekuensi, penanggung_jawab) 
              VALUES 
                (" . ($id_rs ? $id_rs : "NULL") . ", 
                 '$unit_id', 
                 '$nama_indikator', 
                 '$definisi', 
                 '$numerator', 
                 '$denominator', 
                 '$standar', 
                 '$sumber_data', 
                 '$frekuensi', 
                 '$penanggung_jawab')";
        if (mysqli_query($conn, $q)) {
            $_SESSION['flash_message'] = "Data berhasil disimpan.";
            $_SESSION['flash_type'] = "success";
            $activeTab = 'data';
        } else {
            $_SESSION['flash_message'] = "Gagal menyimpan data: " . mysqli_error($conn);
            $_SESSION['flash_type'] = "danger";
            $activeTab = 'input';
        }
    } else {
        $_SESSION['flash_message'] = "Lengkapi semua field wajib!";
        $_SESSION['flash_type'] = "warning";
        $activeTab = 'input';
    }
}

// proses update
if (isset($_POST['update'])) {
    $id_unit        = intval($_POST['id_unit']);
    $id_rs          = intval($_POST['id_rs']);
    $unit_id        = intval($_POST['unit_id']);
    $nama_indikator = mysqli_real_escape_string($conn, $_POST['nama_indikator']);
    $definisi       = mysqli_real_escape_string($conn, $_POST['definisi']);
    $numerator      = mysqli_real_escape_string($conn, $_POST['numerator']);
    $denominator    = mysqli_real_escape_string($conn, $_POST['denominator']);
    $standar        = mysqli_real_escape_string($conn, $_POST['standar']);
    $sumber_data    = mysqli_real_escape_string($conn, $_POST['sumber_data']);
    $frekuensi      = mysqli_real_escape_string($conn, $_POST['frekuensi']);
    $pj_id          = intval($_POST['penanggung_jawab']);

    if ($nama_indikator && $standar !== '' && $unit_id) {
        $id_rs_value = $id_rs ? $id_rs : "NULL";
        $q = "UPDATE indikator_unit SET
              id_rs=$id_rs_value,
              unit_id='$unit_id',
              nama_indikator='$nama_indikator',
              definisi='$definisi',
              numerator='$numerator',
              denominator='$denominator',
              standar='$standar',
              sumber_data='$sumber_data',
              frekuensi='$frekuensi',
              penanggung_jawab='$pj_id'
              WHERE id_unit='$id_unit'";
        if (mysqli_query($conn, $q)) {
            $_SESSION['flash_message'] = "Data berhasil diperbarui.";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Gagal memperbarui data: " . mysqli_error($conn);
            $_SESSION['flash_type'] = "danger";
        }
    } else {
        $_SESSION['flash_message'] = "Lengkapi semua field wajib!";
        $_SESSION['flash_type'] = "warning";
    }
    header("Location: master_indikator_unit.php?page=" . ($_POST['current_page'] ?? 1));
    exit;
}

// hapus data
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    mysqli_query($conn, "DELETE FROM indikator_unit WHERE id_unit='$id'");
    $_SESSION['flash_message'] = "Data berhasil dihapus.";
    $_SESSION['flash_type'] = "success";
    header("Location: master_indikator_unit.php?page=" . ($_GET['page'] ?? 1));
    exit;
}

// Pagination setup
$limit = 10; // Jumlah data per halaman
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page = $page < 1 ? 1 : $page;
$offset = ($page - 1) * $limit;

// Hitung total data
$countQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM indikator_unit");
$totalData = mysqli_fetch_assoc($countQuery)['total'];
$totalPages = ceil($totalData / $limit);

// ambil indikator RS untuk form
$indikatorRS = mysqli_query($conn, "SELECT id_rs, nama_indikator FROM indikator_rs ORDER BY nama_indikator");
// ambil unit kerja untuk form
$units = mysqli_query($conn, "SELECT id, nama_unit FROM unit_kerja ORDER BY nama_unit");
// ambil user sebagai penanggung jawab untuk form
$users = mysqli_query($conn, "SELECT id, nama FROM users ORDER BY nama");
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Master Indikator Unit</title>
  <link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <style>
    .dokumen-table { font-size: 13px; white-space: nowrap; }
    .dokumen-table th, .dokumen-table td { padding: 6px 10px; vertical-align: middle; }
    .flash-center {
      position: fixed; top: 20%; left: 50%; transform: translate(-50%, -50%);
      z-index: 1050; min-width: 300px; max-width: 90%; text-align: center;
      padding: 15px; border-radius: 8px; font-weight: 500;
      box-shadow: 0 5px 15px rgba(0,0,0,0.3);
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
      color: #6c757d;
    }
    .aksi-btn { display: flex; gap: 5px; justify-content: center; }
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
            <?= htmlspecialchars($_SESSION['flash_message']); 
                unset($_SESSION['flash_message']); 
                unset($_SESSION['flash_type']); 
            ?>
          </div>
        <?php endif; ?>

          <div class="card">
            <div class="card-header">
              <h4 class="mb-0">Master Indikator Unit</h4>
            </div>
            <div class="card-body">
              <ul class="nav nav-tabs" id="indikatorTab" role="tablist">
                <li class="nav-item">
                  <a class="nav-link <?= ($activeTab=='input')?'active':'' ?>" id="input-tab" data-toggle="tab" href="#input" role="tab">Input Data</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link <?= ($activeTab=='data')?'active':'' ?>" id="data-tab" data-toggle="tab" href="#data" role="tab">Data Indikator</a>
                </li>
              </ul>

              <div class="tab-content mt-3">
                <!-- FORM INPUT -->
                <div class="tab-pane fade <?= ($activeTab=='input')?'show active':'' ?>" id="input" role="tabpanel">
                  <form method="POST">
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Indikator RS (Opsional)</label>
                          <select name="id_rs" class="form-control select2">
                            <option value="">-- Tidak terkait --</option>
                            <?php 
                            mysqli_data_seek($indikatorRS, 0);
                            while($rs = mysqli_fetch_assoc($indikatorRS)): ?>
                              <option value="<?= $rs['id_rs'] ?>"><?= htmlspecialchars($rs['nama_indikator']) ?></option>
                            <?php endwhile; ?>
                          </select>
                        </div>
                        <div class="form-group">
                          <label>Unit Kerja <span class="text-danger">*</span></label>
                          <select name="unit_id" class="form-control select2" required>
                            <option value="">-- Pilih Unit --</option>
                            <?php 
                            mysqli_data_seek($units, 0);
                            while($u = mysqli_fetch_assoc($units)): ?>
                              <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
                            <?php endwhile; ?>
                          </select>
                        </div>
                        <div class="form-group">
                          <label>Nama Indikator <span class="text-danger">*</span></label>
                          <input type="text" name="nama_indikator" class="form-control" required>
                        </div>
                        <div class="form-group">
                          <label>Definisi Operasional</label>
                          <textarea name="definisi" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                          <label>Numerator</label>
                          <textarea name="numerator" class="form-control" rows="2"></textarea>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Denominator</label>
                          <textarea name="denominator" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                          <label>Standar/Target (%) <span class="text-danger">*</span></label>
                          <input type="number" step="0.01" min="0" name="standar" class="form-control" required>
                          <small class="form-text text-muted">Nilai standar boleh 0 atau lebih</small>
                        </div>
                        <div class="form-group">
                          <label>Sumber Data</label>
                          <input type="text" name="sumber_data" class="form-control">
                        </div>
                        <div class="form-group">
                          <label>Frekuensi Pelaporan</label>
                          <select name="frekuensi" class="form-control">
                            <option value="">-- Pilih --</option>
                            <option value="Harian">Harian</option>
                            <option value="Mingguan">Mingguan</option>
                            <option value="Bulanan">Bulanan</option>
                            <option value="Triwulan">Triwulan</option>
                            <option value="Tahunan">Tahunan</option>
                          </select>
                        </div>
                        <div class="form-group">
                          <label>Penanggung Jawab <span class="text-danger">*</span></label>
                          <select name="penanggung_jawab" id="penanggung_jawab" class="form-control select2" required>
                            <option value="">-- Pilih Penanggung Jawab --</option>
                            <?php 
                            mysqli_data_seek($users, 0);
                            while($usr = mysqli_fetch_assoc($users)): ?>
                              <option value="<?= $usr['id'] ?>"><?= htmlspecialchars($usr['nama']) ?></option>
                            <?php endwhile; ?>
                          </select>
                        </div>
                      </div>
                    </div>
                    <div class="form-group">
                      <button type="submit" name="simpan" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                      </button>
                      <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                      </button>
                    </div>
                  </form>
                </div>

                <!-- DATA -->
                <div class="tab-pane fade <?= ($activeTab=='data')?'show active':'' ?>" id="data" role="tabpanel">
                  <div class="table-responsive">
                    <table class="table table-bordered table-striped dokumen-table">
                      <thead class="thead-light">
                        <tr>
                          <th width="50">No</th>
                          <th>Unit</th>
                          <th>Nama Indikator</th>
                          <th width="100">Standar (%)</th>
                          <th>Frekuensi</th>
                          <th>Penanggung Jawab</th>
                          <th>Indikator RS</th>
                          <th width="120">Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                      <?php
                      $qInd = mysqli_query($conn, "SELECT iu.id_unit, iu.nama_indikator, iu.standar, iu.frekuensi, 
                                                          u.nama_unit, rs.nama_indikator AS indikator_rs,
                                                          usr.nama AS penanggung_jawab, iu.id_rs, iu.unit_id,
                                                          iu.definisi, iu.numerator, iu.denominator, iu.sumber_data, 
                                                          iu.penanggung_jawab AS pj_id
                                                   FROM indikator_unit iu
                                                   LEFT JOIN unit_kerja u ON iu.unit_id=u.id
                                                   LEFT JOIN indikator_rs rs ON iu.id_rs=rs.id_rs
                                                   LEFT JOIN users usr ON iu.penanggung_jawab=usr.id
                                                   ORDER BY iu.id_unit DESC
                                                   LIMIT $limit OFFSET $offset");
                      $no = $offset + 1;
                      if(mysqli_num_rows($qInd) > 0):
                        while($row = mysqli_fetch_assoc($qInd)): 
                            ob_start(); ?>
                            <!-- Modal Edit -->
                            <div class="modal fade" id="editModal<?= $row['id_unit'] ?>" tabindex="-1">
                              <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                  <form method="POST">
                                    <div class="modal-header bg-primary text-white">
                                      <h5 class="modal-title">Edit Indikator Unit</h5>
                                      <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                    </div>
                                    <div class="modal-body">
                                      <input type="hidden" name="id_unit" value="<?= $row['id_unit'] ?>">
                                      <input type="hidden" name="current_page" value="<?= $page ?>">
                                      
                                      <div class="row">
                                        <div class="col-md-6">
                                          <div class="form-group">
                                            <label>Indikator RS (Opsional)</label>
                                            <select name="id_rs" class="form-control">
                                              <option value="">-- Tidak terkait --</option>
                                              <?php 
                                              $rsEdit = mysqli_query($conn, "SELECT id_rs, nama_indikator FROM indikator_rs ORDER BY nama_indikator");
                                              while($rs = mysqli_fetch_assoc($rsEdit)): ?>
                                                <option value="<?= $rs['id_rs'] ?>" <?= ($rs['id_rs'] == $row['id_rs']) ? 'selected' : '' ?>>
                                                  <?= htmlspecialchars($rs['nama_indikator']) ?>
                                                </option>
                                              <?php endwhile; ?>
                                            </select>
                                          </div>
                                          <div class="form-group">
                                            <label>Unit Kerja <span class="text-danger">*</span></label>
                                            <select name="unit_id" class="form-control" required>
                                              <option value="">-- Pilih Unit --</option>
                                              <?php 
                                              $unitEdit = mysqli_query($conn, "SELECT id, nama_unit FROM unit_kerja ORDER BY nama_unit");
                                              while($u = mysqli_fetch_assoc($unitEdit)): ?>
                                                <option value="<?= $u['id'] ?>" <?= ($u['id'] == $row['unit_id']) ? 'selected' : '' ?>>
                                                  <?= htmlspecialchars($u['nama_unit']) ?>
                                                </option>
                                              <?php endwhile; ?>
                                            </select>
                                          </div>
                                          <div class="form-group">
                                            <label>Nama Indikator <span class="text-danger">*</span></label>
                                            <input type="text" name="nama_indikator" class="form-control" 
                                                   value="<?= htmlspecialchars($row['nama_indikator']) ?>" required>
                                          </div>
                                          <div class="form-group">
                                            <label>Definisi Operasional</label>
                                            <textarea name="definisi" class="form-control" rows="3"><?= htmlspecialchars($row['definisi']) ?></textarea>
                                          </div>
                                          <div class="form-group">
                                            <label>Numerator</label>
                                            <textarea name="numerator" class="form-control" rows="2"><?= htmlspecialchars($row['numerator']) ?></textarea>
                                          </div>
                                        </div>
                                        <div class="col-md-6">
                                          <div class="form-group">
                                            <label>Denominator</label>
                                            <textarea name="denominator" class="form-control" rows="2"><?= htmlspecialchars($row['denominator']) ?></textarea>
                                          </div>
                                          <div class="form-group">
                                            <label>Standar/Target (%) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" min="0" name="standar" class="form-control" 
                                                   value="<?= htmlspecialchars($row['standar']) ?>" required>
                                          </div>
                                          <div class="form-group">
                                            <label>Sumber Data</label>
                                            <input type="text" name="sumber_data" class="form-control" 
                                                   value="<?= htmlspecialchars($row['sumber_data']) ?>">
                                          </div>
                                          <div class="form-group">
                                            <label>Frekuensi Pelaporan</label>
                                            <select name="frekuensi" class="form-control">
                                              <option value="">-- Pilih --</option>
                                              <option <?= ($row['frekuensi']=='Harian')?'selected':'' ?>>Harian</option>
                                              <option <?= ($row['frekuensi']=='Mingguan')?'selected':'' ?>>Mingguan</option>
                                              <option <?= ($row['frekuensi']=='Bulanan')?'selected':'' ?>>Bulanan</option>
                                              <option <?= ($row['frekuensi']=='Triwulan')?'selected':'' ?>>Triwulan</option>
                                              <option <?= ($row['frekuensi']=='Tahunan')?'selected':'' ?>>Tahunan</option>
                                            </select>
                                          </div>
                                          <div class="form-group">
                                            <label>Penanggung Jawab <span class="text-danger">*</span></label>
                                            <select name="penanggung_jawab" class="form-control" required>
                                              <option value="">-- Pilih Penanggung Jawab --</option>
                                              <?php 
                                              $userEdit = mysqli_query($conn, "SELECT id, nama FROM users ORDER BY nama");
                                              while($usr = mysqli_fetch_assoc($userEdit)): ?>
                                                <option value="<?= $usr['id'] ?>" <?= ($usr['id'] == $row['pj_id']) ? 'selected' : '' ?>>
                                                  <?= htmlspecialchars($usr['nama']) ?>
                                                </option>
                                              <?php endwhile; ?>
                                            </select>
                                          </div>
                                        </div>
                                      </div>
                                    </div>
                                    <div class="modal-footer">
                                      <button type="submit" name="update" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Simpan Perubahan
                                      </button>
                                      <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                        <i class="fas fa-times"></i> Batal
                                      </button>
                                    </div>
                                  </form>
                                </div>
                              </div>
                            </div>
                            <?php $modals[] = ob_get_clean(); ?>
                          <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['nama_unit']) ?></td>
                            <td><?= htmlspecialchars($row['nama_indikator']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($row['standar']) ?></td>
                            <td><?= htmlspecialchars($row['frekuensi']) ?></td>
                            <td><?= htmlspecialchars($row['penanggung_jawab'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['indikator_rs'] ?? '-') ?></td>
                            <td class="text-center">
                              <div class="aksi-btn">
                                <button class="btn btn-sm btn-warning" data-toggle="modal" 
                                        data-target="#editModal<?= $row['id_unit'] ?>" 
                                        title="Edit">
                                  <i class="fas fa-edit"></i>
                                </button>
                                <a href="?hapus=<?= $row['id_unit'] ?>&page=<?= $page ?>" 
                                   onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')" 
                                   class="btn btn-sm btn-danger"
                                   title="Hapus">
                                  <i class="fas fa-trash"></i>
                                </a>
                              </div>
                            </td>
                          </tr>
                        <?php endwhile;
                      else: ?>
                        <tr>
                          <td colspan="8" class="text-center">Tidak ada data indikator unit</td>
                        </tr>
                      <?php endif; ?>
                      </tbody>
                    </table>
                  </div>

                  <!-- Pagination -->
                  <?php if($totalPages > 1): ?>
                  <div class="pagination-wrapper">
                    <div class="pagination-info">
                      Menampilkan <?= $offset + 1 ?> - <?= min($offset + $limit, $totalData) ?> dari <?= $totalData ?> data
                    </div>
                    <nav>
                      <ul class="pagination mb-0">
                        <!-- Previous Button -->
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                          <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                          </a>
                        </li>

                        <?php
                        // Pagination logic
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);

                        // First page
                        if($start > 1): ?>
                          <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
                          <?php if($start > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                          <?php endif;
                        endif;

                        // Page numbers
                        for($i = $start; $i <= $end; $i++): ?>
                          <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                          </li>
                        <?php endfor;

                        // Last page
                        if($end < $totalPages): 
                          if($end < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                          <?php endif; ?>
                          <li class="page-item"><a class="page-link" href="?page=<?= $totalPages ?>"><?= $totalPages ?></a></li>
                        <?php endif; ?>

                        <!-- Next Button -->
                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                          <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                          </a>
                        </li>
                      </ul>
                    </nav>
                  </div>
                  <?php endif; ?>

                </div>

              </div> <!-- end tab-content -->
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
<script>
  $(document).ready(function() {
    // Inisialisasi Select2 untuk semua select dengan class select2
    $('.select2').select2({
      placeholder: "-- Pilih --",
      allowClear: true,
      width: '100%'
    });

    // Khusus untuk penanggung jawab
    $('#penanggung_jawab').select2({
      placeholder: "Cari nama penanggung jawab...",
      allowClear: true,
      width: '100%'
    });

    // Auto hide flash message
    setTimeout(function(){ 
      $("#flashMsg").fadeOut("slow"); 
    }, 3000);
  });
</script>

<?php
// Render all modals
foreach ($modals as $m) echo $m;
?>

</body>
</html>