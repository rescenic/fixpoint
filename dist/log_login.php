<?php
include 'security.php'; 
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'];
$current_file = basename(__FILE__);

// Cek akses user (optional - comment jika tidak pakai sistem akses menu)
/*
$query = "SELECT 1 FROM akses_menu 
          JOIN menu ON akses_menu.menu_id = menu.id 
          WHERE akses_menu.user_id = '$user_id' 
          AND menu.file_menu = '$current_file'";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) == 0) {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini.'); window.location.href='dashboard.php';</script>";
  exit;
}
*/

// Pagination
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter
$filter_email = isset($_GET['email']) ? mysqli_real_escape_string($conn, trim($_GET['email'])) : '';
$filter_ip = isset($_GET['ip']) ? mysqli_real_escape_string($conn, trim($_GET['ip'])) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$filter_date = isset($_GET['date']) ? mysqli_real_escape_string($conn, $_GET['date']) : '';

// Build WHERE clause
$where = [];
if ($filter_email !== '') {
    $where[] = "email LIKE '%$filter_email%'";
}
if ($filter_ip !== '') {
    $where[] = "ip_address = '$filter_ip'";
}
if ($filter_status !== '') {
    $where[] = "success = '$filter_status'";
}
if ($filter_date !== '') {
    $where[] = "DATE(attempt_time) = '$filter_date'";
}
$where_sql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total
$count_sql = "SELECT COUNT(*) as total FROM login_attempts $where_sql";
$count_result = mysqli_query($conn, $count_sql);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// Get data
$sql = "SELECT id, ip_address, email, success, attempt_time 
        FROM login_attempts 
        $where_sql 
        ORDER BY attempt_time DESC 
        LIMIT $limit OFFSET $offset";
$data_login = mysqli_query($conn, $sql);

// Statistics
$stats_sql = "SELECT 
    COUNT(*) as total_attempts,
    SUM(success = 1) as successful,
    SUM(success = 0) as failed,
    COUNT(DISTINCT ip_address) as unique_ips
FROM login_attempts
WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_sql));

// Top failed IPs
$top_ips_sql = "SELECT 
    ip_address,
    COUNT(*) as attempts,
    SUM(success = 0) as failed_attempts,
    MAX(attempt_time) as last_attempt
