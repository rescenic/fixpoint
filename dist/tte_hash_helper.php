<?php
/**
 * TTE HASH HELPER - COMPLETE VERSION
 * File ini berisi fungsi-fungsi untuk generate dan validasi hash file
 * Gunakan di halaman generate TTE Anda
 */

/**
 * Generate SHA256 hash dari file
 * @param string $filePath - Path file yang akan di-hash
 * @return string|false - Hash SHA256 atau false jika gagal
 */
function generateFileHash($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }
    return hash_file('sha256', $filePath);
}

/**
 * Save hash file ke database saat TTE dibuat
 * Panggil fungsi ini di halaman generate TTE setelah file selesai dibuat
 * 
 * CONTOH PENGGUNAAN di halaman generate TTE:
 * 
 * // Setelah file PDF/gambar selesai dibuat dan disimpan
 * $outputFile = 'dokumen_dengan_tte.pdf';
 * $token = 'abc123...'; // Token TTE yang baru dibuat
 * 
 * // Generate hash
 * $fileHash = generateFileHash($outputFile);
 * 
 * // Simpan hash ke database
 * saveFileHashToDatabase($conn, $token, $fileHash);
 * 
 * @param mysqli $conn - Koneksi database
 * @param string $token - Token TTE
 * @param string $fileHash - Hash file
 * @return bool - True jika berhasil, false jika gagal
 */
