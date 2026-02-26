<?php
include 'security.php'; 
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'];
$current_file = basename(__FILE__);

// Cek apakah user boleh mengakses halaman ini
$query = "SELECT 1 FROM akses_menu 
          JOIN menu ON akses_menu.menu_id = menu.id 
          WHERE akses_menu.user_id = '$user_id' AND menu.file_menu = '$current_file'";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) == 0) {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini.'); window.location.href='dashboard.php';</script>";
  exit;
}

// Folder untuk menyimpan backup
$backup_dir = 'backups/';
if (!is_dir($backup_dir)) {
  mkdir($backup_dir, 0755, true);
}

// Get database name
$db_result = mysqli_query($conn, "SELECT DATABASE()");
$db_row = mysqli_fetch_row($db_result);
$database_name = $db_row[0];

// Cek apakah tabel backup_schedule ada
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'backup_schedule'");
if (mysqli_num_rows($table_check) == 0) {
  // Buat tabel jika belum ada
  mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `backup_schedule` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `is_enabled` tinyint(1) DEFAULT 0,
    `schedule_time` time DEFAULT '08:00:00',
    `schedule_type` enum('daily','weekly','monthly') DEFAULT 'daily',
    `retention_days` int(11) DEFAULT 30,
    `gdrive_enabled` tinyint(1) DEFAULT 0,
    `gdrive_folder_id` varchar(255) DEFAULT NULL,
    `last_run` datetime DEFAULT NULL,
    `next_run` datetime DEFAULT NULL,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  // Insert default
  mysqli_query($conn, "INSERT INTO `backup_schedule` (`is_enabled`, `schedule_time`, `schedule_type`, `retention_days`, `gdrive_enabled`) 
    VALUES (0, '08:00:00', 'daily', 30, 0)");
} else {
  // Cek dan tambahkan kolom gdrive jika belum ada
  $columns = mysqli_query($conn, "SHOW COLUMNS FROM backup_schedule LIKE 'gdrive_enabled'");
  if (mysqli_num_rows($columns) == 0) {
    mysqli_query($conn, "ALTER TABLE backup_schedule ADD COLUMN gdrive_enabled tinyint(1) DEFAULT 0 AFTER retention_days");
    mysqli_query($conn, "ALTER TABLE backup_schedule ADD COLUMN gdrive_folder_id varchar(255) DEFAULT NULL AFTER gdrive_enabled");
  }
}

// Cek tabel backup_history
$table_check2 = mysqli_query($conn, "SHOW TABLES LIKE 'backup_history'");
if (mysqli_num_rows($table_check2) == 0) {
  mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `backup_history` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `filename` varchar(255) NOT NULL,
    `filesize` bigint(20) NOT NULL,
    `backup_type` enum('manual','auto') DEFAULT 'manual',
    `status` enum('success','failed') DEFAULT 'success',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Ambil pengaturan backup otomatis
$schedule = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM backup_schedule LIMIT 1"));

// Proses Backup Database
if (isset($_POST['backup'])) {
  $filename = 'backup_' . $database_name . '_' . date('Y-m-d_H-i-s') . '.sql';
  $filepath = $backup_dir . $filename;
  
  // Buka file untuk menulis
  $handle = fopen($filepath, 'w+');
  if (!$handle) {
    echo "<script>alert('Gagal membuat file backup!'); window.location.href='backup_restore.php';</script>";
    exit;
  }
  
  // Header SQL
  $sql_header = "-- Database Backup\n";
  $sql_header .= "-- Database: {$database_name}\n";
  $sql_header .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
  $sql_header .= "-- ================================================\n\n";
  $sql_header .= "SET FOREIGN_KEY_CHECKS=0;\n";
  $sql_header .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
  $sql_header .= "SET time_zone = \"+00:00\";\n\n";
  
  fwrite($handle, $sql_header);
  
  // Ambil semua tabel
  $tables = array();
  $result_tables = mysqli_query($conn, "SHOW TABLES");
  while ($row = mysqli_fetch_row($result_tables)) {
    $tables[] = $row[0];
  }
  
  // Backup setiap tabel
  foreach ($tables as $table) {
    // DROP TABLE
    $drop = "-- \n-- Drop table: {$table}\n--\n\n";
    $drop .= "DROP TABLE IF EXISTS `{$table}`;\n\n";
    fwrite($handle, $drop);
    
    // CREATE TABLE
    $create_result = mysqli_query($conn, "SHOW CREATE TABLE `{$table}`");
    $create_row = mysqli_fetch_row($create_result);
    $create = "-- \n-- Structure for table: {$table}\n--\n\n";
    $create .= $create_row[1] . ";\n\n";
    fwrite($handle, $create);
    
    // INSERT DATA
    $data_result = mysqli_query($conn, "SELECT * FROM `{$table}`");
    $num_rows = mysqli_num_rows($data_result);
    
    if ($num_rows > 0) {
      $insert = "-- \n-- Data for table: {$table}\n--\n\n";
      fwrite($handle, $insert);
      
      while ($row = mysqli_fetch_assoc($data_result)) {
        $values = array();
        foreach ($row as $value) {
          if (is_null($value)) {
            $values[] = "NULL";
          } else {
            $value = mysqli_real_escape_string($conn, $value);
            $values[] = "'{$value}'";
          }
        }
        $insert_query = "INSERT INTO `{$table}` VALUES (" . implode(", ", $values) . ");\n";
        fwrite($handle, $insert_query);
      }
      fwrite($handle, "\n");
    }
  }
  
  // Footer
  $sql_footer = "SET FOREIGN_KEY_CHECKS=1;\n";
  fwrite($handle, $sql_footer);
  
  fclose($handle);
  
  // Catat ke history
  $filesize = filesize($filepath);
  $filename_esc = mysqli_real_escape_string($conn, $filename);
  mysqli_query($conn, "INSERT INTO backup_history (filename, filesize, backup_type, status) 
    VALUES ('$filename_esc', $filesize, 'manual', 'success')");
  
  echo "<script>alert('Backup berhasil! File: $filename'); window.location.href='backup_restore.php';</script>";
  exit;
}

// Proses Update Schedule
if (isset($_POST['update_schedule'])) {
  $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
  $schedule_time = mysqli_real_escape_string($conn, $_POST['schedule_time']);
  $schedule_type = mysqli_real_escape_string($conn, $_POST['schedule_type']);
  $retention_days = intval($_POST['retention_days']);
  $gdrive_enabled = isset($_POST['gdrive_enabled']) ? 1 : 0;
  $gdrive_folder_id = mysqli_real_escape_string($conn, $_POST['gdrive_folder_id']);
  
  mysqli_query($conn, "UPDATE backup_schedule SET 
    is_enabled = $is_enabled,
    schedule_time = '$schedule_time',
    schedule_type = '$schedule_type',
    retention_days = $retention_days,
    gdrive_enabled = $gdrive_enabled,
    gdrive_folder_id = '$gdrive_folder_id'
    WHERE id = 1");
  
  echo "<script>alert('Pengaturan backup otomatis berhasil disimpan!'); window.location.href='backup_restore.php';</script>";
  exit;
}

// Proses Restore Database
if (isset($_POST['restore'])) {
  if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] == 0) {
    $file_tmp = $_FILES['sql_file']['tmp_name'];
    $file_name = $_FILES['sql_file']['name'];
    $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
    
    if ($file_ext != 'sql') {
      echo "<script>alert('Hanya file .sql yang diperbolehkan!'); window.location.href='backup_restore.php';</script>";
      exit;
    } else {
      // Baca isi file SQL
      $sql_content = file_get_contents($file_tmp);
      
      // Split query
      $queries = array();
      $current_query = '';
      $lines = explode("\n", $sql_content);
      
      foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip komentar
        if (empty($line) || substr($line, 0, 2) == '--') {
          continue;
        }
        
        $current_query .= $line . ' ';
        
        // Cek apakah query sudah selesai
        if (substr(trim($line), -1) == ';') {
          $queries[] = trim($current_query);
          $current_query = '';
        }
      }
      
      // Eksekusi query
      $success_count = 0;
      $error_count = 0;
      
      foreach ($queries as $query) {
        if (!empty($query)) {
          if (mysqli_query($conn, $query)) {
            $success_count++;
          } else {
            $error_count++;
          }
        }
      }
      
      if ($error_count > 0) {
        echo "<script>alert('Restore selesai dengan $error_count error'); window.location.href='backup_restore.php';</script>";
      } else {
        echo "<script>alert('Restore berhasil! ($success_count query dieksekusi)'); window.location.href='backup_restore.php';</script>";
      }
    }
  }
}

// Proses Hapus File Backup
if (isset($_GET['delete'])) {
  $file = basename($_GET['delete']);
  $filepath = $backup_dir . $file;
  
  if (file_exists($filepath)) {
    unlink($filepath);
    echo "<script>alert('File backup berhasil dihapus!'); window.location.href='backup_restore.php';</script>";
  } else {
    echo "<script>alert('File tidak ditemukan!'); window.location.href='backup_restore.php';</script>";
  }
  exit;
}

// Ambil daftar file backup
$backup_files = array();
if (is_dir($backup_dir)) {
  $files = scandir($backup_dir);
  foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
      // Cek apakah auto atau manual backup
      $backup_type = (strpos($file, 'auto_backup') !== false) ? 'auto' : 'manual';
      
      $backup_files[] = array(
        'name' => $file,
        'size' => filesize($backup_dir . $file),
        'date' => date('Y-m-d H:i:s', filemtime($backup_dir . $file)),
        'type' => $backup_type
      );
    }
  }
  // Urutkan berdasarkan tanggal terbaru
  usort($backup_files, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
  });
}

