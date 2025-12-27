<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'koneksi.php';

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    header("Location: login.php");
    exit;
}

$current_file = basename(__FILE__);
$query = "SELECT 1 FROM akses_menu 
          JOIN menu ON akses_menu.menu_id = menu.id 
          WHERE akses_menu.user_id = ? AND menu.file_menu = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("is", $user_id, $current_file);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
    header("Location: dashboard.php");
    exit;
}

// Handle send email
if (isset($_POST['send_email']) && isset($_POST['doc_id']) && isset($_POST['email_recipients'])) {
    $doc_id = intval($_POST['doc_id']);
    $recipients = $_POST['email_recipients'];
    
    // Get document info (tanpa filter user_id karena ini semua dokumen)
    $qDoc = $conn->prepare("
        SELECT dsl.*, tu.nama as penandatangan_nama 
        FROM tte_document_log dsl
        JOIN tte_user tu ON dsl.tte_token = tu.token
        WHERE dsl.id = ?
    ");
    $qDoc->bind_param("i", $doc_id);
    $qDoc->execute();
    $docData = $qDoc->get_result()->fetch_assoc();
    
    if ($docData && !empty($recipients)) {
        $mail_setting = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM mail_settings LIMIT 1"));
        
        if ($mail_setting) {
            $mail = new PHPMailer(true);
            
            try {
                $mail->isSMTP();
                $mail->Host = $mail_setting['mail_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $mail_setting['mail_username'];
                $mail->Password = $mail_setting['mail_password'];
                $mail->SMTPSecure = 'tls';
                $mail->Port = $mail_setting['mail_port'];
                
                $mail->setFrom($mail_setting['mail_from_email'], $mail_setting['mail_from_name']);
                
                foreach ($recipients as $recipient_email) {
                    $mail->addAddress($recipient_email);
                }
                
                $file_path = __DIR__ . '/uploads/signed/' . $docData['document_name'];
                if (file_exists($file_path)) {
                    $mail->addAttachment($file_path, $docData['document_name']);
                }
                
                $mail->isHTML(true);
                $mail->Subject = 'Dokumen TTE: ' . $docData['document_name'];
                
                $emailBody = '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background: #6777ef; color: white; padding: 20px; border-radius: 8px 8px 0 0;">
                        <h2 style="margin: 0;">📄 Dokumen Bertanda Tangan Elektronik</h2>
                    </div>
                    <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px;">
                        <p style="font-size: 16px; color: #333;">Assalamualaikum warahmatullahi wabarakatuh,</p>
                        <p style="color: #666;">Anda menerima dokumen yang telah ditandatangani secara elektronik:</p>
                        
                        <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #6777ef;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 8px 0; color: #666; width: 40%;">📁 Nama File:</td>
                                    <td style="padding: 8px 0; font-weight: bold;">' . htmlspecialchars($docData['document_name']) . '</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #666;">✍️ Ditandatangani oleh:</td>
                                    <td style="padding: 8px 0; font-weight: bold;">' . htmlspecialchars($docData['penandatangan_nama']) . '</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #666;">📅 Tanggal:</td>
                                    <td style="padding: 8px 0; font-weight: bold;">' . date('d F Y, H:i', strtotime($docData['signed_at'])) . ' WIB</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #666;">🔐 Hash:</td>
                                    <td style="padding: 8px 0; font-family: monospace; font-size: 11px;">' . substr($docData['document_hash'], 0, 32) . '...</td>
                                </tr>
                            </table>
                        </div>
                        
                        <p style="color: #666; font-size: 14px; margin-top: 20px;">
                            <strong>Catatan:</strong> Dokumen ini telah ditandatangani secara elektronik dan dilindungi dengan hash SHA-256. 
                            Jangan memodifikasi dokumen ini agar tanda tangan tetap valid.
                        </p>
                        
                        <p style="color: #666;">Wassalamualaikum warahmatullahi wabarakatuh.</p>
                        
                        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #999; font-size: 12px;">
                            Email otomatis dari Sistem TTE - Jangan balas email ini
                        </div>
                    </div>
                </div>
                ';
                
                $mail->Body = $emailBody;
                $mail->send();
                $_SESSION['success_message'] = "Email berhasil dikirim ke " . count($recipients) . " penerima";
                
            } catch (Exception $e) {
                $_SESSION['error_message'] = "Gagal mengirim email: " . $mail->ErrorInfo;
            }
        } else {
            $_SESSION['error_message'] = "Pengaturan SMTP belum dikonfigurasi";
        }
    }
    
    header("Location: dokumen_tte_semua.php");
    exit;
}

// Handle delete (hanya admin/superuser yang bisa hapus semua dokumen)
if (isset($_POST['delete_doc']) && isset($_POST['doc_id'])) {
    $doc_id = intval($_POST['doc_id']);
    
    // Tanpa filter user_id karena ini semua dokumen
    $qFile = $conn->prepare("SELECT document_name FROM tte_document_log WHERE id = ?");
    $qFile->bind_param("i", $doc_id);
    $qFile->execute();
    $fileData = $qFile->get_result()->fetch_assoc();
    
    if ($fileData) {
        $file_path = __DIR__ . '/uploads/signed/' . $fileData['document_name'];
        
        $qDelete = $conn->prepare("DELETE FROM tte_document_log WHERE id = ?");
        $qDelete->bind_param("i", $doc_id);
        
        if ($qDelete->execute()) {
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
            $_SESSION['success_message'] = "Dokumen berhasil dihapus";
        } else {
            $_SESSION['error_message'] = "Gagal menghapus dokumen";
        }
    }
    
    header("Location: dokumen_tte_semua.php");
    exit;
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchParam = '';
if (!empty($search)) {
    $searchParam = '%' . $search . '%';
}

// Filter by user (optional)
$filter_user = isset($_GET['filter_user']) ? intval($_GET['filter_user']) : 0;

// Count documents - SEMUA DOKUMEN (tanpa filter user_id)
if (!empty($search)) {
    if ($filter_user > 0) {
        $qCount = $conn->prepare("SELECT COUNT(*) as total FROM tte_document_log WHERE user_id = ? AND (document_name LIKE ? OR document_hash LIKE ?)");
        $qCount->bind_param("iss", $filter_user, $searchParam, $searchParam);
    } else {
        $qCount = $conn->prepare("SELECT COUNT(*) as total FROM tte_document_log WHERE (document_name LIKE ? OR document_hash LIKE ?)");
        $qCount->bind_param("ss", $searchParam, $searchParam);
    }
} else {
    if ($filter_user > 0) {
        $qCount = $conn->prepare("SELECT COUNT(*) as total FROM tte_document_log WHERE user_id = ?");
        $qCount->bind_param("i", $filter_user);
    } else {
        $qCount = $conn->prepare("SELECT COUNT(*) as total FROM tte_document_log");
    }
}
$qCount->execute();
$totalDocs = $qCount->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalDocs / $limit);

// Get documents - SEMUA DOKUMEN dengan info user yang upload
if (!empty($search)) {
    if ($filter_user > 0) {
        $qDocs = $conn->prepare("
            SELECT dsl.*, 
                   tu.nama as penandatangan_nama, 
                   tu.nik as penandatangan_nik, 
                   tu.jabatan as penandatangan_jabatan,
                   u.nama as uploader_nama,
                   u.nik as uploader_nik
            FROM tte_document_log dsl
            JOIN tte_user tu ON dsl.tte_token = tu.token
            JOIN users u ON dsl.user_id = u.id
            WHERE dsl.user_id = ? AND (dsl.document_name LIKE ? OR dsl.document_hash LIKE ?)
            ORDER BY dsl.signed_at DESC
            LIMIT ? OFFSET ?
        ");
        $qDocs->bind_param("issii", $filter_user, $searchParam, $searchParam, $limit, $offset);
    } else {
        $qDocs = $conn->prepare("
            SELECT dsl.*, 
                   tu.nama as penandatangan_nama, 
                   tu.nik as penandatangan_nik, 
                   tu.jabatan as penandatangan_jabatan,
                   u.nama as uploader_nama,
                   u.nik as uploader_nik
            FROM tte_document_log dsl
            JOIN tte_user tu ON dsl.tte_token = tu.token
            JOIN users u ON dsl.user_id = u.id
            WHERE dsl.document_name LIKE ? OR dsl.document_hash LIKE ?
            ORDER BY dsl.signed_at DESC
            LIMIT ? OFFSET ?
        ");
        $qDocs->bind_param("ssii", $searchParam, $searchParam, $limit, $offset);
    }
} else {
    if ($filter_user > 0) {
        $qDocs = $conn->prepare("
            SELECT dsl.*, 
                   tu.nama as penandatangan_nama, 
                   tu.nik as penandatangan_nik, 
                   tu.jabatan as penandatangan_jabatan,
                   u.nama as uploader_nama,
                   u.nik as uploader_nik
            FROM tte_document_log dsl
            JOIN tte_user tu ON dsl.tte_token = tu.token
            JOIN users u ON dsl.user_id = u.id
            WHERE dsl.user_id = ?
            ORDER BY dsl.signed_at DESC
            LIMIT ? OFFSET ?
        ");
        $qDocs->bind_param("iii", $filter_user, $limit, $offset);
    } else {
        $qDocs = $conn->prepare("
            SELECT dsl.*, 
                   tu.nama as penandatangan_nama, 
                   tu.nik as penandatangan_nik, 
                   tu.jabatan as penandatangan_jabatan,
                   u.nama as uploader_nama,
                   u.nik as uploader_nik
            FROM tte_document_log dsl
            JOIN tte_user tu ON dsl.tte_token = tu.token
            JOIN users u ON dsl.user_id = u.id
            ORDER BY dsl.signed_at DESC
            LIMIT ? OFFSET ?
        ");
        $qDocs->bind_param("ii", $limit, $offset);
    }
}
$qDocs->execute();
$documents = $qDocs->get_result();

// Get statistics - SEMUA DOKUMEN
$qStats = $conn->prepare("
    SELECT 
        COUNT(*) as total_docs,
        COUNT(DISTINCT user_id) as total_users,
        COUNT(DISTINCT DATE(signed_at)) as signing_days,
        MIN(signed_at) as first_sign,
        MAX(signed_at) as last_sign
    FROM tte_document_log
");
$qStats->execute();
$stats = $qStats->get_result()->fetch_assoc();

// Get all users for email modal
$qUsers = $conn->query("SELECT id, nama, email FROM users WHERE email IS NOT NULL AND email != '' ORDER BY nama ASC");

// Get all users for filter dropdown
$qAllUsers = $conn->query("SELECT DISTINCT u.id, u.nama FROM users u JOIN tte_document_log dsl ON u.id = dsl.user_id ORDER BY u.nama ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Semua Dokumen TTE</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.stats-card {
    background: white;
    border-radius: 8px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-left: 3px solid #6777ef;
}
.stats-card h3 {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0;
    color: #2d3748;
}
.stats-card p {
    margin: 0.25rem 0 0 0;
    color: #718096;
    font-size: 0.875rem;
}
.search-box {
    background: white;
    border-radius: 8px;
    padding: 1.25rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.card {
    border: none;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-radius: 8px;
}
.card-header {
    background: white;
    border-bottom: 1px solid #e2e8f0;
    padding: 1.25rem 1.5rem;
}
.table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.doc-table {
    width: 100%;
    margin-bottom: 0;
    white-space: nowrap;
}
.doc-table thead {
    background: #f7fafc;
    border-bottom: 2px solid #e2e8f0;
}
.doc-table thead th {
    border: none;
    font-weight: 600;
    padding: 0.75rem 1rem;
    color: #4a5568;
    font-size: 0.875rem;
    vertical-align: middle;
}
.doc-table tbody td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    font-size: 0.875rem;
}
.doc-table tbody tr:hover {
    background: #f8fafc;
}
.doc-table tbody tr:last-child td {
    border-bottom: none;
}
.file-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    margin-right: 0.5rem;
}
.file-badge.pdf { background: #ef4444; color: white; }
.file-badge.docx { background: #2563eb; color: white; }
.file-badge.doc { background: #1e40af; color: white; }
.btn-action {
    padding: 0.375rem 0.625rem;
    border-radius: 5px;
    font-size: 0.8125rem;
    margin: 0 0.125rem;
    white-space: nowrap;
}
.file-hash {
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    background: #f1f5f9;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    display: inline-block;
    color: #64748b;
}
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #94a3b8;
}
.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    color: #cbd5e1;
}
.pagination .page-link {
    border-radius: 5px;
    margin: 0 0.15rem;
    border: 1px solid #e2e8f0;
    color: #6777ef;
    padding: 0.375rem 0.75rem;
}
.pagination .page-item.active .page-link {
    background: #6777ef;
    border-color: #6777ef;
}
.modal-email-list {
    max-height: 350px;
    overflow-y: auto;
}
.email-item {
    padding: 0.625rem;
    border-bottom: 1px solid #e2e8f0;
    cursor: pointer;
    transition: background 0.15s;
}
.email-item:hover {
    background: #f8fafc;
}
.email-item:last-child {
    border-bottom: none;
}
.email-item input[type="checkbox"] {
    margin-right: 0.625rem;
}
.badge-uploader {
    background: #e0e7ff;
    color: #4338ca;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
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
<div class="section-header">
    <h1><i class="fas fa-folder-open"></i> Semua Dokumen TTE</h1>
   
</div>

<div class="section-body">
    
    <!-- Statistics -->
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <h3><?= number_format($stats['total_docs'] ?? 0) ?></h3>
                <p><i class="fas fa-file-alt text-primary"></i> Total Dokumen</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card" style="border-left-color: #8b5cf6;">
                <h3><?= number_format($stats['total_users'] ?? 0) ?></h3>
                <p><i class="fas fa-users text-purple"></i> Total User</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card" style="border-left-color: #3b82f6;">
                <h3><?= isset($stats['first_sign']) && $stats['first_sign'] ? date('d/m/Y', strtotime($stats['first_sign'])) : '-' ?></h3>
                <p><i class="fas fa-clock text-info"></i> Dokumen Pertama</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card" style="border-left-color: #10b981;">
                <h3><?= isset($stats['last_sign']) && $stats['last_sign'] ? date('d/m/Y', strtotime($stats['last_sign'])) : '-' ?></h3>
                <p><i class="fas fa-check-circle text-success"></i> Dokumen Terakhir</p>
            </div>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="search-box">
        <form method="GET" class="row align-items-center">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="🔍 Cari nama file atau hash..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="filter_user" class="form-control">
                    <option value="0">👥 Semua User</option>
                    <?php 
                    mysqli_data_seek($qAllUsers, 0);
                    while($usr = $qAllUsers->fetch_assoc()): 
                    ?>
                    <option value="<?= $usr['id'] ?>" <?= $filter_user == $usr['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($usr['nama']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-search"></i> Cari
                </button>
            </div>
            <div class="col-md-2">
                <a href="dokumen_tte_semua.php" class="btn btn-secondary btn-block">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Documents Table -->
    <div class="card">
        <div class="card-header">
            <h4><i class="fas fa-list"></i> Semua Dokumen (<?= number_format($totalDocs) ?>)</h4>
        </div>
        <div class="card-body p-0">
            <?php if ($documents && $documents->num_rows > 0): ?>
            <div class="table-container">
                <table class="table table-hover doc-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama File</th>
                            <th>Penandatangan</th>
                            <th>Di-upload oleh</th>
                            <th>NIK</th>
                            <th>Hash</th>
                            <th>Tanggal</th>
                            <th>Ukuran</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = $offset + 1;
                        while($doc = $documents->fetch_assoc()): 
                            $file_path = __DIR__ . '/uploads/signed/' . $doc['document_name'];
                            $file_exists = file_exists($file_path);
                            $file_size = $file_exists ? filesize($file_path) : 0;
                            $ext = strtolower(pathinfo($doc['document_name'], PATHINFO_EXTENSION));
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="file-badge <?= $ext ?>"><?= strtoupper($ext) ?></span>
                                    <span style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($doc['document_name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #334155;"><?= htmlspecialchars($doc['penandatangan_nama']) ?></div>
                                <div style="font-size: 0.75rem; color: #94a3b8;"><?= htmlspecialchars($doc['penandatangan_nik']) ?></div>
                            </td>
                            <td>
                                <span class="badge-uploader">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($doc['uploader_nama']) ?>
                                </span>
                            </td>
                            <td style="color: #64748b;"><?= htmlspecialchars($doc['uploader_nik']) ?></td>
                            <td>
                                <span class="file-hash" title="<?= htmlspecialchars($doc['document_hash']) ?>">
                                    <?= substr($doc['document_hash'], 0, 12) ?>...
                                </span>
                            </td>
                            <td style="color: #475569;"><?= date('d/m/Y H:i', strtotime($doc['signed_at'])) ?></td>
                            <td>
                                <?php if ($file_exists): ?>
                                <span class="badge badge-success"><?= number_format($file_size / 1024, 1) ?> KB</span>
                                <?php else: ?>
                                <span class="badge badge-danger">Hilang</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($file_exists): ?>
                                <button class="btn btn-sm btn-info btn-action" 
                                        onclick="openEmailModal(<?= $doc['id'] ?>, '<?= addslashes($doc['document_name']) ?>')"
                                        title="Kirim Email">
                                    <i class="fas fa-envelope"></i>
                                </button>
                                <a href="uploads/signed/<?= htmlspecialchars($doc['document_name']) ?>" 
                                   class="btn btn-sm btn-success btn-action" 
                                   download
                                   title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-danger btn-action" 
                                        onclick="deleteDoc(<?= $doc['id'] ?>, '<?= addslashes($doc['document_name']) ?>')"
                                        title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $filter_user > 0 ? '&filter_user=' . $filter_user : '' ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $filter_user > 0 ? '&filter_user=' . $filter_user : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $filter_user > 0 ? '&filter_user=' . $filter_user : '' ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h4>Belum Ada Dokumen</h4>
                <p>Belum ada dokumen TTE yang ditandatangani</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>
</section>
</div>

</div>
</div>

<!-- Email Modal -->
<div class="modal fade" id="emailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-paper-plane"></i> Kirim Dokumen via Email
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST" id="emailForm">
                <div class="modal-body">
                    <input type="hidden" name="send_email" value="1">
                    <input type="hidden" name="doc_id" id="emailDocId">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Dokumen:</strong> <span id="emailDocName"></span>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-search"></i> Cari Penerima</label>
                        <input type="text" class="form-control" id="searchEmail" placeholder="Ketik nama atau email...">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-users"></i> Pilih Penerima Email</label>
                        <div class="modal-email-list border rounded" id="emailList">
                            <?php 
                            mysqli_data_seek($qUsers, 0);
                            while($user = $qUsers->fetch_assoc()): 
                            ?>
                            <div class="email-item" data-name="<?= strtolower($user['nama']) ?>" data-email="<?= strtolower($user['email']) ?>">
                                <label class="mb-0 d-flex align-items-center" style="cursor: pointer; width: 100%;">
                                    <input type="checkbox" name="email_recipients[]" value="<?= htmlspecialchars($user['email']) ?>">
                                    <div>
                                        <strong><?= htmlspecialchars($user['nama']) ?></strong><br>
                                        <small class="text-muted"><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></small>
                                    </div>
                                </label>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <small>Pastikan pengaturan SMTP sudah dikonfigurasi dengan benar</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Kirim Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_doc" value="1">
    <input type="hidden" name="doc_id" id="deleteDocId">
</form>

<script src="assets/modules/jquery.min.js"></script>
<script src="assets/modules/popper.js"></script>
<script src="assets/modules/bootstrap/js/bootstrap.min.js"></script>
<script src="assets/modules/nicescroll/jquery.nicescroll.min.js"></script>
<script src="assets/modules/moment.min.js"></script>
<script src="assets/js/stisla.js"></script>
<script src="assets/js/scripts.js"></script>
<script src="assets/js/custom.js"></script>

<script>
function openEmailModal(docId, docName) {
    $('#emailDocId').val(docId);
    $('#emailDocName').text(docName);
    $('#searchEmail').val('');
    $('.email-item').show();
    $('input[name="email_recipients[]"]').prop('checked', false);
    $('#emailModal').modal('show');
}

// Search email
$('#searchEmail').on('keyup', function() {
    const search = $(this).val().toLowerCase();
    $('.email-item').each(function() {
        const name = $(this).data('name');
        const email = $(this).data('email');
        if (name.includes(search) || email.includes(search)) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
});

// Validate email form
$('#emailForm').on('submit', function(e) {
    const checked = $('input[name="email_recipients[]"]:checked').length;
    if (checked === 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Pilih Penerima',
            text: 'Pilih minimal 1 penerima email',
            confirmButtonColor: '#6777ef'
        });
        return false;
    }
    
    Swal.fire({
        title: 'Mengirim Email...',
        html: 'Mohon tunggu sebentar',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
});

function deleteDoc(id, filename) {
    Swal.fire({
        title: 'Hapus Dokumen?',
        html: `
            <p>Anda yakin ingin menghapus dokumen ini?</p>
            <div class="alert alert-warning mt-3">
                <strong>${filename}</strong>
            </div>
            <p class="text-danger mt-3">
                <i class="fas fa-exclamation-triangle"></i> 
                File akan dihapus permanen dan tidak dapat dikembalikan!
            </p>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash"></i> Ya, Hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            $('#deleteDocId').val(id);
            $('#deleteForm').submit();
        }
    });
}

<?php if (isset($_SESSION['success_message'])): ?>
Swal.fire({
    icon: 'success',
    title: 'Berhasil!',
    text: '<?= addslashes($_SESSION['success_message']) ?>',
    confirmButtonColor: '#6777ef'
});
<?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
Swal.fire({
    icon: 'error',
    title: 'Gagal!',
    text: '<?= addslashes($_SESSION['error_message']) ?>',
    confirmButtonColor: '#6777ef'
});
<?php unset($_SESSION['error_message']); ?>
<?php endif; ?>
</script>

</body>
</html>