FROM login_attempts
WHERE success = 0
AND attempt_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY ip_address
ORDER BY failed_attempts DESC
LIMIT 5";
$top_ips = mysqli_query($conn, $top_ips_sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Log Login - FixPoint</title>
  <link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <style>
    .login-table {
      font-size: 13px;
      white-space: nowrap;
    }
    .login-table th, .login-table td {
      padding: 6px 10px;
      vertical-align: middle;
    }
    .flash-center {
      position: fixed;
      top: 20%;
      left: 50%;
      transform: translate(-50%, -50%);
      z-index: 1050;
      min-width: 300px;
      max-width: 90%;
      text-align: center;
      padding: 15px;
      border-radius: 8px;
      font-weight: 500;
      box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }
    .stats-card {
      border-left: 4px solid;
      transition: transform 0.2s;
      margin-bottom: 15px;
    }
    .stats-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .badge-success-login {
      background: #28a745;
      color: white;
      padding: 4px 8px;
      border-radius: 3px;
      font-size: 11px;
    }
    .badge-failed-login {
      background: #dc3545;
      color: white;
      padding: 4px 8px;
      border-radius: 3px;
      font-size: 11px;
    }
    .ip-badge {
      font-family: monospace;
      background: #f8f9fa;
      padding: 2px 6px;
      border-radius: 3px;
      border: 1px solid #dee2e6;
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
            <div class="alert alert-info flash-center" id="flashMsg">
              <?= $_SESSION['flash_message'] ?>
            </div>
            <?php unset($_SESSION['flash_message']); ?>
          <?php endif; ?>

          <!-- Statistics Cards -->
          <div class="row">
            <div class="col-lg-3 col-md-6 col-sm-6 col-12">
              <div class="card card-statistic-1 stats-card" style="border-left-color: #6777ef;">
                <div class="card-icon bg-primary">
                  <i class="fas fa-list"></i>
                </div>
                <div class="card-wrap">
                  <div class="card-header">
                    <h4>Total (24h)</h4>
                  </div>
                  <div class="card-body">
                    <?= number_format($stats['total_attempts']) ?>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="col-lg-3 col-md-6 col-sm-6 col-12">
              <div class="card card-statistic-1 stats-card" style="border-left-color: #28a745;">
                <div class="card-icon bg-success">
                  <i class="fas fa-check-circle"></i>
                </div>
                <div class="card-wrap">
                  <div class="card-header">
                    <h4>Berhasil</h4>
                  </div>
                  <div class="card-body">
                    <?= number_format($stats['successful']) ?>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="col-lg-3 col-md-6 col-sm-6 col-12">
              <div class="card card-statistic-1 stats-card" style="border-left-color: #dc3545;">
                <div class="card-icon bg-danger">
                  <i class="fas fa-times-circle"></i>
                </div>
                <div class="card-wrap">
                  <div class="card-header">
                    <h4>Gagal</h4>
                  </div>
                  <div class="card-body">
                    <?= number_format($stats['failed']) ?>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="col-lg-3 col-md-6 col-sm-6 col-12">
              <div class="card card-statistic-1 stats-card" style="border-left-color: #ffc107;">
                <div class="card-icon bg-warning">
                  <i class="fas fa-network-wired"></i>
                </div>
                <div class="card-wrap">
                  <div class="card-header">
                    <h4>Unique IPs</h4>
                  </div>
                  <div class="card-body">
                    <?= number_format($stats['unique_ips']) ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <h4 class="mb-0">Log Login Attempts</h4>
            </div>

            <div class="card-body">
              <!-- Tab Menu -->
              <ul class="nav nav-tabs" id="loginTab" role="tablist">
                <li class="nav-item">
                  <a class="nav-link active" id="data-tab" data-toggle="tab" href="#data" role="tab">Data Login</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" id="filter-tab" data-toggle="tab" href="#filter" role="tab">Filter</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" id="failed-tab" data-toggle="tab" href="#failed" role="tab">IP Gagal</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" id="blocked-tab" data-toggle="tab" href="#blocked" role="tab">IP Terblokir</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" id="tools-tab" data-toggle="tab" href="#tools" role="tab">Tools</a>
                </li>
              </ul>

              <!-- Tab Content -->
              <div class="tab-content mt-3">
                
                <!-- Tab Data Login -->
                <div class="tab-pane fade show active" id="data" role="tabpanel">
                  <div class="table-responsive">
                    <table class="table table-bordered login-table">
                      <thead class="thead-dark">
                        <tr>
                          <th>No</th>
                          <th>Waktu</th>
                          <th>Email</th>
                          <th>IP Address</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (mysqli_num_rows($data_login) > 0): ?>
                          <?php $no = $offset + 1; while ($row = mysqli_fetch_assoc($data_login)) : ?>
                            <tr>
                              <td><?= $no++ ?></td>
                              <td><?= date('d/m/Y H:i:s', strtotime($row['attempt_time'])) ?></td>
                              <td><?= htmlspecialchars($row['email']) ?></td>
                              <td><span class="ip-badge"><?= htmlspecialchars($row['ip_address']) ?></span></td>
                              <td>
                                <?php if ($row['success'] == 1): ?>
                                  <span class="badge-success-login">
                                    <i class="fas fa-check"></i> Berhasil
                                  </span>
                                <?php else: ?>
                                  <span class="badge-failed-login">
                                    <i class="fas fa-times"></i> Gagal
                                  </span>
                                <?php endif; ?>
                              </td>
                            </tr>
                          <?php endwhile; ?>
                        <?php else: ?>
                          <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                              <i class="fas fa-inbox fa-2x mb-2"></i>
                              <p>Tidak ada data</p>
                            </td>
                          </tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>

                  <!-- Pagination -->
                  <?php if ($total_pages > 1): ?>
                    <nav>
                      <ul class="pagination">
                        <?php if ($page > 1): ?>
                          <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&email=<?= urlencode($filter_email) ?>&ip=<?= urlencode($filter_ip) ?>&status=<?= $filter_status ?>&date=<?= $filter_date ?>">
                              <i class="fas fa-chevron-left"></i>
                            </a>
                          </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                          <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&email=<?= urlencode($filter_email) ?>&ip=<?= urlencode($filter_ip) ?>&status=<?= $filter_status ?>&date=<?= $filter_date ?>">
                              <?= $i ?>
                            </a>
                          </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                          <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&email=<?= urlencode($filter_email) ?>&ip=<?= urlencode($filter_ip) ?>&status=<?= $filter_status ?>&date=<?= $filter_date ?>">
                              <i class="fas fa-chevron-right"></i>
                            </a>
                          </li>
                        <?php endif; ?>
                      </ul>
                    </nav>
                    <p class="text-muted">
                      Menampilkan <?= min($offset + 1, $total_records) ?> - <?= min($offset + $limit, $total_records) ?> dari <?= number_format($total_records) ?> data
                    </p>
                  <?php endif; ?>
                </div>

                <!-- Tab Filter -->
                <div class="tab-pane fade" id="filter" role="tabpanel">
                  <form method="GET" action="log_login.php">
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Email</label>
                          <input type="text" name="email" class="form-control" value="<?= htmlspecialchars($filter_email) ?>" placeholder="Cari email...">
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>IP Address</label>
                          <input type="text" name="ip" class="form-control" value="<?= htmlspecialchars($filter_ip) ?>" placeholder="192.168.1.1">
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Status</label>
                          <select name="status" class="form-control">
                            <option value="">Semua Status</option>
                            <option value="1" <?= $filter_status === '1' ? 'selected' : '' ?>>Berhasil</option>
                            <option value="0" <?= $filter_status === '0' ? 'selected' : '' ?>>Gagal</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Tanggal</label>
                          <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
                        </div>
                      </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                      <i class="fas fa-search"></i> Apply Filter
                    </button>
                    <a href="log_login.php" class="btn btn-secondary">
                      <i class="fas fa-undo"></i> Reset
                    </a>
                  </form>
                </div>

                <!-- Tab IP Gagal -->
                <div class="tab-pane fade" id="failed" role="tabpanel">
                  <h5 class="mb-3">Top Failed IPs (24 Jam Terakhir)</h5>
                  <?php if (mysqli_num_rows($top_ips) > 0): ?>
                    <div class="table-responsive">
                      <table class="table table-bordered login-table">
                        <thead class="thead-dark">
                          <tr>
                            <th>No</th>
                            <th>IP Address</th>
                            <th>Percobaan Gagal</th>
                            <th>Terakhir</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php $no = 1; while($ip = mysqli_fetch_assoc($top_ips)): ?>
                            <tr>
                              <td><?= $no++ ?></td>
                              <td><span class="ip-badge"><?= htmlspecialchars($ip['ip_address']) ?></span></td>
                              <td>
                                <span class="badge badge-danger"><?= $ip['failed_attempts'] ?> kali</span>
                              </td>
                              <td><?= date('d/m/Y H:i', strtotime($ip['last_attempt'])) ?></td>
                            </tr>
                          <?php endwhile; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php else: ?>
                    <div class="alert alert-success">
                      <i class="fas fa-check-circle"></i> Tidak ada failed login dalam 24 jam terakhir
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Tab IP Terblokir -->
                <div class="tab-pane fade" id="blocked" role="tabpanel">
                  <h5 class="mb-3">IP Address yang Terblokir Saat Ini</h5>
                  <p class="text-muted">IP yang melakukan 5x percobaan gagal dalam 3 menit terakhir</p>
                  
                  <?php
                  // Get blocked IPs (yang punya >= 5 failed attempts dalam 3 menit)
                  $blocked_sql = "SELECT 
                      ip_address,
                      COUNT(*) as failed_attempts,
                      MAX(attempt_time) as last_attempt,
                      TIMESTAMPDIFF(SECOND, MAX(attempt_time), NOW()) as seconds_ago,
                      180 - TIMESTAMPDIFF(SECOND, MAX(attempt_time), NOW()) as seconds_remaining
                  FROM login_attempts
                  WHERE success = 0
                  AND attempt_time > DATE_SUB(NOW(), INTERVAL 3 MINUTE)
                  GROUP BY ip_address
                  HAVING failed_attempts >= 5
                  ORDER BY last_attempt DESC";
                  $blocked_ips = mysqli_query($conn, $blocked_sql);
                  ?>
                  
                  <?php if (mysqli_num_rows($blocked_ips) > 0): ?>
                    <div class="table-responsive">
                      <table class="table table-bordered login-table">
                        <thead class="thead-dark">
                          <tr>
                            <th>No</th>
                            <th>IP Address</th>
                            <th>Percobaan Gagal</th>
                            <th>Terakhir Coba</th>
                            <th>Sisa Block</th>
                            <th>Aksi</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php $no = 1; while($blocked = mysqli_fetch_assoc($blocked_ips)): ?>
                            <tr>
                              <td><?= $no++ ?></td>
                              <td><span class="ip-badge"><?= htmlspecialchars($blocked['ip_address']) ?></span></td>
                              <td>
                                <span class="badge badge-danger"><?= $blocked['failed_attempts'] ?> kali</span>
                              </td>
                              <td><?= date('d/m/Y H:i:s', strtotime($blocked['last_attempt'])) ?></td>
                              <td>
                                <?php if ($blocked['seconds_remaining'] > 0): ?>
                                  <span class="badge badge-warning">
                                    <?= gmdate("i:s", $blocked['seconds_remaining']) ?> menit
                                  </span>
                                <?php else: ?>
                                  <span class="badge badge-success">Sudah dibuka</span>
                                <?php endif; ?>
                              </td>
                              <td>
                                <button class="btn btn-sm btn-danger" onclick="resetIP('<?= htmlspecialchars($blocked['ip_address']) ?>')">
                                  <i class="fas fa-unlock"></i> Reset
                                </button>
                              </td>
                            </tr>
                          <?php endwhile; ?>
                        </tbody>
                      </table>
                    </div>
                    <div class="alert alert-info mt-3">
                      <i class="fas fa-info-circle"></i> 
                      <strong>Info:</strong> Klik tombol "Reset" untuk membuka blokir IP tertentu. 
                      Atau gunakan "Reset Semua Block" di tab Tools untuk membuka semua sekaligus.
                    </div>
                  <?php else: ?>
                    <div class="alert alert-success">
                      <i class="fas fa-check-circle"></i> Tidak ada IP yang terblokir saat ini
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Tab Tools -->
                <div class="tab-pane fade" id="tools" role="tabpanel">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="card">
                        <div class="card-body">
                          <h6><i class="fas fa-unlock text-success"></i> Reset Semua Block</h6>
                          <p class="text-muted">Buka blokir semua IP yang terblokir</p>
                          <button class="btn btn-success btn-sm" onclick="resetAllBlocks()">
                            <i class="fas fa-unlock"></i> Reset Semua Block
                          </button>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="card">
                        <div class="card-body">
                          <h6><i class="fas fa-trash text-danger"></i> Clear Old Logs</h6>
                          <p class="text-muted">Hapus log login yang lebih dari 30 hari</p>
                          <button class="btn btn-danger btn-sm" onclick="clearOldLogs()">
                            <i class="fas fa-trash"></i> Hapus Log Lama
                          </button>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="card">
                        <div class="card-body">
                          <h6><i class="fas fa-download text-warning"></i> Export Data</h6>
                          <p class="text-muted">Download semua data login dalam format CSV</p>
                          <button class="btn btn-warning btn-sm" onclick="exportLogs()">
                            <i class="fas fa-download"></i> Export ke CSV
                          </button>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="card">
                        <div class="card-body">
                          <h6><i class="fas fa-calendar-day text-info"></i> Quick Access</h6>
                          <p class="text-muted">Lihat log hari ini</p>
                          <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-info btn-sm">
                            <i class="fas fa-calendar-day"></i> Log Hari Ini
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

              </div> <!-- End Tab Content -->
            </div>
          </div>

        </div>
      </section>
    </div>
  </div>
</div>

<!-- JS -->
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
    setTimeout(function() {
      $("#flashMsg").fadeOut("slow");
    }, 3000);
  });

  function clearOldLogs() {
    if (confirm('Hapus semua log login yang lebih dari 30 hari?')) {
      window.location.href = 'log_login_action.php?action=clear_old';
    }
  }

  function exportLogs() {
    window.location.href = 'log_login_action.php?action=export';
  }

  function resetIP(ip) {
    if (confirm('Reset blokir untuk IP: ' + ip + '?')) {
      window.location.href = 'log_login_action.php?action=reset_ip&ip=' + encodeURIComponent(ip);
    }
  }

  function resetAllBlocks() {
    if (confirm('Reset blokir untuk SEMUA IP yang terblokir?')) {
      window.location.href = 'log_login_action.php?action=reset_all_blocks';
    }
  }
</script>

</body>
</html>