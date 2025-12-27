<?php
/**
 * Helper Functions untuk Validasi Cuti
 * File: cuti_validation_helper.php
 * 
 * Cara Penggunaan:
 * include 'cuti_validation_helper.php';
 */

/**
 * Validasi tanggal cuti tidak di masa lalu
 * 
 * @param array $tanggalArray Array tanggal cuti
 * @return array ['valid' => bool, 'message' => string]
 */
function validate_tanggal_not_past($tanggalArray) {
    $today = date('Y-m-d');
    
    foreach ($tanggalArray as $tgl) {
        if ($tgl < $today) {
            return [
                'valid' => false,
                'message' => 'Tanggal cuti tidak boleh di masa lalu ('. date('d-m-Y', strtotime($tgl)) .')'
            ];
        }
    }
    
    return ['valid' => true, 'message' => ''];
}

/**
 * Validasi overlap cuti dengan pengajuan lain
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id ID karyawan
 * @param array $tanggalArray Array tanggal cuti
 * @param int $exclude_id ID pengajuan yang dikecualikan (untuk edit)
 * @return array ['valid' => bool, 'message' => string]
 */
function validate_cuti_overlap($conn, $user_id, $tanggalArray, $exclude_id = 0) {
    // Escape tanggal untuk query
    $tglListEscaped = array_map(function($tgl) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, $tgl) . "'";
    }, $tanggalArray);
    
    $tglList = implode(",", $tglListEscaped);
    
    $sql = "SELECT COUNT(*) as total, 
                   MIN(pd.tanggal) as tgl_overlap
            FROM pengajuan_cuti p
            JOIN pengajuan_cuti_detail pd ON p.id = pd.pengajuan_id
            WHERE p.karyawan_id = ?
              AND pd.tanggal IN ($tglList)
              AND p.status NOT LIKE '%Ditolak%'
              AND p.status NOT LIKE '%Dibatalkan%'
              AND p.id != ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $exclude_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    if ($data['total'] > 0) {
        return [
            'valid' => false,
            'message' => 'Tanggal cuti overlap dengan pengajuan lain pada tanggal ' . 
                        date('d-m-Y', strtotime($data['tgl_overlap']))
        ];
    }
    
    return ['valid' => true, 'message' => ''];
}

/**
 * Validasi sisa cuti mencukupi
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id ID karyawan
 * @param int $cuti_id ID jenis cuti
 * @param int $lama_hari Lama cuti yang diajukan
 * @param int $tahun Tahun cuti
 * @return array ['valid' => bool, 'message' => string, 'sisa' => int]
 */
function validate_sisa_cuti($conn, $user_id, $cuti_id, $lama_hari, $tahun = null) {
    if ($tahun === null) {
        $tahun = date('Y');
    }
    
    $sql = "SELECT sisa_hari FROM jatah_cuti 
            WHERE karyawan_id = ? 
              AND cuti_id = ? 
              AND tahun = ? 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $user_id, $cuti_id, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    if (!$data) {
        return [
            'valid' => false,
            'message' => 'Jatah cuti untuk tahun ' . $tahun . ' belum diinput',
            'sisa' => 0
        ];
    }
    
    $sisa = (int)$data['sisa_hari'];
    
    if ($lama_hari > $sisa) {
        return [
            'valid' => false,
            'message' => "Sisa cuti hanya $sisa hari, tidak cukup untuk mengajukan $lama_hari hari",
            'sisa' => $sisa
        ];
    }
    
    return ['valid' => true, 'message' => '', 'sisa' => $sisa];
}

/**
 * Validasi tanggal cuti tidak di hari libur/weekend (OPTIONAL)
 * 
 * @param mysqli $conn Database connection
 * @param array $tanggalArray Array tanggal cuti
 * @param bool $check_weekend Check weekend atau tidak
 * @return array ['valid' => bool, 'message' => string, 'libur' => array]
 */
function validate_hari_libur($conn, $tanggalArray, $check_weekend = true) {
    $hari_libur = [];
    
    foreach ($tanggalArray as $tgl) {
        // Cek weekend
        if ($check_weekend) {
            $day_of_week = date('N', strtotime($tgl)); // 1=Senin, 7=Minggu
            if ($day_of_week == 6 || $day_of_week == 7) { // Sabtu atau Minggu
                $hari_libur[] = [
                    'tanggal' => $tgl,
                    'nama' => $day_of_week == 6 ? 'Sabtu' : 'Minggu',
                    'type' => 'weekend'
                ];
            }
        }
        
        // Cek hari libur nasional dari database
        $sql = "SELECT nama_libur FROM hari_libur WHERE tanggal = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $tgl);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $libur = $result->fetch_assoc();
            $hari_libur[] = [
                'tanggal' => $tgl,
                'nama' => $libur['nama_libur'],
                'type' => 'nasional'
            ];
        }
    }
    
    if (count($hari_libur) > 0) {
        $msg = "Tanggal cuti tidak bisa di hari libur:<br>";
        foreach ($hari_libur as $libur) {
            $msg .= "- " . date('d-m-Y', strtotime($libur['tanggal'])) . 
                    " (" . $libur['nama'] . ")<br>";
        }
        
        return [
            'valid' => false,
            'message' => $msg,
            'libur' => $hari_libur
        ];
    }
    
    return ['valid' => true, 'message' => '', 'libur' => []];
}

