<?php
/**
 * =====================================================
 * FIXPOINT TTE - LICENSE VALIDATOR (OFFLINE MODE)
 * =====================================================
 * 
 * Validasi token berdasarkan binding email perusahaan
 * Token untuk 1 email SELALU SAMA (konsisten)
 * Tidak perlu koneksi ke license server (offline mode)
 * 
 * PENTING: SECRET KEY harus SAMA dengan di License.php!
 * =====================================================
 */

// SECRET KEY - HARUS SAMA dengan di localhost/fixlicense/includes/License.php
// GANTI dengan string random Anda sendiri (minimal 32 karakter)
// ⚠️ JANGAN UBAH setelah ada token yang dibuat!
define('LICENSE_SECRET', 'FixPoint2025SecretKey_GantiDenganRandomStringAnda_Min32Karakter');

/**
 * Validasi token OFFLINE (berdasarkan email binding)
 * 
 * Cara kerja:
 * 1. Generate token yang seharusnya untuk email ini
 * 2. Bandingkan dengan token yang diinput
 * 3. Jika cocok = VALID
 * 
 * @param string $token Token yang diinput user
 * @param string $email Email perusahaan
 * @param string|null $domain Domain (opsional)
 * @return array ['success' => bool, 'message' => string, 'data' => array]
 */
function validateLicenseToServer($token, $email, $domain = null) {
    // Normalize input
    $email = strtolower(trim($email));
    $token = strtoupper(trim($token));
    
    // 1. Validasi format token
    if (!preg_match('/^FIXPOINT-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/', $token)) {
        return [
            'success' => false,
            'message' => '❌ Format token tidak valid!<br>Format yang benar: <strong>FIXPOINT-XXXXX-XXXXX-XXXXX</strong><br>(28 karakter, huruf besar semua)'
        ];
    }
    
    // 2. Generate token yang seharusnya untuk email ini
    // ALGORITMA INI HARUS SAMA dengan di License.php!
    $hash = hash_hmac('sha256', $email, LICENSE_SECRET);
    $hashPart = strtoupper(substr($hash, 0, 15));
    $expectedToken = 'FIXPOINT-' . 
                     substr($hashPart, 0, 5) . '-' . 
                     substr($hashPart, 5, 5) . '-' . 
                     substr($hashPart, 10, 5);
    
    // 3. Bandingkan token yang diinput dengan yang seharusnya
    if ($token !== $expectedToken) {
        return [
            'success' => false,
            'message' => '❌ Token tidak cocok dengan email perusahaan Anda!<br><br>' .
                        '<strong>Email Anda:</strong> ' . htmlspecialchars($email) . '<br>' .
                        '<strong>Token ini dibuat untuk email yang berbeda.</strong><br><br>' .
                        'Pastikan:<br>' .
                        '1. Token yang Anda masukkan benar (case-sensitive)<br>' .
                        '2. Email perusahaan di database sama dengan saat pembuatan token<br>' .
                        '3. Tidak ada typo saat copy-paste token'
        ];
    }
    
    // 4. ✅ Token VALID untuk email ini!
    return [
        'success' => true,
        'message' => '✅ Token valid untuk email ' . htmlspecialchars($email),
        'data' => [
            'license_type' => 'lifetime',
            'expires_at' => null,
            'validated_at' => date('Y-m-d H:i:s'),
            'email' => $email,
            'token' => $token,
            'mode' => 'offline'
        ]
    ];
}

/**
 * Simpan license ke database aplikasi
 * 
 * @param mysqli $conn Koneksi database
 * @param int $perusahaanId ID perusahaan
 * @param string $email Email perusahaan
 * @param string $token Token lisensi
 * @param array $licenseData Data dari validasi
 * @return bool|array true jika sukses, array error jika gagal
 */
