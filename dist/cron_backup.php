<?php
// Jangan tampilkan output (untuk cron)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include koneksi database
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// Folder untuk menyimpan backup
$backup_dir = 'backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Log file
$log_file = $backup_dir . 'backup_log.txt';

// Fungsi untuk menulis log
function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Get database name
$db_result = mysqli_query($conn, "SELECT DATABASE()");
$db_row = mysqli_fetch_row($db_result);
$database_name = $db_row[0];

// Nama file backup
$filename = 'auto_backup_' . $database_name . '_' . date('Y-m-d_H-i-s') . '.sql';
$filepath = $backup_dir . $filename;

writeLog("Memulai backup otomatis...");

try {
    // Buka file untuk menulis
    $handle = fopen($filepath, 'w+');
    if (!$handle) {
        throw new Exception('Gagal membuat file backup');
    }
    
    // Header SQL
    $sql_header = "-- Automatic Database Backup\n";
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
    
    writeLog("Backup " . count($tables) . " tabel...");
    
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
    
    $filesize = filesize($filepath);
    $filesize_kb = round($filesize / 1024, 2);
    
    writeLog("Backup berhasil! File: $filename (${filesize_kb} KB)");
    
    // Hapus backup lama (simpan hanya 30 hari terakhir)
    cleanOldBackups($backup_dir, 30);
    
    // Catat ke database
    recordBackupHistory($conn, $filename, $filesize, 'auto');
    
    // Upload ke Google Drive jika enabled
    $schedule = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM backup_schedule LIMIT 1"));
    if ($schedule && $schedule['gdrive_enabled'] && $schedule['gdrive_folder_id']) {
        writeLog("Mengupload ke Google Drive...");
        
        // Include Google Drive helper
        if (file_exists('gdrive_helper.php')) {
            include 'gdrive_helper.php';
            
            $gdrive_result = uploadToGoogleDrive($filepath, $schedule['gdrive_folder_id']);
            
            if ($gdrive_result['success']) {
                writeLog("Upload ke Google Drive berhasil! File ID: " . $gdrive_result['file_id']);
            } else {
                writeLog("ERROR Google Drive: " . $gdrive_result['message']);
            }
        } else {
            writeLog("WARNING: gdrive_helper.php not found, skipping Google Drive upload");
        }
    }
    
    echo "SUCCESS: Backup completed - $filename";
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage();
}

// Fungsi untuk membersihkan backup lama
function cleanOldBackups($dir, $days = 30) {
    $files = glob($dir . 'auto_backup_*.sql');
    $now = time();
    $deleted = 0;
    
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= 60 * 60 * 24 * $days) {
                unlink($file);
                $deleted++;
                writeLog("Backup lama dihapus: " . basename($file));
            }
        }
    }
    
    if ($deleted > 0) {
        writeLog("Total $deleted backup lama dihapus (>$days hari)");
    }
}

// Fungsi untuk mencatat history backup
function recordBackupHistory($conn, $filename, $filesize, $type) {
    $filename = mysqli_real_escape_string($conn, $filename);
    $type = mysqli_real_escape_string($conn, $type);
    
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'backup_history'");
    if (mysqli_num_rows($table_check) == 0) {
        // Buat tabel jika belum ada
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
    
    // Insert history
    mysqli_query($conn, "INSERT INTO backup_history 
        (filename, filesize, backup_type, status, created_at) 
        VALUES ('$filename', $filesize, '$type', 'success', NOW())");
}

mysqli_close($conn);
?>