/**
 * Validasi pengajuan cuti pending
 * Cek apakah user masih punya cuti yang statusnya pending
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id ID karyawan
 * @return array ['valid' => bool, 'message' => string]
 */
function validate_no_pending_cuti($conn, $user_id) {
    $sql = "SELECT id, status FROM pengajuan_cuti 
            WHERE karyawan_id = ? 
              AND (
                  status_delegasi = 'Menunggu' 
                  OR status_atasan = 'Menunggu' 
                  OR status_hrd = 'Menunggu'
              )
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return [
            'valid' => false,
            'message' => 'Anda masih memiliki pengajuan cuti yang belum diproses. ' .
                        'Tunggu sampai disetujui atau ditolak sebelum mengajukan lagi.'
        ];
    }
    
    return ['valid' => true, 'message' => ''];
}

/**
 * Validasi alasan cuti
 * 
 * @param string $alasan Alasan cuti
 * @param int $min_length Panjang minimal karakter
 * @return array ['valid' => bool, 'message' => string]
 */
function validate_alasan($alasan, $min_length = 10) {
    $alasan = trim($alasan);
    
    if (empty($alasan)) {
        return [
            'valid' => false,
            'message' => 'Alasan cuti wajib diisi'
        ];
    }
    
    if (strlen($alasan) < $min_length) {
        return [
            'valid' => false,
            'message' => "Alasan cuti minimal $min_length karakter"
        ];
    }
    
    return ['valid' => true, 'message' => ''];
}

/**
 * Validasi complete untuk pengajuan cuti
 * Gabungan semua validasi
 * 
 * @param mysqli $conn Database connection
 * @param array $data Array data pengajuan cuti
 * @return array ['valid' => bool, 'errors' => array]
 */
function validate_pengajuan_cuti($conn, $data) {
    $errors = [];
    
    // Required fields
    $user_id = $data['user_id'] ?? 0;
    $cuti_id = $data['cuti_id'] ?? 0;
    $tanggalArray = $data['tanggal_array'] ?? [];
    $alasan = $data['alasan'] ?? '';
    $tahun = $data['tahun'] ?? date('Y');
    
    // Validasi 1: Field tidak boleh kosong
    if ($user_id <= 0) {
        $errors[] = 'User ID tidak valid';
    }
    
    if ($cuti_id <= 0) {
        $errors[] = 'Jenis cuti harus dipilih';
    }
    
    if (empty($tanggalArray)) {
        $errors[] = 'Tanggal cuti harus diisi';
    }
    
    // Jika ada error basic, return
    if (!empty($errors)) {
        return ['valid' => false, 'errors' => $errors];
    }
    
    // Validasi 2: Alasan
    $val_alasan = validate_alasan($alasan);
    if (!$val_alasan['valid']) {
        $errors[] = $val_alasan['message'];
    }
    
    // Validasi 3: Tanggal tidak di masa lalu
    $val_past = validate_tanggal_not_past($tanggalArray);
    if (!$val_past['valid']) {
        $errors[] = $val_past['message'];
    }
    
    // Validasi 4: No pending cuti
    $val_pending = validate_no_pending_cuti($conn, $user_id);
    if (!$val_pending['valid']) {
        $errors[] = $val_pending['message'];
    }
    
    // Validasi 5: Overlap cuti
    $val_overlap = validate_cuti_overlap($conn, $user_id, $tanggalArray);
    if (!$val_overlap['valid']) {
        $errors[] = $val_overlap['message'];
    }
    
    // Validasi 6: Sisa cuti
    $lama_hari = count($tanggalArray);
    $val_sisa = validate_sisa_cuti($conn, $user_id, $cuti_id, $lama_hari, $tahun);
    if (!$val_sisa['valid']) {
        $errors[] = $val_sisa['message'];
    }
    
    // Validasi 7: Hari libur (optional - bisa dinonaktifkan)
    // $val_libur = validate_hari_libur($conn, $tanggalArray, true);
    // if (!$val_libur['valid']) {
    //     $errors[] = $val_libur['message'];
    // }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}