function saveLicenseToDatabase($conn, $perusahaanId, $email, $token, $licenseData) {
    $email = strtolower(trim($email));
    $licenseType = $licenseData['license_type'] ?? 'lifetime';
    $expiresAt = $licenseData['expires_at'] ?? null;
    
    // 1. Cek apakah tabel tte_licenses ada
    $tableCheck = $conn->query("SHOW TABLES LIKE 'tte_licenses'");
    if ($tableCheck->num_rows === 0) {
        return [
            'success' => false,
            'message' => '❌ ERROR: Tabel <strong>tte_licenses</strong> tidak ditemukan!<br><br>' .
                        'Silakan buat tabel terlebih dahulu dengan menjalankan SQL di phpMyAdmin:<br><br>' .
                        '<textarea readonly style="width:100%; height:200px; font-family:monospace; font-size:11px;">' .
                        getTTELicensesTableSQL() . '</textarea>'
        ];
    }
    
    // 2. Cek apakah email ini sudah pernah aktivasi
    $sql = "SELECT id, perusahaan_id, token, activated_at FROM tte_licenses WHERE email = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return [
            'success' => false,
            'message' => '❌ Database error: ' . $conn->error
        ];
    }
    
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existing) {
        // Email sudah pernah aktivasi
        $activatedDate = date('d/m/Y H:i', strtotime($existing['activated_at']));
        return [
            'success' => false,
            'message' => '❌ Email <strong>' . htmlspecialchars($email) . '</strong> sudah pernah digunakan untuk aktivasi TTE!<br><br>' .
                        '<strong>Detail:</strong><br>' .
                        'Perusahaan ID: ' . $existing['perusahaan_id'] . '<br>' .
                        'Token: ' . htmlspecialchars($existing['token']) . '<br>' .
                        'Tanggal Aktivasi: ' . $activatedDate . '<br><br>' .
                        '<em>Setiap email hanya bisa aktivasi 1 kali.</em>'
        ];
    }
    
    // 3. Cek apakah perusahaan ini sudah punya lisensi
    $sql = "SELECT id, email, token, activated_at FROM tte_licenses WHERE perusahaan_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $perusahaanId);
    $stmt->execute();
    $existingCompany = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existingCompany) {
        // Perusahaan sudah punya lisensi dengan email lain
        $activatedDate = date('d/m/Y H:i', strtotime($existingCompany['activated_at']));
        return [
            'success' => false,
            'message' => '❌ Perusahaan ini sudah memiliki lisensi aktif!<br><br>' .
                        '<strong>Detail lisensi yang sudah ada:</strong><br>' .
                        'Email: ' . htmlspecialchars($existingCompany['email']) . '<br>' .
                        'Token: ' . htmlspecialchars($existingCompany['token']) . '<br>' .
                        'Tanggal Aktivasi: ' . $activatedDate . '<br><br>' .
                        '<em>Jika ingin ganti email, hapus dulu lisensi yang lama di database.</em>'
        ];
    }
    
    // 4. Insert lisensi baru
    $sql = "INSERT INTO tte_licenses 
            (perusahaan_id, email, token, license_type, status, expires_at, activated_at, last_verified) 
            VALUES (?, ?, ?, ?, 'active', ?, NOW(), NOW())";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return [
            'success' => false,
            'message' => '❌ Database error: ' . $conn->error
        ];
    }
    
    $stmt->bind_param('issss', $perusahaanId, $email, $token, $licenseType, $expiresAt);
    $result = $stmt->execute();
    
    if (!$result) {
        $stmt->close();
        return [
            'success' => false,
            'message' => '❌ Gagal menyimpan ke database: ' . $stmt->error
        ];
    }
    
    $stmt->close();
    
    return true; // ✅ Berhasil!
}

/**
 * Cek apakah TTE sudah aktif untuk perusahaan ini
 * 
 * @param mysqli $conn Koneksi database
 * @param int|null $perusahaanId ID perusahaan
 * @return bool true jika aktif, false jika belum
 */
function checkTTELicense($conn, $perusahaanId = null) {
    if (!$perusahaanId && isset($_SESSION['perusahaan_id'])) {
        $perusahaanId = $_SESSION['perusahaan_id'];
    }
    
    if (!$perusahaanId) {
        return false;
    }
    
    // Cek tabel ada atau tidak
    $tableCheck = $conn->query("SHOW TABLES LIKE 'tte_licenses'");
    if ($tableCheck->num_rows === 0) {
        return false;
    }
    
    $sql = "SELECT id FROM tte_licenses 
            WHERE perusahaan_id = ? 
            AND status = 'active' 
            AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('i', $perusahaanId);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasLicense = $result->num_rows > 0;
    $stmt->close();
    
    return $hasLicense;
}

/**
 * Get license info untuk perusahaan
 * 
 * @param mysqli $conn Koneksi database
 * @param int $perusahaanId ID perusahaan
 * @return array|null Data lisensi atau null jika tidak ada
 */
function getTTELicenseInfo($conn, $perusahaanId) {
    $sql = "SELECT * FROM tte_licenses WHERE perusahaan_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $perusahaanId);
    $stmt->execute();
    $result = $stmt->get_result();
    $license = $result->fetch_assoc();
    $stmt->close();
    
    return $license;
}

/**
 * Helper: SQL untuk membuat tabel tte_licenses
 * 
 * @return string SQL query
 */
function getTTELicensesTableSQL() {
    return "CREATE TABLE IF NOT EXISTS `tte_licenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `perusahaan_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(100) NOT NULL,
  `license_type` varchar(20) DEFAULT 'lifetime',
  `status` enum('active','expired','revoked') DEFAULT 'active',
  `activated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `last_verified` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `perusahaan_id` (`perusahaan_id`),
  KEY `token` (`token`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
}

/**
 * Test function - Generate expected token untuk email tertentu
 * Untuk debugging/testing
 * 
 * @param string $email Email yang ingin dites
 * @return string Token yang seharusnya untuk email ini
 */
function testGenerateExpectedToken($email) {
    $email = strtolower(trim($email));
    $hash = hash_hmac('sha256', $email, LICENSE_SECRET);
    $hashPart = strtoupper(substr($hash, 0, 15));
    
    return 'FIXPOINT-' . 
           substr($hashPart, 0, 5) . '-' . 
           substr($hashPart, 5, 5) . '-' . 
           substr($hashPart, 10, 5);
}