// Format bytes
function formatBytes($bytes, $precision = 2) {
  $units = array('B', 'KB', 'MB', 'GB');
  $bytes = max($bytes, 0);
  $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
  $pow = min($pow, count($units) - 1);
  $bytes /= (1 << (10 * $pow));
  return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <title>Backup Database - f.i.x.p.o.i.n.t</title>
  <link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css" />
  <link rel="stylesheet" href="assets/modules/datatables/datatables.min.css">
  <link rel="stylesheet" href="assets/modules/datatables/DataTables-1.10.16/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/components.css" />
  <style>
    .card-statistic-1 {
      transition: all 0.3s;
    }
    .card-statistic-1:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    .card-statistic-1 .card-icon {
      width: 70px;
      height: 70px;
      line-height: 70px;
      font-size: 28px;
    }
    .badge {
      font-size: 11px;
      padding: 5px 10px;
    }
    .table-responsive {
      border-radius: 5px;
    }
    .btn-sm {
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

      <!-- Main Content -->
      <div class="main-content">
        <section class="section">
          <div class="section-header">
            <h1><i class="fas fa-database"></i> Backup & Restore Database</h1>
            <div class="section-header-breadcrumb">
              <div class="breadcrumb-item">Database: <strong><?= htmlspecialchars($database_name) ?></strong></div>
              <div class="breadcrumb-item">Total: <strong><?= count($backup_files) ?></strong> file</div>
            </div>
          </div>

          <div class="section-body">
            
            <!-- Backup & Restore Actions -->
            <div class="row">
              <div class="col-lg-4 col-md-6">
                <div class="card card-statistic-1">
                  <div class="card-icon bg-success">
                    <i class="fas fa-download"></i>
                  </div>
                  <div class="card-wrap">
                    <div class="card-header">
                      <h4>Backup Manual</h4>
                    </div>
                    <div class="card-body">
                      <form method="POST" class="d-inline">
                        <button type="submit" name="backup" class="btn btn-success btn-block" onclick="return confirm('Backup database sekarang?')">
                          <i class="fas fa-database"></i> Backup Sekarang
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-lg-4 col-md-6">
                <div class="card card-statistic-1">
                  <div class="card-icon bg-warning">
                    <i class="fas fa-upload"></i>
                  </div>
                  <div class="card-wrap">
                    <div class="card-header">
                      <h4>Restore Database</h4>
                    </div>
                    <div class="card-body">
                      <button type="button" class="btn btn-warning btn-block btn-sm" data-toggle="modal" data-target="#modalRestore">
                        <i class="fas fa-upload"></i> Pilih File & Restore
                      </button>
                      <small class="text-muted d-block mt-2">
                      </small>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-lg-4 col-md-12">
                <div class="card card-statistic-1">
                  <div class="card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-robot"></i>
                  </div>
                  <div class="card-wrap">
                    <div class="card-header">
                      <h4>Backup Otomatis</h4>
                    </div>
                    <div class="card-body">
                      <?php if ($schedule['is_enabled']): ?>
                        <span class="badge badge-success mb-2"><i class="fas fa-check-circle"></i> Aktif</span>
                        <p class="mb-0 text-muted small">
                          <i class="far fa-clock"></i> <?= date('H:i', strtotime($schedule['schedule_time'])) ?> 
                          (<?= ucfirst($schedule['schedule_type']) ?>)
                        </p>
                      <?php else: ?>
                        <span class="badge badge-secondary mb-2"><i class="fas fa-times-circle"></i> Nonaktif</span>
                        <p class="mb-0 text-muted small">Backup otomatis tidak aktif</p>
                      <?php endif; ?>
                      <button type="button" class="btn btn-primary btn-block btn-sm mt-2" data-toggle="modal" data-target="#modalAutoBackup">
                        <i class="fas fa-cog"></i> Pengaturan
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Daftar File Backup -->
            <div class="row">
              <div class="col-12">
                <div class="card">
                  <div class="card-header">
                    <h4>Daftar File Backup</h4>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive">
                      <table class="table table-striped" id="tableBackup">
                        <thead>
                          <tr>
                            <th width="5%">No</th>
                            <th>Nama File</th>
                            <th width="10%">Tipe</th>
                            <th width="12%">Ukuran</th>
                            <th width="18%">Tanggal</th>
                            <th width="18%" class="text-center">Aksi</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php if (empty($backup_files)): ?>
                            <tr>
                              <td colspan="6" class="text-center">
                                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                <p>Belum ada file backup</p>
                              </td>
                            </tr>
                          <?php else: ?>
                            <?php 
                            $no = 1;
                            foreach ($backup_files as $file): 
                            ?>
                              <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                  <i class="fas fa-file-code text-primary"></i> 
                                  <?= htmlspecialchars($file['name']) ?>
                                </td>
                                <td>
                                  <?php if ($file['type'] == 'auto'): ?>
                                    <span class="badge badge-success"><i class="fas fa-robot"></i> Auto</span>
                                  <?php else: ?>
                                    <span class="badge badge-info"><i class="fas fa-hand-pointer"></i> Manual</span>
                                  <?php endif; ?>
                                </td>
                                <td><?= formatBytes($file['size']) ?></td>
                                <td><?= date('d M Y H:i', strtotime($file['date'])) ?></td>
                                <td class="text-center">
                                  <a href="<?= $backup_dir . $file['name'] ?>" class="btn btn-primary btn-sm" download>
                                    <i class="fas fa-download"></i>
                                  </a>
                                  <a href="?delete=<?= urlencode($file['name']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus file ini?')">
                                    <i class="fas fa-trash"></i>
                                  </a>
                                </td>
                              </tr>
                            <?php endforeach; ?>
                          <?php endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </section>
      </div>
    </div>
  </div>

  <!-- Modal Restore Database -->
  <div class="modal fade" id="modalRestore" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header bg-warning text-white">
          <h5 class="modal-title">
            <i class="fas fa-upload"></i> Restore Database
          </h5>
          <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>
        <form method="POST" enctype="multipart/form-data">
          <div class="modal-body">
            <div class="alert alert-danger">
              <i class="fas fa-exclamation-triangle"></i> 
              <strong>PERHATIAN!</strong><br>
              Restore akan menimpa SEMUA data yang ada di database saat ini. Pastikan Anda sudah backup terlebih dahulu!
            </div>
            <div class="form-group">
              <label><i class="fas fa-file-code"></i> Pilih File SQL</label>
              <div class="custom-file">
                <input type="file" name="sql_file" class="custom-file-input" id="sqlFile" accept=".sql" required>
                <label class="custom-file-label" for="sqlFile">Pilih file...</label>
              </div>
              <small class="text-muted">Format: .sql (dari backup manual atau auto)</small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">
              <i class="fas fa-times"></i> Batal
            </button>
            <button type="submit" name="restore" class="btn btn-warning" onclick="return confirm('Yakin ingin restore? Data lama akan terhapus!')">
              <i class="fas fa-upload"></i> Restore Sekarang
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Pengaturan Backup Otomatis -->
  <div class="modal fade" id="modalAutoBackup" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fas fa-robot"></i> Pengaturan Backup Otomatis
          </h5>
          <button type="button" class="close" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label><i class="fas fa-toggle-on"></i> Status</label>
                  <div class="custom-control custom-switch custom-switch-on-success">
                    <input type="checkbox" class="custom-control-input" id="is_enabled_modal" name="is_enabled" <?= $schedule['is_enabled'] ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="is_enabled_modal">Aktifkan Backup Otomatis</label>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label><i class="far fa-clock"></i> Jadwal Waktu</label>
                  <input type="time" name="schedule_time" class="form-control" value="<?= $schedule['schedule_time'] ?>" required>
                  <small class="text-muted">Format 24 jam (misal: 08:00)</small>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label><i class="fas fa-calendar-alt"></i> Frekuensi</label>
                  <select name="schedule_type" class="form-control">
                    <option value="daily" <?= $schedule['schedule_type'] == 'daily' ? 'selected' : '' ?>>Harian</option>
                    <option value="weekly" <?= $schedule['schedule_type'] == 'weekly' ? 'selected' : '' ?>>Mingguan (Setiap Senin)</option>
                    <option value="monthly" <?= $schedule['schedule_type'] == 'monthly' ? 'selected' : '' ?>>Bulanan (Tanggal 1)</option>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label><i class="fas fa-trash-alt"></i> Simpan Backup</label>
                  <div class="input-group">
                    <input type="number" name="retention_days" class="form-control" value="<?= $schedule['retention_days'] ?>" min="1" max="365" required>
                    <div class="input-group-append">
                      <span class="input-group-text">Hari</span>
                    </div>
                  </div>
                  <small class="text-muted">Backup lebih dari ini akan dihapus otomatis</small>
                </div>
              </div>
            </div>
            
            <!-- Google Drive Settings -->
            <div class="row">
              <div class="col-12">
                <hr>
                <h6><i class="fab fa-google-drive"></i> Upload Otomatis ke Google Drive</h6>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label><i class="fas fa-cloud-upload-alt"></i> Auto Upload ke GDrive</label>
                  <div class="custom-control custom-switch custom-switch-on-primary">
                    <input type="checkbox" class="custom-control-input" id="gdrive_enabled" name="gdrive_enabled" <?= isset($schedule['gdrive_enabled']) && $schedule['gdrive_enabled'] ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="gdrive_enabled">Upload backup ke Google Drive</label>
                  </div>
                  <small class="text-muted">Backup akan otomatis diupload ke GDrive setelah dibuat</small>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label><i class="fas fa-folder"></i> Folder ID Google Drive</label>
                  <input type="text" name="gdrive_folder_id" class="form-control" value="<?= $schedule['gdrive_folder_id'] ?? '' ?>" placeholder="1A2B3C4D5E6F7G8H9I0J">
                  <small class="text-muted">ID folder tujuan di Google Drive</small>
                </div>
              </div>
            </div>
            
            <div class="alert alert-info">
              <h6><i class="fas fa-info-circle"></i> Informasi Penting:</h6>
              <ul class="mb-0 pl-3">
                <li>Backup otomatis memerlukan <strong>Cron Job</strong> di server</li>
                <li>File backup otomatis akan diberi prefix <code>auto_backup_</code></li>
                <li>Backup lama akan dihapus otomatis sesuai pengaturan retention</li>
                <li>Upload ke Google Drive memerlukan setup Service Account (lihat dokumentasi)</li>
                <?php if ($schedule['last_run']): ?>
                  <li>Backup terakhir: <strong><?= date('d M Y H:i', strtotime($schedule['last_run'])) ?></strong></li>
                <?php endif; ?>
              </ul>
            </div>
            
            <div class="alert alert-warning">
              <strong><i class="fas fa-terminal"></i> Setup Cron Job:</strong><br>
              <code>0 8 * * * php <?= realpath('cron_backup.php') ?></code>
              <br><small class="text-muted">Atau lihat dokumentasi untuk panduan lengkap</small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">
              <i class="fas fa-times"></i> Batal
            </button>
            <button type="submit" name="update_schedule" class="btn btn-primary">
              <i class="fas fa-save"></i> Simpan Pengaturan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- JS Scripts -->
  <script src="assets/modules/jquery.min.js"></script>
  <script src="assets/modules/popper.js"></script>
  <script src="assets/modules/bootstrap/js/bootstrap.min.js"></script>
  <script src="assets/modules/nicescroll/jquery.nicescroll.min.js"></script>
  <script src="assets/modules/moment.min.js"></script>
  <script src="assets/modules/datatables/datatables.min.js"></script>
  <script src="assets/modules/datatables/DataTables-1.10.16/js/dataTables.bootstrap4.min.js"></script>
  <script src="assets/js/stisla.js"></script>
  <script src="assets/js/scripts.js"></script>
  <script src="assets/js/custom.js"></script>

  <script>
    $(document).ready(function() {
      $('#tableBackup').DataTable({
        "language": {
          "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
        },
        "order": [[4, "desc"]]
      });

      // Custom file input label
      $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
      });
    });
  </script>

</body>
</html>