function saveFileHashToDatabase($conn, $token, $fileHash) {
    if (empty($token) || empty($fileHash)) {
        return false;
    }
    
    $stmt = $conn->prepare("UPDATE tte_user SET file_hash = ? WHERE token = ?");
    $stmt->bind_param("ss", $fileHash, $token);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * NEW: Save document signing log with accurate timestamp
 * Simpan log penandatanganan dokumen dengan timestamp yang akurat
 * 
 * @param mysqli $conn - Koneksi database
 * @param string $tte_token - Token TTE yang digunakan
 * @param int $user_id - ID user yang menandatangani
 * @param string $document_name - Nama file dokumen
 * @param string $file_hash - Hash file dokumen
 * @return bool - True jika berhasil, false jika gagal
 */
function saveDocumentSigningLog($conn, $tte_token, $user_id, $document_name, $file_hash) {
    try {
        // Cek apakah tabel tte_document_log ada
        $check_table = $conn->query("SHOW TABLES LIKE 'tte_document_log'");
        
        if ($check_table->num_rows == 0) {
            // Buat tabel jika belum ada
            $create_table = "
            CREATE TABLE IF NOT EXISTS `tte_document_log` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `tte_token` varchar(64) NOT NULL,
              `user_id` int(11) NOT NULL,
              `document_name` varchar(255) NOT NULL,
              `document_hash` varchar(64) NOT NULL,
              `signed_at` datetime NOT NULL,
              `ip_address` varchar(45) DEFAULT NULL,
              `user_agent` text DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_token` (`tte_token`),
              KEY `idx_hash` (`document_hash`),
              KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            $conn->query($create_table);
        }
        
        // Simpan log penandatanganan
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $signed_at = date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("
            INSERT INTO tte_document_log 
            (tte_token, user_id, document_name, document_hash, signed_at, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("sisssss", 
            $tte_token, 
            $user_id, 
            $document_name, 
            $file_hash, 
            $signed_at, 
            $ip_address, 
            $user_agent
        );
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error saving document log: " . $e->getMessage());
        return false;
    }
}

/**
 * Validasi apakah file sudah dimodifikasi
 * @param string $currentFilePath - Path file yang akan dicek
 * @param string $originalHash - Hash asli dari database
 * @return array - ['valid' => bool, 'status' => string, 'message' => string]
 */
function validateFileIntegrity($currentFilePath, $originalHash) {
    $result = [
        'valid' => false,
        'status' => 'unknown',
        'message' => ''
    ];
    
    if (empty($originalHash)) {
        $result['status'] = 'no_hash';
        $result['message'] = 'Hash tidak tersedia (TTE dibuat sebelum sistem hash)';
        $result['valid'] = null; // null = tidak bisa divalidasi
        return $result;
    }
    
    $currentHash = generateFileHash($currentFilePath);
    
    if (!$currentHash) {
        $result['status'] = 'error';
        $result['message'] = 'Gagal membaca file';
        return $result;
    }
    
    if ($currentHash === $originalHash) {
        $result['valid'] = true;
        $result['status'] = 'original';
        $result['message'] = 'File asli, belum dimodifikasi';
    } else {
        $result['valid'] = false;
        $result['status'] = 'modified';
        $result['message'] = 'File telah dimodifikasi/diedit dari aslinya';
    }
    
    return $result;
}

// ============================================================================
// FUNGSI TAMBAHAN UNTUK HALAMAN DOKUMEN_TTE.PHP
// ============================================================================

/**
 * Get documents by user ID dengan pagination
 * Fungsi baru untuk mendukung halaman dokumen_tte.php
 */
function getDocumentsByUserId($conn, $user_id, $limit = 10, $offset = 0, $search = '') {
    try {
        $searchCondition = '';
        $params = [$user_id];
        $types = 'i';
        
        if (!empty($search)) {
            $searchCondition = " AND (dsl.document_name LIKE ? OR dsl.document_hash LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= 'ss';
        }
        
        $sql = "
            SELECT dsl.*, tu.nama, tu.nik, tu.jabatan, tu.instansi, tu.token
            FROM tte_document_log dsl
            JOIN tte_user tu ON dsl.tte_token = tu.token
            WHERE dsl.user_id = ?" . $searchCondition . "
            ORDER BY dsl.signed_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        return $stmt->get_result();
    } catch (Exception $e) {
        error_log("Error getting documents by user: " . $e->getMessage());
        return false;
    }
}

/**
 * Count total documents by user ID
 */
function countDocumentsByUserId($conn, $user_id, $search = '') {
    try {
        $searchCondition = '';
        $params = [$user_id];
        $types = 'i';
        
        if (!empty($search)) {
            $searchCondition = " AND (document_name LIKE ? OR document_hash LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= 'ss';
        }
        
        $sql = "SELECT COUNT(*) as total FROM tte_document_log WHERE user_id = ?" . $searchCondition;
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        $result = $stmt->get_result()->fetch_assoc();
        return $result['total'];
    } catch (Exception $e) {
        error_log("Error counting documents: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get user document statistics
 */
function getUserDocumentStats($conn, $user_id) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_docs,
                COUNT(DISTINCT DATE(signed_at)) as signing_days,
                MIN(signed_at) as first_sign,
                MAX(signed_at) as last_sign
            FROM tte_document_log 
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    } catch (Exception $e) {
        error_log("Error getting user stats: " . $e->getMessage());
        return [
            'total_docs' => 0,
            'signing_days' => 0,
            'first_sign' => null,
            'last_sign' => null
        ];
    }
}

/**
 * Delete document from log
 */
function deleteDocumentLog($conn, $doc_id, $user_id) {
    try {
        $stmt = $conn->prepare("DELETE FROM tte_document_log WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $doc_id, $user_id);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Error deleting document log: " . $e->getMessage());
        return false;
    }
}

/**
 * CARA MODIFIKASI DATABASE:
 * Tambahkan kolom file_hash di tabel tte_user
 * 
 * SQL Query:
 * 
 * ALTER TABLE `tte_user` ADD `file_hash` VARCHAR(64) NULL DEFAULT NULL AFTER `token`;
 * ALTER TABLE `tte_user` ADD INDEX `idx_file_hash` (`file_hash`);
 * 
 * Atau gunakan phpMyAdmin:
 * 1. Buka tabel tte_user
 * 2. Klik tab "Structure"
 * 3. Klik "Add column" setelah kolom token
 * 4. Nama: file_hash
 * 5. Type: VARCHAR
 * 6. Length: 64
 * 7. Default: NULL
 * 8. Nullable: Yes
 * 9. Save
 */
?>